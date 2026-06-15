<?php
require_once __DIR__ . '/../auth.php';

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

require_once __DIR__ . '/conecta.php';
exigirLogin();

date_default_timezone_set('America/Sao_Paulo');

$unidadesValidas = ['RJ', 'PR', 'SP', 'MG', 'ES', 'TODOS'];
$unidadeSelecionada = $_POST['unidadef'] ?? 'TODOS';
if (!in_array($unidadeSelecionada, $unidadesValidas, true)) {
    $unidadeSelecionada = 'TODOS';
}

function excelEsc(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatarDataExcel(?string $data): string
{
    if (empty($data) || $data === '0000-00-00' || $data === '0000-00-00 00:00:00') {
        return '';
    }

    $dataFormatada = date_create($data);

    return $dataFormatada ? date_format($dataFormatada, 'd/m/Y') : $data;
}

function limparDescricaoExcel(?string $acao): string
{
    return str_replace(['-1', '-2', '-3'], '', (string) $acao);
}

function obterStatusCnhExcel(?string $validade): string
{
    if (empty($validade) || $validade === '0000-00-00' || $validade === '0000-00-00 00:00:00') {
        return '';
    }

    $hoje = new DateTimeImmutable('today');
    $validadeCnh = date_create_immutable($validade);
    if (!$validadeCnh) {
        return '';
    }

    $validadeComparacao = DateTimeImmutable::createFromFormat('Y-m-d', $validadeCnh->format('Y-m-d')) ?: $validadeCnh;
    if ($hoje >= $validadeComparacao) {
        return 'Carteira vencida.';
    }

    if ($hoje >= $validadeCnh->modify('-30 days')) {
        return 'Data de vencimento em menos de 30 dias.';
    }

    return 'Carteira dentro do período de validade.';
}

$sql = "
    SELECT
        c.matricula,
        COALESCE(NULLIF(c.nome, ''), f.nome, '') AS nome,
        c.placaassoc AS placa,
        c.dataassoc AS associado_em,
        cn.validade AS venc_cnh,
        f.ccusto AS centro_de_custo,
        f.status AS status_aniel,
        l.acao AS ultima_atualizacao,
        l.data_e_hora AS atualizado_em,
        CASE
            WHEN l.mat_autor IN ('003535', '003427') THEN 'EQUIPE TI'
            ELSE COALESCE(autor.nome, l.mat_autor, '')
        END AS realizada_por
    FROM `{$databaseName}`.`tbcondutor` c
    LEFT JOIN `{$databaseName}`.`tbcnh` cn
        ON cn.matricula = c.matricula
    LEFT JOIN `{$databaseName}`.`tbfuncionario` f
        ON f.matricula = c.matricula
    LEFT JOIN (
        SELECT log_atual.*
        FROM `{$databaseName}`.`tblog` log_atual
        INNER JOIN (
            SELECT matricula, MAX(idtblog) AS idtblog
            FROM `{$databaseName}`.`tblog`
            WHERE tipo IN ('checklist', 'cadastro', 'edição')
            GROUP BY matricula
        ) ultimo_log
            ON ultimo_log.idtblog = log_atual.idtblog
    ) l
        ON l.matricula = c.matricula
    LEFT JOIN `{$databaseName}`.`tbfuncionario` autor
        ON autor.matricula = l.mat_autor
    WHERE c.ativo = 1
      AND c.matricula <> '003535'
";

if ($unidadeSelecionada !== 'TODOS') {
    $sql .= "
      AND c.placaassoc IN (
          SELECT placa
          FROM `{$databaseName}`.`tbveiculo`
          WHERE unidade = ?
      )
    ";
}

$sql .= ' ORDER BY nome ASC';

$linhas = [];
if (isset($conn) && $conn instanceof mysqli) {
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if ($unidadeSelecionada !== 'TODOS') {
            mysqli_stmt_bind_param($stmt, 's', $unidadeSelecionada);
        }
        mysqli_stmt_execute($stmt);
        $resultado = mysqli_stmt_get_result($stmt);
        if ($resultado) {
            while ($row = mysqli_fetch_assoc($resultado)) {
                $linhas[] = $row;
            }
            mysqli_free_result($resultado);
        }
        mysqli_stmt_close($stmt);
    }
}

$arquivo = 'rel-cond-ativos-' . date('Ymd-His') . '.xls';
header('Expires: Mon, 07 Jul 2016 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D,d M YH:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-type: application/x-msexcel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $arquivo . '"');
header('Content-Description: PHP Generated Data');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Relatório Condutores Ativos</title>
</head>
<body>
<table border="1">
    <tr>
        <th>Matrícula</th>
        <th>Nome</th>
        <th>Placa</th>
        <th>Status Aniel</th>
        <th>Associado em</th>
        <th>Venc. CNH</th>
        <th>Status CNH</th>
        <th>Centro de Custo</th>
        <th>Atualizado em</th>
        <th>Última atualização</th>
        <th>Realizada por</th>
    </tr>
    <?php foreach ($linhas as $linha): ?>
        <tr>
            <td><?= excelEsc($linha['matricula'] ?? '') ?></td>
            <td><?= excelEsc($linha['nome'] ?? '') ?></td>
            <td><?= excelEsc($linha['placa'] ?? '') ?></td>
            <td><?= excelEsc($linha['status_aniel'] ?? '') ?></td>
            <td><?= excelEsc(formatarDataExcel($linha['associado_em'] ?? '')) ?></td>
            <td><?= excelEsc(formatarDataExcel($linha['venc_cnh'] ?? '')) ?></td>
            <td><?= excelEsc(obterStatusCnhExcel($linha['venc_cnh'] ?? '')) ?></td>
            <td><?= excelEsc($linha['centro_de_custo'] ?? '') ?></td>
            <td><?= excelEsc(formatarDataExcel($linha['atualizado_em'] ?? '')) ?></td>
            <td><?= excelEsc(limparDescricaoExcel($linha['ultima_atualizacao'] ?? '')) ?></td>
            <td><?= excelEsc($linha['realizada_por'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
</table>
</body>
</html>

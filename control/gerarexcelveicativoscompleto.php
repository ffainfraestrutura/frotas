<?php
require_once __DIR__ . '/../auth.php';

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

require_once __DIR__ . '/conecta.php';
exigirLogin();

date_default_timezone_set('America/Sao_Paulo');

$unidadesValidas = ['RJ', 'PR', 'SP', 'MG', 'ES', 'TODOS'];
$idsOcultos = [1241, 1767, 1764, 1765, 1893, 1894, 1895, 1896];

function excelEsc(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatarDataExcel(?string $data, string $formato = 'd/m/Y'): string
{
    if (empty($data) || $data === '0000-00-00' || $data === '0000-00-00 00:00:00') {
        return '';
    }

    $dataFormatada = date_create($data);

    return $dataFormatada ? date_format($dataFormatada, $formato) : $data;
}

function limparDescricaoExcel(?string $acao): string
{
    return str_replace(['-1', '-2', '-3'], '', (string) $acao);
}

function vincularParametrosExcel(mysqli_stmt $stmt, string $tipos, array $parametros): bool
{
    $referencias = [];
    foreach ($parametros as $indice => $valor) {
        $referencias[$indice] = &$parametros[$indice];
    }

    return mysqli_stmt_bind_param($stmt, $tipos, ...$referencias);
}

function parametrosSelecionadosExcel($valores): array
{
    if (!is_array($valores)) {
        $valores = [$valores];
    }

    $selecionados = [];
    foreach ($valores as $valor) {
        $valor = trim((string) $valor);
        if ($valor !== '') {
            $selecionados[] = $valor;
        }
    }

    return array_values(array_unique($selecionados));
}

function descreverStatusVeiculoExcel($status): string
{
    if ((string) $status === '1') {
        return 'Ativo';
    }

    if ((string) $status === '0') {
        return 'Inativo';
    }

    return (string) $status;
}

$unidadeSelecionada = $_POST['unidades'] ?? 'TODOS';
if (!in_array($unidadeSelecionada, $unidadesValidas, true)) {
    $unidadeSelecionada = 'TODOS';
}

$basesSelecionadas = parametrosSelecionadosExcel($_POST['bases'] ?? []);
$statusSelecionados = [];
if (($_POST['statusa'] ?? '') === '1') {
    $statusSelecionados[] = '1';
}
if (($_POST['statusi'] ?? '') === '0') {
    $statusSelecionados[] = '0';
}
if ($statusSelecionados === []) {
    $statusSelecionados[] = '1';
}

$where = [
    'v.visivel = 1',
    'v.idtbveiculo NOT IN (' . implode(',', array_fill(0, count($idsOcultos), '?')) . ')',
];
$tipos = str_repeat('i', count($idsOcultos));
$parametros = $idsOcultos;

if ($unidadeSelecionada !== 'TODOS') {
    $where[] = 'v.unidade = ?';
    $tipos .= 's';
    $parametros[] = $unidadeSelecionada;
}

if ($basesSelecionadas !== []) {
    $where[] = 'v.basegestao IN (' . implode(',', array_fill(0, count($basesSelecionadas), '?')) . ')';
    $tipos .= str_repeat('s', count($basesSelecionadas));
    array_push($parametros, ...$basesSelecionadas);
}

$where[] = 'v.status IN (' . implode(',', array_fill(0, count($statusSelecionados), '?')) . ')';
$tipos .= str_repeat('s', count($statusSelecionados));
array_push($parametros, ...$statusSelecionados);

$sql = "
    SELECT
        v.placa,
        v.matcond,
        v.ccusto,
        v.status,
        v.unidade,
        v.basegestao,
        v.tipoposse,
        v.dtdevolucaoloc,
        COALESCE(sv.status, 'CONDUTOR FIXO') AS situacao,
        av.aplicacao AS aplicacao_nome,
        mv.modelo AS modelo_nome,
        func.nome AS nomecond,
        func.cargo AS cargo_condutor,
        man.oficina,
        logv.acao AS ultima_movimentacao,
        logv.data_e_hora AS data_ultima_movimentacao,
        CASE
            WHEN logv.mat_autor IN ('003535', '003427') THEN 'EQUIPE TI'
            ELSE COALESCE(autor.nome, logv.mat_autor, '')
        END AS feita_por
    FROM `{$databaseName}`.`tbveiculo` v
    LEFT JOIN `{$databaseName}`.`tbveiculostatus` sv
        ON sv.idtbstatusveic = v.statusvel
    LEFT JOIN `{$databaseName}`.`tbveiculoaplicacao` av
        ON av.idtbaplicacaoveic = v.aplicacao
    LEFT JOIN `{$databaseName}`.`tbveiculomodelo` mv
        ON mv.idtbmodeloveic = v.modelo
    LEFT JOIN `{$databaseName}`.`tbfuncionario` func
        ON func.matricula = v.matcond
    LEFT JOIN (
        SELECT man_atual.*
        FROM `{$databaseName}`.`tbmanprev` man_atual
        INNER JOIN (
            SELECT placa, MAX(idtbmanprev) AS idtbmanprev
            FROM `{$databaseName}`.`tbmanprev`
            WHERE status = 'ABERTO'
            GROUP BY placa
        ) ultima_man
            ON ultima_man.idtbmanprev = man_atual.idtbmanprev
    ) man
        ON man.placa = v.placa
    LEFT JOIN (
        SELECT log_atual.*
        FROM `{$databaseName}`.`tblog` log_atual
        INNER JOIN (
            SELECT placa, MAX(idtblog) AS idtblog
            FROM `{$databaseName}`.`tblog`
            WHERE tipo IN ('cadastro', 'edição', 'checklist')
            GROUP BY placa
        ) ultimo_log
            ON ultimo_log.idtblog = log_atual.idtblog
    ) logv
        ON logv.placa = v.placa
    LEFT JOIN `{$databaseName}`.`tbfuncionario` autor
        ON autor.matricula = logv.mat_autor
    WHERE " . implode(' AND ', $where) . "
    ORDER BY v.placa ASC
";

$linhas = [];
if (isset($conn) && $conn instanceof mysqli) {
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        vincularParametrosExcel($stmt, $tipos, $parametros);
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

$arquivo = 'rel-veiculos-ativos-' . date('Ymd-His') . '.xls';
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
    <title>Relatório Veículos Ativos</title>
</head>
<body>
<table border="1">
    <tr>
        <th>Placa</th>
        <th>Condutor</th>
        <th>Cargo</th>
        <th>Matrícula</th>
        <th>Unidade</th>
        <th>Base Gestão</th>
        <th>Situação</th>
        <th>Status</th>
        <th>Aplicação</th>
        <th>Em manutenção?</th>
        <th>Tipo de posse</th>
        <th>Vencimento da locação</th>
        <th>Modelo</th>
        <th>Últ. movimentação</th>
        <th>Data Últ. movimentação</th>
        <th>Feita por</th>
    </tr>
    <?php foreach ($linhas as $linha): ?>
        <tr>
            <td><?= excelEsc($linha['placa'] ?? '') ?></td>
            <td><?= excelEsc($linha['nomecond'] ?? '') ?></td>
            <td><?= excelEsc($linha['cargo_condutor'] ?? '') ?></td>
            <td><?= excelEsc($linha['matcond'] ?? '') ?></td>
            <td><?= excelEsc($linha['unidade'] ?? '') ?></td>
            <td><?= excelEsc($linha['basegestao'] ?? '') ?></td>
            <td><?= excelEsc($linha['situacao'] ?? '') ?></td>
            <td><?= excelEsc(descreverStatusVeiculoExcel($linha['status'] ?? '')) ?></td>
            <td><?= excelEsc($linha['aplicacao_nome'] ?? '') ?></td>
            <td><?= excelEsc(($linha['oficina'] ?? '') !== '' ? 'Sim - ' . $linha['oficina'] : 'Não') ?></td>
            <td><?= excelEsc($linha['tipoposse'] ?? '') ?></td>
            <td><?= excelEsc(formatarDataExcel($linha['dtdevolucaoloc'] ?? '')) ?></td>
            <td><?= excelEsc($linha['modelo_nome'] ?? '') ?></td>
            <td><?= excelEsc(limparDescricaoExcel($linha['ultima_movimentacao'] ?? '')) ?></td>
            <td><?= excelEsc(formatarDataExcel($linha['data_ultima_movimentacao'] ?? '')) ?></td>
            <td><?= excelEsc($linha['feita_por'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
</table>
</body>
</html>

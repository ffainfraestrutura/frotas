<?php
require_once __DIR__ . '/../../auth.php';

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

require_once __DIR__ . '/../../control/conecta.php';
exigirLogin();

date_default_timezone_set('America/Sao_Paulo');

$hoje = date('Y-m-d');
$dataInicial = trim((string) ($_POST['datain'] ?? ''));
$dataFinal = trim((string) ($_POST['datafi'] ?? ''));
$tipoSelecionado = trim((string) ($_POST['tipofim'] ?? ''));

function excelEsc(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function vincularParametrosExcel(mysqli_stmt $stmt, string $tipos, array $parametros): bool
{
    $referencias = [];
    foreach ($parametros as $indice => $valor) {
        $referencias[$indice] = &$parametros[$indice];
    }

    return mysqli_stmt_bind_param($stmt, $tipos, ...$referencias);
}

function descreverTipoManutencaoExcel(?string $tipo): string
{
    $tipos = [
        'MP' => 'Manutenção Preventiva',
        'MC' => 'Manutenção Corretiva',
        'OS' => 'Ordem de Serviço',
        'SS' => 'Sinistro',
    ];

    return $tipos[$tipo] ?? (string) $tipo;
}

function descreverStatusManutencaoExcel($status): string
{
    if ((string) $status === '1') {
        return 'ABERTO';
    }

    if ((string) $status === '2') {
        return 'CONCLUIDO';
    }

    return (string) $status;
}

function descreverBooleanoExcel($valor): string
{
    if ((string) $valor === '1') {
        return 'SIM';
    }

    if ((string) $valor === '0') {
        return 'NÃO';
    }

    return (string) $valor;
}

$where = [
    "man.tipo <> 'Desativada'",
    "man.placa <> 'ABC1234'",
    "man.placa <> ''",
];
$tiposBind = '';
$parametros = [];

if ($dataInicial !== '' && $dataFinal !== '') {
    $where[] = 'man.data >= ?';
    $where[] = 'man.data <= ?';
    $tiposBind .= 'ss';
    $parametros[] = $dataInicial . ' 00:00:00';
    $parametros[] = $dataFinal . ' 23:59:59';
} else {
    $where[] = 'man.data >= ?';
    $where[] = 'man.data <= ?';
    $tiposBind .= 'ss';
    $parametros[] = $hoje . ' 00:00:00';
    $parametros[] = $hoje . ' 23:59:59';
}

if ($tipoSelecionado !== '') {
    $where[] = 'man.tipo = ?';
    $tiposBind .= 's';
    $parametros[] = $tipoSelecionado;
}

$sql = "
    SELECT
        man.*,
        vei.unidade
    FROM `{$databaseName}`.`tbmanprev` man
    LEFT JOIN `{$databaseName}`.`tbveiculo` vei
        ON vei.placa = man.placa
    WHERE " . implode(' AND ', $where) . "
    ORDER BY man.data DESC, man.idtbmanprev DESC
";

$linhas = [];
if (isset($conn) && $conn instanceof mysqli) {
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        vincularParametrosExcel($stmt, $tiposBind, $parametros);
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

$arquivo = 'rel-todas-manutencoes-' . date('Ymd-His') . '.xls';
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
    <title>Relatório Manutenções</title>
</head>
<body>
<table border="1">
    <tr>
        <th>Número</th>
        <th>Protocolo</th>
        <th>Placa</th>
        <th>Unidade</th>
        <th>Data de Agendamento</th>
        <th>Data de Cadastro</th>
        <th>Tipo</th>
        <th>Data Entrada</th>
        <th>Status</th>
        <th>Oficina</th>
        <th>Centro de Custo</th>
        <th>Descrição</th>
        <th>Previsão de Saída</th>
        <th>Data de Retirada</th>
        <th>Reembolso Aprovado</th>
        <th>Valor Oficina</th>
        <th>Valor Mão de Obra</th>
        <th>Valor Material</th>
        <th>Observação</th>
        <th>Descontar Condutor</th>
        <th>Hodômetro</th>
    </tr>
    <?php foreach ($linhas as $linha): ?>
        <tr>
            <td><?= excelEsc($linha['idtbmanprev'] ?? '') ?></td>
            <td><?= excelEsc($linha['protocolo'] ?? '') ?></td>
            <td><?= excelEsc($linha['placa'] ?? '') ?></td>
            <td><?= excelEsc($linha['unidade'] ?? '') ?></td>
            <td><?= excelEsc($linha['dataagendamento'] ?? '') ?></td>
            <td><?= excelEsc($linha['data'] ?? '') ?></td>
            <td><?= excelEsc(descreverTipoManutencaoExcel($linha['tipo'] ?? '')) ?></td>
            <td><?= excelEsc($linha['dataentrada'] ?? '') ?></td>
            <td><?= excelEsc(descreverStatusManutencaoExcel($linha['status'] ?? '')) ?></td>
            <td><?= excelEsc($linha['oficina'] ?? '') ?></td>
            <td><?= excelEsc($linha['ccusto'] ?? '') ?></td>
            <td><?= excelEsc($linha['descricao'] ?? '') ?></td>
            <td><?= excelEsc($linha['prevsaida'] ?? '') ?></td>
            <td><?= excelEsc($linha['dataretirada'] ?? '') ?></td>
            <td><?= excelEsc(descreverBooleanoExcel($linha['reembolsoaprov'] ?? '')) ?></td>
            <td><?= excelEsc($linha['valoroficina'] ?? '') ?></td>
            <td><?= excelEsc($linha['valormaoobra'] ?? '') ?></td>
            <td><?= excelEsc($linha['valormaterial'] ?? '') ?></td>
            <td><?= excelEsc($linha['observacao'] ?? '') ?></td>
            <td><?= excelEsc(descreverBooleanoExcel($linha['descontarcond'] ?? '')) ?></td>
            <td><?= excelEsc($linha['hodometro'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
</table>
</body>
</html>

<?php
require_once __DIR__ . '/../../includes/autofrota_common.php';
$autofrota = autofrotaInit();
$con = $autofrota['conn'];
$databaseName = (string) ($autofrota['databaseName'] ?? '');
header('Content-Type: text/html; charset=utf-8');

$id = (int) ($_POST['idinserido'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id < 1 || !($con instanceof mysqli)) {
    http_response_code(400);
    exit('Vistoria inválida.');
}

$stmt = mysqli_prepare($con, "UPDATE `{$databaseName}`.`tbvistoria` SET statusreg = 1 WHERE idtbvistoria = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) < 1) {
    http_response_code(404);
    exit('Não foi possível localizar ou finalizar a vistoria.');
}

$stmtDados = mysqli_prepare($con, "SELECT matricula, matrvistoriador, placa FROM `{$databaseName}`.`tbvistoria` WHERE idtbvistoria = ? LIMIT 1");
if ($stmtDados) {
    mysqli_stmt_bind_param($stmtDados, 'i', $id);
    mysqli_stmt_execute($stmtDados);
    $vistoria = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtDados)) ?: [];
    $stmtLog = mysqli_prepare($con, 'INSERT INTO tblog (data_e_hora, acao, matricula, mat_autor, tipo, placa) VALUES (?, ?, ?, ?, ?, ?)');
    if ($stmtLog) {
        $dataHora = date('Y-m-d H:i:s');
        $acao = 'Concluiu vistoria/checklist';
        $tipoLog = 'checklist';
        $matricula = (string) ($vistoria['matricula'] ?? '');
        $matAutor = (string) ($vistoria['matrvistoriador'] ?? ($autofrota['matricula'] ?? ''));
        $placa = (string) ($vistoria['placa'] ?? '');
        mysqli_stmt_bind_param($stmtLog, 'ssssss', $dataHora, $acao, $matricula, $matAutor, $tipoLog, $placa);
        mysqli_stmt_execute($stmtLog);
    }
}

header('Location: ../checklistp3.php?id=' . $id);
exit;
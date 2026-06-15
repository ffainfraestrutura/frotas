<?php
require_once __DIR__ . '/../../includes/autofrota_common.php';

header('Content-Type: application/json; charset=utf-8');

$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');

$payload = json_decode(file_get_contents('php://input') ?: '{}', true);
$autoinfracao = trim((string) ($payload['autoinfracao'] ?? ''));
$placa = trim((string) ($payload['placa'] ?? ''));

if (!$conn instanceof mysqli || $databaseName === '' || $autoinfracao === '' || $placa === '') {
    echo json_encode(['status' => 'erro', 'auto' => $autoinfracao, 'data_log' => '']);
    exit;
}

$sql = "SELECT data_e_hora FROM `{$databaseName}`.tblog WHERE placa = ? AND tipo = 'multa' AND acao LIKE ? ORDER BY data_e_hora DESC LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
$dataEnvio = '';
if ($stmt) {
    $like = '%' . $autoinfracao . '-Imprimir Recibo DP%';
    mysqli_stmt_bind_param($stmt, 'ss', $placa, $like);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    $dataEnvio = (string) ($row['data_e_hora'] ?? '');
    mysqli_stmt_close($stmt);
}

echo json_encode(['status' => 'sucesso', 'auto' => $autoinfracao, 'data_log' => $dataEnvio]);

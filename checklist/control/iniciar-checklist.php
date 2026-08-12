<?php
require_once __DIR__ . '/../../includes/autofrota_common.php';
$autofrota = autofrotaInit();
$con = $autofrota['conn'];
$databaseName = (string) ($autofrota['databaseName'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !($con instanceof mysqli) || preg_match('/^[A-Za-z0-9_]+$/', $databaseName) !== 1) {
    http_response_code(400);
    exit('Não foi possível iniciar o checklist.');
}

$placa = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) ($_POST['placa'] ?? '')) ?? '');
if ($placa === '') {
    header('Location: ../checklistinicio.php?erro=placa');
    exit;
}

$stmt = mysqli_prepare(
    $con,
    "SELECT idtbvistoria, placa, datavistoria, matricula, nome, vistoriador
     FROM `{$databaseName}`.`tbvistoria`
     WHERE placa = ? AND statusreg = 0
     ORDER BY idtbvistoria DESC
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 's', $placa);
mysqli_stmt_execute($stmt);
$pendente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if ($pendente) {
    $query = http_build_query([
        'placa' => $pendente['placa'],
        'datavistoria' => $pendente['datavistoria'],
        'matricula' => $pendente['matricula'],
        'idinserido' => $pendente['idtbvistoria'],
        'retomada' => 1,
    ]);
    header('Location: ../checklistp2.php?' . $query);
    exit;
}

header('Location: ../checklistp1.php?placa=' . rawurlencode($placa));
exit;
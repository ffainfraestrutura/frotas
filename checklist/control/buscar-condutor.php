<?php
require_once __DIR__ . '/../../includes/autofrota_common.php';
$autofrota = autofrotaInit();
$con = $autofrota['conn'];
$databaseName = (string) ($autofrota['databaseName'] ?? '');
header('Content-Type: application/json; charset=utf-8');

$responder = static function (int $status, array $dados): never {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !($con instanceof mysqli) || preg_match('/^[A-Za-z0-9_]+$/', $databaseName) !== 1) {
    $responder(400, ['ok' => false, 'message' => 'Consulta inválida.']);
}

$cpf = preg_replace('/\D/', '', (string) ($_GET['cpf'] ?? '')) ?? '';
$matricula = trim((string) ($_GET['matricula'] ?? ''));
if ($cpf === '' && $matricula === '') {
    $responder(422, ['ok' => false, 'message' => 'Informe CPF ou matrícula.']);
}

$filtro = $cpf !== '' ? 'REPLACE(REPLACE(REPLACE(f.cpf, ".", ""), "-", ""), " ", "") = ?' : 'f.matricula = ?';
$busca = $cpf !== '' ? $cpf : $matricula;
$sql = "SELECT f.nome, f.matricula, f.cpf, f.ccusto, f.status, cn.numcnh AS cnh, cn.categoria AS categoriacnh, DATE(cn.validade) AS validadecnh
        FROM `{$databaseName}`.`tbfuncionario` f
        LEFT JOIN `{$databaseName}`.`tbcnh` cn ON cn.matricula = f.matricula
        WHERE {$filtro}
          AND UPPER(TRIM(COALESCE(f.status, ''))) NOT IN ('DEMITIDO', 'AFASTADO', 'FÉRIAS', 'FERIAS')
        LIMIT 1";
$stmt = mysqli_prepare($con, $sql);
if (!$stmt) {
    $responder(500, ['ok' => false, 'message' => 'Não foi possível preparar a consulta.']);
}
mysqli_stmt_bind_param($stmt, 's', $busca);
mysqli_stmt_execute($stmt);
$condutor = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$condutor) {
    $responder(404, ['ok' => false, 'message' => 'Condutor não encontrado ou com status inválido.']);
}
$responder(200, ['ok' => true, 'condutor' => $condutor]);
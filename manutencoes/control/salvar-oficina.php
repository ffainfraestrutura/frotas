<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../control/conecta.php';

exigirLogin();

$perfilLogado = (string) ($_SESSION['perfil'] ?? '');
$matriculaLogada = (string) ($_SESSION['matricula'] ?? $_SESSION['usuario'] ?? '');
$bloqueados = ['160030', '410109', '501285', '410039', '411425', '003931'];
$podeEditar = $perfilLogado === '4' && !in_array($matriculaLogada, $bloqueados, true);
$placa = strtoupper(trim((string) ($_POST['placa'] ?? '')));
$placa = str_replace(['-', ' '], '', $placa);

function voltarParaCadastro(string $placa, string $mensagem): never
{
    header('Location: ../adicionar-oficina.php?placa=' . rawurlencode($placa) . '&msg=' . rawurlencode($mensagem));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !$podeEditar) {
    voltarParaCadastro($placa, 'Sem permissão para cadastrar a oficina.');
}

$nome = trim((string) ($_POST['nome'] ?? ''));
$telefone = trim((string) ($_POST['telefone'] ?? ''));
if ($nome === '') {
    voltarParaCadastro($placa, 'Informe o nome da oficina.');
}

$database = (isset($databaseName) && preg_match('/^[A-Za-z0-9_]+$/', (string) $databaseName))
    ? (string) $databaseName
    : 'bdautofrotas';

$sqlExiste = "SELECT nome FROM `{$database}`.`tboficina` WHERE UPPER(TRIM(nome)) = UPPER(?) LIMIT 1";
$stmtExiste = mysqli_prepare($conn, $sqlExiste);
if (!$stmtExiste) {
    voltarParaCadastro($placa, 'Não foi possível validar a oficina.');
}
mysqli_stmt_bind_param($stmtExiste, 's', $nome);
mysqli_stmt_execute($stmtExiste);
$resExiste = mysqli_stmt_get_result($stmtExiste);
$oficinaExistente = $resExiste ? mysqli_fetch_assoc($resExiste) : null;
mysqli_stmt_close($stmtExiste);

if ($oficinaExistente) {
    $nomeExistente = (string) ($oficinaExistente['nome'] ?? $nome);
    header('Location: ../cadastrar-manutencao-preventiva.php?placa=' . rawurlencode($placa) . '&oficina=' . rawurlencode($nomeExistente));
    exit;
}

$sqlInsert = "INSERT INTO `{$database}`.`tboficina` (nome, telefone) VALUES (?, ?)";
$stmtInsert = mysqli_prepare($conn, $sqlInsert);
if (!$stmtInsert) {
    voltarParaCadastro($placa, 'Não foi possível cadastrar a oficina.');
}
mysqli_stmt_bind_param($stmtInsert, 'ss', $nome, $telefone);
$salvou = mysqli_stmt_execute($stmtInsert);
mysqli_stmt_close($stmtInsert);

if (!$salvou) {
    voltarParaCadastro($placa, 'Não foi possível cadastrar a oficina.');
}

header('Location: ../cadastrar-manutencao-preventiva.php?placa=' . rawurlencode($placa) . '&oficina=' . rawurlencode($nome));
exit;
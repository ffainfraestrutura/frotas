<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/conecta.php';

date_default_timezone_set('America/Sao_Paulo');
exigirLogin();

$perfilLogado = (string) ($_SESSION['perfil'] ?? '');
$matriculaLogada = (string) ($_SESSION['matricula'] ?? $_SESSION['usuario'] ?? '');
$bloqueados = ['160030', '410109', '501285', '410039', '411425', '003931'];
$podeEditar = $perfilLogado === '4' && !in_array($matriculaLogada, $bloqueados, true);

$database = (isset($databaseName) && preg_match('/^[A-Za-z0-9_]+$/', (string) $databaseName))
  ? (string) $databaseName
  : 'bdautofrotas';

function redirecionarVeiculos(string $mensagem = ''): never
{
  $url = '../veiculos.php';
  if ($mensagem !== '') {
    $url .= '?msg=' . urlencode($mensagem);
  }

  header('Location: ' . $url);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  redirecionarVeiculos('Requisição inválida.');
}

if (!$podeEditar) {
  redirecionarVeiculos('Sem permissão para apagar veículo.');
}

$idtbveiculo = (int) ($_POST['idtbveiculo'] ?? 0);
$escolha = (string) ($_POST['escolha'] ?? '0');
$matrAutor = trim((string) ($_POST['matr_autor'] ?? $matriculaLogada));
$placa = strtoupper(str_replace(['-', ' '], '', trim((string) ($_POST['placa'] ?? ''))));

if ($escolha !== '1') {
  redirecionarVeiculos('Processo impedido.');
}

if ($idtbveiculo <= 0) {
  redirecionarVeiculos('ID inválido.');
}

$schemasTentativa = array_values(array_unique(array_filter([
  $database,
  'bdfrota',
])));

$ok = false;
$linhasAfetadas = 0;
$erroPreparacao = false;

foreach ($schemasTentativa as $schema) {
  $sql = "UPDATE `{$schema}`.`tbveiculo` SET visivel = 0 WHERE idtbveiculo = ?";
  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt) {
    $erroPreparacao = true;
    continue;
  }

  mysqli_stmt_bind_param($stmt, 'i', $idtbveiculo);
  $executou = mysqli_stmt_execute($stmt);
  $afetadasAtual = mysqli_stmt_affected_rows($stmt);
  mysqli_stmt_close($stmt);

  if ($executou) {
    $ok = true;
  }

  if ($afetadasAtual > 0) {
    $linhasAfetadas = $afetadasAtual;
    break;
  }
}

if (!$ok) {
  redirecionarVeiculos('Erro ao apagar registro.');
}

if ($linhasAfetadas < 1) {
  if ($erroPreparacao) {
    redirecionarVeiculos('Erro ao preparar exclusão.');
  }
  redirecionarVeiculos('ID inválido.');
}

$arquivoLog = __DIR__ . '/../../func/log.php';
if (is_file($arquivoLog)) {
  include_once $arquivoLog;
  if (function_exists('enviarlognovo')) {
    @enviarlognovo(date('Y-m-d H:i:s'), 'Apagou registro placa', '', $matrAutor, 'cadastro', $placa);
  }
}

redirecionarVeiculos('Registro do veículo apagado.');
?>
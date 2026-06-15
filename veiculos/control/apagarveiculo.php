<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../control/conecta.php';

date_default_timezone_set('America/Sao_Paulo');
exigirLogin();

$perfilLogado = (string) ($_SESSION['perfil'] ?? '');
$matriculaLogada = (string) ($_SESSION['matricula'] ?? $_SESSION['usuario'] ?? '');
$bloqueados = ['160030', '410109', '501285', '410039', '411425', '003931'];
$podeEditar = $perfilLogado === '4' && !in_array($matriculaLogada, $bloqueados, true);

$database = (isset($databaseName) && preg_match('/^[A-Za-z0-9_]+$/', (string) $databaseName))
  ? (string) $databaseName
  : 'bdautofrotas';

function resumoPostApagar(array $post): string
{
  $id = (string) ($post['idtbveiculo'] ?? '');
  $escolha = (string) ($post['escolha'] ?? '');
  $placa = (string) ($post['placa'] ?? '');
  $matr = (string) ($post['matr_autor'] ?? '');

  return 'POST recebido: idtbveiculo=' . ($id === '' ? '(vazio)' : $id)
    . ', escolha=' . ($escolha === '' ? '(vazio)' : $escolha)
    . ', placa=' . ($placa === '' ? '(vazia)' : $placa)
    . ', matr_autor=' . ($matr === '' ? '(vazia)' : $matr);
}

function montarDetalheErro(string $mensagemBase, array $detalhes): string
{
  $detalhesFiltrados = array_values(array_filter(array_map('trim', $detalhes), static fn($v) => $v !== ''));
  if (count($detalhesFiltrados) < 1) {
    return $mensagemBase;
  }

  return $mensagemBase . ' | ' . implode(' | ', $detalhesFiltrados);
}

function redirecionarVeiculos(string $mensagem = ''): never
{
  $url = '../listagem-veiculo.php';
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

$resumoPost = resumoPostApagar($_POST);

$idtbveiculo = (int) ($_POST['idtbveiculo'] ?? 0);
$escolha = (string) ($_POST['escolha'] ?? '0');
$matrAutor = trim((string) ($_POST['matr_autor'] ?? $matriculaLogada));
$placa = strtoupper(str_replace(['-', ' '], '', trim((string) ($_POST['placa'] ?? ''))));

if ($escolha !== '1') {
  redirecionarVeiculos('Processo impedido.');
}

if ($idtbveiculo <= 0) {
  redirecionarVeiculos(montarDetalheErro('ID inválido.', [$resumoPost]));
}

$schemasTentativa = array_values(array_unique(array_filter([
  $database,
  'bdfrota',
])));

$ok = false;
$linhasAfetadas = 0;
$erroPreparacao = false;
$consultasTentadas = [];
$errosDetalhados = [];
$idExisteEmAlgumSchema = false;

foreach ($schemasTentativa as $schema) {
  $schemaSeguro = preg_replace('/[^A-Za-z0-9_]/', '', (string) $schema);
  if ($schemaSeguro === '') {
    continue;
  }

  $sqlExiste = "SELECT idtbveiculo, visivel FROM `{$schemaSeguro}`.`tbveiculo` WHERE idtbveiculo = ? LIMIT 1";
  $stmtExiste = mysqli_prepare($conn, $sqlExiste);
  if ($stmtExiste) {
    mysqli_stmt_bind_param($stmtExiste, 'i', $idtbveiculo);
    if (mysqli_stmt_execute($stmtExiste)) {
      $resultadoExiste = mysqli_stmt_get_result($stmtExiste);
      if ($resultadoExiste instanceof mysqli_result) {
        $linhaExiste = mysqli_fetch_assoc($resultadoExiste);
        if (is_array($linhaExiste)) {
          $idExisteEmAlgumSchema = true;
        }
      }
    } else {
      $errosDetalhados[] = 'Falha execute SELECT em ' . $schemaSeguro . ': ' . mysqli_stmt_error($stmtExiste);
    }
    mysqli_stmt_close($stmtExiste);
  } else {
    $errosDetalhados[] = 'Falha prepare SELECT em ' . $schemaSeguro . ': ' . mysqli_error($conn);
  }

  $sql = "UPDATE `{$schemaSeguro}`.`tbveiculo` SET visivel = 0 WHERE idtbveiculo = ?";
  $consultasTentadas[] = $sql . ' [id=' . $idtbveiculo . ']';
  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt) {
    $erroPreparacao = true;
    $errosDetalhados[] = 'Falha prepare UPDATE em ' . $schemaSeguro . ': ' . mysqli_error($conn);
    continue;
  }

  mysqli_stmt_bind_param($stmt, 'i', $idtbveiculo);
  $executou = mysqli_stmt_execute($stmt);
  if (!$executou) {
    $errosDetalhados[] = 'Falha execute UPDATE em ' . $schemaSeguro . ': ' . mysqli_stmt_error($stmt);
  }
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
  redirecionarVeiculos(montarDetalheErro('Erro ao apagar registro.', [
    $resumoPost,
    'Consultas: ' . implode(' || ', $consultasTentadas),
    implode(' || ', $errosDetalhados),
  ]));
}

if ($linhasAfetadas < 1) {
  if ($erroPreparacao) {
    redirecionarVeiculos(montarDetalheErro('Erro ao preparar exclusão.', [
      $resumoPost,
      'Consultas: ' . implode(' || ', $consultasTentadas),
      implode(' || ', $errosDetalhados),
    ]));
  }

  $mensagemSemAfetar = $idExisteEmAlgumSchema
    ? 'Nenhum registro alterado (veículo já invisível ou sem mudança).'
    : 'ID inválido.';

  redirecionarVeiculos(montarDetalheErro($mensagemSemAfetar, [
    $resumoPost,
    'Consultas: ' . implode(' || ', $consultasTentadas),
    implode(' || ', $errosDetalhados),
  ]));
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
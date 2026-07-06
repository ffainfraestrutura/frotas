<?php
require_once __DIR__ . '/../../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? ($GLOBALS['conn'] ?? null);
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? ($GLOBALS['databaseName'] ?? 'bdautofrotas'));
$databaseCorp = (string) ($autofrotaSessao['databaseCorp'] ?? ($GLOBALS['databaseCorp'] ?? 'bdcorp'));
$matriculaLogada = (string) ($autofrotaSessao['matricula'] ?? $_SESSION['matricula'] ?? '');
$perfilLogado = (string) ($autofrotaSessao['perfil'] ?? $_SESSION['perfil'] ?? '');

if (!$conn instanceof mysqli) {
    exit('Conexão indisponível.');
}

function voltarPedidoOrcamentoDiretor(string $mensagem, string $tipo = 'danger'): void
{
    $_SESSION['pedir_orcamento_diretor_mensagem'] = $mensagem;
    $_SESSION['pedir_orcamento_diretor_tipo'] = $tipo;
    header('Location: ../pedir-orcamento.php');
    exit;
}

function normalizarMoedaPedidoOrcamentoDiretor(string $valor): float
{
    $valor = str_replace('.', '', trim($valor));
    $valor = str_replace(',', '.', $valor);
    return is_numeric($valor) ? (float) $valor : 0.0;
}

if ($perfilLogado !== '10') {
    voltarPedidoOrcamentoDiretor('Acesso permitido apenas para perfil diretor.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarPedidoOrcamentoDiretor('Método inválido.');
}
if (!hash_equals((string) ($_SESSION['pedir_orcamento_diretor_token'] ?? ''), (string) ($_POST['token'] ?? ''))) {
    voltarPedidoOrcamentoDiretor('Token inválido ou expirado. Atualize a página e tente novamente.');
}

$valor = normalizarMoedaPedidoOrcamentoDiretor((string) ($_POST['valor'] ?? ''));
$justificativa = trim((string) ($_POST['justificativa'] ?? ''));

if ($matriculaLogada === '') {
    voltarPedidoOrcamentoDiretor('Sessão sem matrícula. Faça login novamente.');
}
if ($valor <= 0) {
    voltarPedidoOrcamentoDiretor('Informe um valor válido.');
}
if ($justificativa === '') {
    voltarPedidoOrcamentoDiretor('Informe a justificativa.');
}

$diretor = buscarUmaLinha($conn, "SELECT matricula FROM `{$databaseCorp}`.`tbdiretor` WHERE matricula = ? LIMIT 1", 's', [$matriculaLogada]);
if ($diretor === []) {
    voltarPedidoOrcamentoDiretor('Cadastro do diretor não encontrado.');
}

$agora = date('Y-m-d H:i:s');
$insert = consultaPreparada(
    $conn,
    "INSERT INTO `{$databaseName}`.`tbpedidosgerente` (matricula, valor, justificativa, data, flag) VALUES (?, ?, ?, ?, 0)",
    'sdss',
    [$matriculaLogada, $valor, $justificativa, $agora]
);
if (($insert['erro'] ?? '') !== '') {
    voltarPedidoOrcamentoDiretor('Erro ao registrar pedido: ' . $insert['erro']);
}

consultaPreparada(
    $conn,
    "INSERT INTO `{$databaseName}`.`tblog` (data_e_hora, acao, flag, matricula, mat_autor, valor_ant, valor_novo) VALUES (?, 'Pediu orçamento extra diretor à frota', 2, ?, ?, 0, ?)",
    'sssd',
    [$agora, $matriculaLogada, $matriculaLogada, $valor]
);

voltarPedidoOrcamentoDiretor('Pedido de orçamento enviado com sucesso.', 'success');
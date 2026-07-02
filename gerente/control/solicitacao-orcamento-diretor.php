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

function voltarSolicitacaoOrcamentoDiretor(string $mensagem, string $tipo = 'danger'): void
{
    $_SESSION['solicitacao_orcamento_diretor_mensagem'] = $mensagem;
    $_SESSION['solicitacao_orcamento_diretor_tipo'] = $tipo;
    header('Location: ../solicitacao-orcamento-diretor.php');
    exit;
}

function normalizarMoedaSolicitacaoOrcamentoDiretor(string $valor): float
{
    $valor = str_replace('.', '', trim($valor));
    $valor = str_replace(',', '.', $valor);
    return is_numeric($valor) ? (float) $valor : 0.0;
}

if ($perfilLogado !== '3') {
    voltarSolicitacaoOrcamentoDiretor('Acesso permitido apenas para perfil gerente.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarSolicitacaoOrcamentoDiretor('Método inválido.');
}
$tokenPost = (string) ($_POST['token'] ?? '');
$tokensSessao = $_SESSION['solicitacao_orcamento_diretor_tokens'] ?? [];
if (!is_array($tokensSessao)) {
    $tokensSessao = [];
}
$tokenLegado = (string) ($_SESSION['solicitacao_orcamento_diretor_token'] ?? '');
if ($tokenLegado !== '') {
    $tokensSessao[] = $tokenLegado;
}
$tokenValido = false;
foreach ($tokensSessao as $indiceToken => $tokenSessao) {
    if ($tokenPost !== '' && is_string($tokenSessao) && hash_equals($tokenSessao, $tokenPost)) {
        $tokenValido = true;
        unset($tokensSessao[$indiceToken]);
        break;
    }
}
$_SESSION['solicitacao_orcamento_diretor_tokens'] = array_slice(array_values(array_unique($tokensSessao)), -5);
if (!$tokenValido) {
    voltarSolicitacaoOrcamentoDiretor('Token inválido ou expirado. Atualize a página e tente novamente.');
}

$valor = normalizarMoedaSolicitacaoOrcamentoDiretor((string) ($_POST['valor'] ?? ''));
$justificativa = trim((string) ($_POST['justificativa'] ?? ''));

if ($matriculaLogada === '') {
    voltarSolicitacaoOrcamentoDiretor('Sessão sem matrícula. Faça login novamente.');
}
if ($valor <= 0) {
    voltarSolicitacaoOrcamentoDiretor('Informe um valor válido.');
}
if ($justificativa === '') {
    voltarSolicitacaoOrcamentoDiretor('Informe a justificativa.');
}

$gerente = buscarUmaLinha($conn, "SELECT matricula, idtbdiretor FROM `{$databaseCorp}`.`tbgerente` WHERE matricula = ? LIMIT 1", 's', [$matriculaLogada]);
if ($gerente === []) {
    voltarSolicitacaoOrcamentoDiretor('Cadastro do gerente não encontrado.');
}
if ((int) ($gerente['idtbdiretor'] ?? 0) <= 0) {
    voltarSolicitacaoOrcamentoDiretor('Gerente sem diretor vinculado.');
}

$agora = date('Y-m-d H:i:s');
$insert = consultaPreparada(
    $conn,
    "INSERT INTO `{$databaseName}`.`tbpedidosdiretor` (matricula, valor, justificativa, data, flag) VALUES (?, ?, ?, ?, 0)",
    'sdss',
    [$matriculaLogada, $valor, $justificativa, $agora]
);
if (($insert['erro'] ?? '') !== '') {
    voltarSolicitacaoOrcamentoDiretor('Erro ao registrar pedido: ' . $insert['erro']);
}

consultaPreparada(
    $conn,
    "INSERT INTO `{$databaseName}`.`tblog` (data_e_hora, acao, flag, matricula, mat_autor, valor_ant, valor_novo) VALUES (?, 'Pediu orçamento extra ao diretor', 2, ?, ?, 0, ?)",
    'sssd',
    [$agora, $matriculaLogada, $matriculaLogada, $valor]
);

voltarSolicitacaoOrcamentoDiretor('Pedido de orçamento enviado ao diretor com sucesso.', 'success');
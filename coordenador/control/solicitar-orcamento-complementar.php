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

function voltarSolicitacaoOrcamentoCoordenador(string $mensagem, string $tipo = 'danger'): void
{
    $_SESSION['solicitacao_orcamento_coordenador_mensagem'] = $mensagem;
    $_SESSION['solicitacao_orcamento_coordenador_tipo'] = $tipo;
    header('Location: ../solicitar-orcamento-complementar.php');
    exit;
}

function normalizarMoedaSolicitacaoOrcamentoCoordenador(string $valor): float
{
    $valor = str_replace('.', '', trim($valor));
    $valor = str_replace(',', '.', $valor);
    return is_numeric($valor) ? (float) $valor : 0.0;
}

if ($perfilLogado !== '2') {
    voltarSolicitacaoOrcamentoCoordenador('Acesso permitido apenas para perfil coordenador.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarSolicitacaoOrcamentoCoordenador('Método inválido.');
}
$tokenPost = (string) ($_POST['token'] ?? '');
$tokensSessao = $_SESSION['solicitacao_orcamento_coordenador_tokens'] ?? [];
if (!is_array($tokensSessao)) {
    $tokensSessao = [];
}
$tokenLegado = (string) ($_SESSION['solicitacao_orcamento_coordenador_token'] ?? '');
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
$_SESSION['solicitacao_orcamento_coordenador_tokens'] = array_slice(array_values(array_unique($tokensSessao)), -5);
if (!$tokenValido) {
    voltarSolicitacaoOrcamentoCoordenador('Token inválido ou expirado. Atualize a página e tente novamente.');
}

$valor = normalizarMoedaSolicitacaoOrcamentoCoordenador((string) ($_POST['valor'] ?? ''));
$justificativa = trim((string) ($_POST['justificativa'] ?? ''));

if ($matriculaLogada === '') {
    voltarSolicitacaoOrcamentoCoordenador('Sessão sem matrícula. Faça login novamente.');
}
if ($valor <= 0) {
    voltarSolicitacaoOrcamentoCoordenador('Informe um valor válido.');
}
if ($justificativa === '') {
    voltarSolicitacaoOrcamentoCoordenador('Informe a justificativa.');
}

$coordenador = buscarUmaLinha($conn, "SELECT matricula, idtbgerente FROM `{$databaseCorp}`.`tbcoord` WHERE matricula = ? LIMIT 1", 's', [$matriculaLogada]);
if ($coordenador === []) {
    voltarSolicitacaoOrcamentoCoordenador('Cadastro do coordenador não encontrado.');
}
if ((int) ($coordenador['idtbgerente'] ?? 0) <= 0) {
    voltarSolicitacaoOrcamentoCoordenador('Coordenador sem gerente vinculado.');
}

$agora = date('Y-m-d H:i:s');
$insert = consultaPreparada(
    $conn,
    "INSERT INTO `{$databaseName}`.`tbpedidoscoord` (matricula, valor, justificativa, data) VALUES (?, ?, ?, ?)",
    'sdss',
    [$matriculaLogada, $valor, $justificativa, $agora]
);
if (($insert['erro'] ?? '') !== '') {
    voltarSolicitacaoOrcamentoCoordenador('Erro ao registrar pedido: ' . $insert['erro']);
}

consultaPreparada(
    $conn,
    "INSERT INTO `{$databaseName}`.`tblog` (data_e_hora, acao, flag, matricula, mat_autor, valor_ant, valor_novo) VALUES (?, 'Pediu orçamento extra', 2, ?, ?, 0, ?)",
    'sssd',
    [$agora, $matriculaLogada, $matriculaLogada, $valor]
);

voltarSolicitacaoOrcamentoCoordenador('Pedido de orçamento enviado com sucesso.', 'success');
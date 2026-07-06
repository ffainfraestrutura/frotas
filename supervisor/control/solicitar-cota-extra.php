<?php
require_once __DIR__ . '/../../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? ($GLOBALS['conn'] ?? null);
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? ($GLOBALS['databaseName'] ?? 'bdautofrotas'));
$matriculaLogada = (string) ($autofrotaSessao['matricula'] ?? $_SESSION['matricula'] ?? '');

if (!$conn instanceof mysqli) {
    exit('Conexão indisponível.');
}

function voltarSolicitacaoSupervisor(string $mensagem, string $tipo = 'danger'): void
{
    $_SESSION['solicitar_cota_supervisor_mensagem'] = $mensagem;
    $_SESSION['solicitar_cota_supervisor_tipo'] = $tipo;
    header('Location: ../solicitar-cota-extra.php');
    exit;
}

function normalizarMoedaSupervisor(string $valor): float
{
    $valor = str_replace('.', '', trim($valor));
    $valor = str_replace(',', '.', $valor);
    return is_numeric($valor) ? (float) $valor : 0.0;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarSolicitacaoSupervisor('Método inválido.');
}

$tokenPost = (string) ($_POST['token'] ?? '');
$tokenSessao = (string) ($_SESSION['solicitar_cota_supervisor_token'] ?? '');
if ($tokenPost === '' || $tokenSessao === '' || !hash_equals($tokenSessao, $tokenPost)) {
    voltarSolicitacaoSupervisor('Token inválido ou expirado. Atualize a página e tente novamente.');
}

$valor = normalizarMoedaSupervisor((string) ($_POST['valor'] ?? ''));
$justificativa = trim((string) ($_POST['justificativa'] ?? ''));

if ($matriculaLogada === '') {
    voltarSolicitacaoSupervisor('Sessão sem matrícula. Faça login novamente.');
}
if ($valor <= 0) {
    voltarSolicitacaoSupervisor('Informe um valor válido.');
}
if ($justificativa === '') {
    voltarSolicitacaoSupervisor('Informe a justificativa.');
}

$insert = consultaPreparada(
    $conn,
    "INSERT INTO `{$databaseName}`.`tbpedidossup` (matricula, valor, justificativa, data, flag) VALUES (?, ?, ?, ?, 0)",
    'sdss',
    [$matriculaLogada, $valor, $justificativa, date('Y-m-d H:i:s')]
);

if (($insert['erro'] ?? '') !== '') {
    voltarSolicitacaoSupervisor('Erro ao registrar pedido: ' . $insert['erro']);
}

consultaPreparada(
    $conn,
    "INSERT INTO `{$databaseName}`.`tblog` (data_e_hora, acao, flag, matricula, mat_autor, valor_ant, valor_novo) VALUES (?, 'Pediu cota extra supervisor', 2, ?, ?, 0, ?)",
    'sssd',
    [date('Y-m-d H:i:s'), $matriculaLogada, $matriculaLogada, $valor]
);

voltarSolicitacaoSupervisor('Pedido realizado com sucesso.', 'success');
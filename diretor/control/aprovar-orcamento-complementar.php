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

function voltarAprovacaoOrcamentoDiretor(string $mensagem, string $tipo = 'danger'): void
{
    $_SESSION['aprovar_orcamento_diretor_mensagem'] = $mensagem;
    $_SESSION['aprovar_orcamento_diretor_tipo'] = $tipo;
    header('Location: ../aprovar-orcamento-complementar.php');
    exit;
}

if ($perfilLogado !== '10') {
    voltarAprovacaoOrcamentoDiretor('Acesso permitido apenas para perfil diretor.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarAprovacaoOrcamentoDiretor('Método inválido.');
}
if (!hash_equals((string) ($_SESSION['aprovar_orcamento_diretor_token'] ?? ''), (string) ($_POST['token'] ?? ''))) {
    voltarAprovacaoOrcamentoDiretor('Token inválido ou expirado. Atualize a página e tente novamente.');
}

$idPedido = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$decisao = (string) ($_POST['decisao'] ?? '');
if (!$idPedido || !in_array($decisao, ['1', '2'], true)) {
    voltarAprovacaoOrcamentoDiretor('Dados da decisão inválidos.');
}

$diretor = buscarUmaLinha($conn, "SELECT id, valor FROM `{$databaseCorp}`.`tbdiretor` WHERE matricula = ? LIMIT 1", 's', [$matriculaLogada]);
if ($diretor === []) {
    voltarAprovacaoOrcamentoDiretor('Cadastro do diretor não encontrado.');
}

$pedido = buscarUmaLinha(
    $conn,
    "SELECT p.idtbpedidosdiretor, p.matricula, p.valor, g.valor AS saldo_gerente
       FROM `{$databaseName}`.`tbpedidosdiretor` p
    INNER JOIN `{$databaseCorp}`.`tbgerente` g ON g.matricula = p.matricula
            WHERE p.idtbpedidosdiretor = ? AND p.flag = 0 AND g.idtbdiretor = ?
      LIMIT 1",
    'ii',
                [$idPedido, (int) $diretor['id']]
);
if ($pedido === []) {
    voltarAprovacaoOrcamentoDiretor('Pedido pendente não encontrado para este diretor.');
}

$valor = (float) $pedido['valor'];
$saldoDiretor = (float) $diretor['valor'];
$saldoGerenteAntes = (float) ($pedido['saldo_gerente'] ?? 0);
$matriculaGerente = (string) $pedido['matricula'];
$agora = date('Y-m-d H:i:s');

mysqli_begin_transaction($conn);
try {
    if ($decisao === '2') {
        if ($valor > $saldoDiretor) {
            mysqli_rollback($conn);
            voltarAprovacaoOrcamentoDiretor('Saldo insuficiente para aprovação do pedido.');
        }

        $atualizaPedido = consultaPreparada($conn, "UPDATE `{$databaseName}`.`tbpedidosdiretor` SET flag = 2 WHERE idtbpedidosdiretor = ? AND flag = 0", 'i', [$idPedido]);
        if (($atualizaPedido['erro'] ?? '') !== '') {
            throw new RuntimeException($atualizaPedido['erro']);
        }
        $atualizaGerente = consultaPreparada($conn, "UPDATE `{$databaseCorp}`.`tbgerente` SET valor = valor + ?, orcrecebido = orcrecebido + ? WHERE matricula = ?", 'dds', [$valor, $valor, $matriculaGerente]);
        if (($atualizaGerente['erro'] ?? '') !== '') {
            throw new RuntimeException($atualizaGerente['erro']);
        }
        $atualizaDiretor = consultaPreparada($conn, "UPDATE `{$databaseCorp}`.`tbdiretor` SET valor = valor - ? WHERE matricula = ?", 'ds', [$valor, $matriculaLogada]);
        if (($atualizaDiretor['erro'] ?? '') !== '') {
            throw new RuntimeException($atualizaDiretor['erro']);
        }
        consultaPreparada($conn, "INSERT INTO `{$databaseName}`.`tblog` (data_e_hora, acao, flag, matricula, mat_autor, valor_ant, valor_novo) VALUES (?, 'Aceitou orçamento extra gerente', 2, ?, ?, ?, ?)", 'sssdd', [$agora, $matriculaGerente, $matriculaLogada, $saldoGerenteAntes, $valor]);
        mysqli_commit($conn);
        voltarAprovacaoOrcamentoDiretor('Pedido aprovado com sucesso.', 'success');
    }

    $atualizaPedido = consultaPreparada($conn, "UPDATE `{$databaseName}`.`tbpedidosdiretor` SET flag = 1 WHERE idtbpedidosdiretor = ? AND flag = 0", 'i', [$idPedido]);
    if (($atualizaPedido['erro'] ?? '') !== '') {
        throw new RuntimeException($atualizaPedido['erro']);
    }
    consultaPreparada($conn, "INSERT INTO `{$databaseName}`.`tblog` (data_e_hora, acao, flag, matricula, mat_autor, valor_ant, valor_novo) VALUES (?, 'Não aceitou orçamento extra gerente', 2, ?, ?, ?, ?)", 'sssdd', [$agora, $matriculaGerente, $matriculaLogada, $saldoGerenteAntes, $valor]);
    mysqli_commit($conn);
    voltarAprovacaoOrcamentoDiretor('Pedido reprovado com sucesso.', 'success');
} catch (Throwable $e) {
    mysqli_rollback($conn);
    voltarAprovacaoOrcamentoDiretor('Erro ao processar decisão: ' . $e->getMessage());
}
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

function voltarAprovacaoOrcamentoGerente(string $mensagem, string $tipo = 'danger'): void
{
    $_SESSION['aprovar_orcamento_gerente_mensagem'] = $mensagem;
    $_SESSION['aprovar_orcamento_gerente_tipo'] = $tipo;
    header('Location: ../aprovar-orcamento-complementar.php');
    exit;
}

if ($perfilLogado !== '3') {
    voltarAprovacaoOrcamentoGerente('Acesso permitido apenas para perfil gerente.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarAprovacaoOrcamentoGerente('Método inválido.');
}
if (!hash_equals((string) ($_SESSION['aprovar_orcamento_gerente_token'] ?? ''), (string) ($_POST['token'] ?? ''))) {
    voltarAprovacaoOrcamentoGerente('Token inválido ou expirado. Atualize a página e tente novamente.');
}

$idPedido = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$decisao = (string) ($_POST['decisao'] ?? '');
if (!$idPedido || !in_array($decisao, ['1', '2'], true)) {
    voltarAprovacaoOrcamentoGerente('Dados da decisão inválidos.');
}

$gerente = buscarUmaLinha($conn, "SELECT idtbgerente, valor FROM `{$databaseCorp}`.`tbgerente` WHERE matricula = ? LIMIT 1", 's', [$matriculaLogada]);
if ($gerente === []) {
    voltarAprovacaoOrcamentoGerente('Cadastro do gerente não encontrado.');
}

$pedido = buscarUmaLinha(
    $conn,
    "SELECT p.idtbpedidoscoord, p.matricula, p.valor, c.valor AS saldo_coordenador
       FROM `{$databaseName}`.`tbpedidoscoord` p
       INNER JOIN `{$databaseCorp}`.`tbcoord` c ON c.matricula = p.matricula
      WHERE p.idtbpedidoscoord = ?
        AND p.flag = 0
        AND c.idtbgerente = ?
      LIMIT 1",
    'ii',
    [$idPedido, (int) $gerente['idtbgerente']]
);
if ($pedido === []) {
    voltarAprovacaoOrcamentoGerente('Pedido pendente não encontrado para este gerente.');
}

$valor = (float) $pedido['valor'];
$saldoGerente = (float) $gerente['valor'];
$saldoCoordenadorAntes = (float) ($pedido['saldo_coordenador'] ?? 0);
$matriculaCoordenador = (string) $pedido['matricula'];
$agora = date('Y-m-d H:i:s');

mysqli_begin_transaction($conn);
try {
    if ($decisao === '2') {
        if ($valor > $saldoGerente) {
            mysqli_rollback($conn);
            voltarAprovacaoOrcamentoGerente('Saldo insuficiente para aprovação do pedido.');
        }

        $atualizaPedido = consultaPreparada($conn, "UPDATE `{$databaseName}`.`tbpedidoscoord` SET flag = 2 WHERE idtbpedidoscoord = ? AND flag = 0", 'i', [$idPedido]);
        if (($atualizaPedido['erro'] ?? '') !== '') {
            throw new RuntimeException($atualizaPedido['erro']);
        }

        $atualizaCoordenador = consultaPreparada($conn, "UPDATE `{$databaseCorp}`.`tbcoord` SET valor = valor + ?, orcrecebido = orcrecebido + ? WHERE matricula = ?", 'dds', [$valor, $valor, $matriculaCoordenador]);
        if (($atualizaCoordenador['erro'] ?? '') !== '') {
            throw new RuntimeException($atualizaCoordenador['erro']);
        }

        $atualizaGerente = consultaPreparada($conn, "UPDATE `{$databaseCorp}`.`tbgerente` SET valor = valor - ? WHERE matricula = ?", 'ds', [$valor, $matriculaLogada]);
        if (($atualizaGerente['erro'] ?? '') !== '') {
            throw new RuntimeException($atualizaGerente['erro']);
        }

        consultaPreparada(
            $conn,
            "INSERT INTO `{$databaseName}`.`tblog` (data_e_hora, acao, flag, matricula, mat_autor, valor_ant, valor_novo) VALUES (?, 'Aceitou orçamento extra coordenador', 2, ?, ?, ?, ?)",
            'sssdd',
            [$agora, $matriculaCoordenador, $matriculaLogada, $saldoCoordenadorAntes, $valor]
        );
        mysqli_commit($conn);
        voltarAprovacaoOrcamentoGerente('Pedido aprovado com sucesso.', 'success');
    }

    $atualizaPedido = consultaPreparada($conn, "UPDATE `{$databaseName}`.`tbpedidoscoord` SET flag = 1 WHERE idtbpedidoscoord = ? AND flag = 0", 'i', [$idPedido]);
    if (($atualizaPedido['erro'] ?? '') !== '') {
        throw new RuntimeException($atualizaPedido['erro']);
    }

    consultaPreparada(
        $conn,
        "INSERT INTO `{$databaseName}`.`tblog` (data_e_hora, acao, flag, matricula, mat_autor, valor_ant, valor_novo) VALUES (?, 'Não aceitou orçamento extra coordenador', 2, ?, ?, ?, ?)",
        'sssdd',
        [$agora, $matriculaCoordenador, $matriculaLogada, $saldoCoordenadorAntes, $valor]
    );
    mysqli_commit($conn);
    voltarAprovacaoOrcamentoGerente('Pedido reprovado com sucesso.', 'success');
} catch (Throwable $e) {
    mysqli_rollback($conn);
    voltarAprovacaoOrcamentoGerente('Erro ao processar decisão: ' . $e->getMessage());
}
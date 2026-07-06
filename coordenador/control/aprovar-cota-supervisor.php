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

function voltarAprovacaoCotaSupervisor(string $mensagem, string $tipo = 'danger'): void
{
    $_SESSION['aprovacao_cota_supervisor_mensagem'] = $mensagem;
    $_SESSION['aprovacao_cota_supervisor_tipo'] = $tipo;
    header('Location: ../aprovacao-cotas-supervisor.php');
    exit;
}

function normalizarMoedaAprovacaoSupervisor(string $valor): float
{
    $valor = str_replace('.', '', trim($valor));
    $valor = str_replace(',', '.', $valor);
    return is_numeric($valor) ? (float) $valor : 0.0;
}

function colunaSupervisorExiste(mysqli $conn, string $databaseName, string $tabela, string $coluna): bool
{
    return buscarUmaLinha($conn, 'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1', 'sss', [$databaseName, $tabela, $coluna]) !== [];
}

function registrarLogSupervisor(mysqli $conn, string $databaseName, int $idPedido, int $decisao, string $matriculaSupervisor, string $matriculaAutor, float $valorAnterior, float $valorNovo): void
{
    $acao = ($decisao === 2 ? 'Aceitou cota extra supervisor' : 'Não aceitou cota extra supervisor') . ' #' . $idPedido;
    consultaPreparada(
        $conn,
        "INSERT INTO `{$databaseName}`.`tblog` (data_e_hora, acao, flag, matricula, mat_autor, valor_ant, valor_novo) VALUES (?, ?, ?, ?, ?, ?, ?)",
        'ssissdd',
        [date('Y-m-d H:i:s'), $acao, $decisao, $matriculaSupervisor, $matriculaAutor, $valorAnterior, $valorNovo]
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarAprovacaoCotaSupervisor('Método inválido.');
}

$tokenPost = (string) ($_POST['token'] ?? '');
$tokenSessao = (string) ($_SESSION['aprovacao_cota_supervisor_token'] ?? '');
if ($tokenPost === '' || $tokenSessao === '' || !hash_equals($tokenSessao, $tokenPost)) {
    voltarAprovacaoCotaSupervisor('Token inválido ou expirado. Atualize a página e tente novamente.');
}

$idPedido = (int) ($_POST['idtbpedidossupcota'] ?? 0);
$decisao = (int) ($_POST['decisao'] ?? 0);
$valorInseridoRaw = trim((string) ($_POST['valorinserido'] ?? ''));
$valorInserido = normalizarMoedaAprovacaoSupervisor($valorInseridoRaw);

if ($idPedido <= 0 || !in_array($decisao, [1, 2], true)) {
    voltarAprovacaoCotaSupervisor('Pedido ou decisão inválida.');
}
if ($decisao === 2 && $valorInseridoRaw !== '' && $valorInserido <= 0) {
    voltarAprovacaoCotaSupervisor('Informe um valor aprovado válido.');
}

$whereEscopo = '';
$tipos = 'i';
$params = [$idPedido];
if ($perfilLogado === '2') {
    $whereEscopo = ' AND c.matricula = ?';
    $tipos .= 's';
    $params[] = $matriculaLogada;
} elseif (!in_array($perfilLogado, ['3', '4', '5'], true)) {
    $whereEscopo = ' AND 1 = 0';
}

$pedido = buscarUmaLinha(
    $conn,
    "SELECT p.*, s.saldo AS saldo_supervisor, s.totcotaextra, c.matricula AS matricula_coordenador, c.valor AS saldo_coordenador
       FROM `{$databaseName}`.`tbpedidossup` p
       INNER JOIN `{$databaseCorp}`.`tbsupervisor` s ON s.matricula = p.matricula
       LEFT JOIN `{$databaseCorp}`.`tbcoord` c ON c.idtbcoordenador = s.idtbcoordenador
      WHERE p.idtbpedidossupcota = ?
        AND p.flag = 0
        {$whereEscopo}
      LIMIT 1",
    $tipos,
    $params
);

if ($pedido === []) {
    voltarAprovacaoCotaSupervisor('Pedido não encontrado, sem permissão ou já processado.');
}

$matriculaSupervisor = (string) $pedido['matricula'];
$valorPedido = (float) ($pedido['valor'] ?? 0);
if ($valorInseridoRaw === '') {
    $valorInserido = $valorPedido;
}

if ($decisao === 1) {
    $atualizacaoReprovacao = consultaPreparada(
        $conn,
        "UPDATE `{$databaseName}`.`tbpedidossup` SET flag = 1, tipocota = 0, dataplantao = NULL WHERE idtbpedidossupcota = ?",
        'i',
        [$idPedido]
    );
    if (($atualizacaoReprovacao['erro'] ?? '') !== '') {
        voltarAprovacaoCotaSupervisor('Erro ao reprovar pedido: ' . $atualizacaoReprovacao['erro']);
    }
    registrarLogSupervisor($conn, $databaseName, $idPedido, $decisao, $matriculaSupervisor, $matriculaLogada, $valorPedido, 0.0);
    voltarAprovacaoCotaSupervisor('Pedido reprovado com sucesso.', 'success');
}

if ($valorInserido <= 0) {
    voltarAprovacaoCotaSupervisor('Informe um valor aprovado válido.');
}

$saldoCoordenador = is_numeric($pedido['saldo_coordenador'] ?? null) ? (float) $pedido['saldo_coordenador'] : 0.0;
if ($valorInserido > $saldoCoordenador) {
    voltarAprovacaoCotaSupervisor('Saldo insuficiente.', 'warning');
}

$agora = date('Y-m-d H:i:s');

$temValorInseridoPedido = colunaSupervisorExiste($conn, $databaseName, 'tbpedidossup', 'valorinserido');
if ($temValorInseridoPedido) {
    $atualizacaoAprovacao = consultaPreparada(
        $conn,
        "UPDATE `{$databaseName}`.`tbpedidossup` SET flag = 2, tipocota = 0, dataplantao = NULL, valorinserido = ? WHERE idtbpedidossupcota = ?",
        'di',
        [$valorInserido, $idPedido]
    );
} else {
    $atualizacaoAprovacao = consultaPreparada(
        $conn,
        "UPDATE `{$databaseName}`.`tbpedidossup` SET flag = 2, tipocota = 0, dataplantao = NULL WHERE idtbpedidossupcota = ?",
        'i',
        [$idPedido]
    );
}

if (($atualizacaoAprovacao['erro'] ?? '') !== '') {
    voltarAprovacaoCotaSupervisor('Erro ao aprovar pedido: ' . $atualizacaoAprovacao['erro']);
}

$temIdPedidoCota = colunaSupervisorExiste($conn, $databaseName, 'tbcotaextraac', 'idtbpedidossupcota');
if ($temIdPedidoCota) {
    $insertCota = consultaPreparada(
        $conn,
        "INSERT INTO `{$databaseName}`.`tbcotaextraac` (matricula, matsup, valor, justificativa, data, flag, dataaceite, valorinserido, idtbpedidossupcota)
         VALUES (?, ?, ?, ?, ?, 2, ?, ?, ?)",
        'ssdsssdi',
        [$matriculaSupervisor, $matriculaLogada, $valorPedido, (string) $pedido['justificativa'], (string) $pedido['data'], $agora, $valorInserido, $idPedido]
    );
} else {
    $insertCota = consultaPreparada(
        $conn,
        "INSERT INTO `{$databaseName}`.`tbcotaextraac` (matricula, matsup, valor, justificativa, data, flag, dataaceite, valorinserido)
         VALUES (?, ?, ?, ?, ?, 2, ?, ?)",
        'ssdsssd',
        [$matriculaSupervisor, $matriculaLogada, $valorPedido, (string) $pedido['justificativa'], (string) $pedido['data'], $agora, $valorInserido]
    );
}

if (($insertCota['erro'] ?? '') !== '') {
    if ($temValorInseridoPedido) {
        consultaPreparada($conn, "UPDATE `{$databaseName}`.`tbpedidossup` SET flag = 0, valorinserido = 0 WHERE idtbpedidossupcota = ?", 'i', [$idPedido]);
    } else {
        consultaPreparada($conn, "UPDATE `{$databaseName}`.`tbpedidossup` SET flag = 0 WHERE idtbpedidossupcota = ?", 'i', [$idPedido]);
    }
    voltarAprovacaoCotaSupervisor('Erro ao registrar cota aprovada: ' . $insertCota['erro']);
}

consultaPreparada(
    $conn,
    "UPDATE `{$databaseCorp}`.`tbsupervisor` SET saldo = COALESCE(saldo, 0) + ?, totcotaextra = COALESCE(totcotaextra, 0) + ? WHERE matricula = ? LIMIT 1",
    'dds',
    [$valorInserido, $valorInserido, $matriculaSupervisor]
);

consultaPreparada(
    $conn,
    "UPDATE `{$databaseCorp}`.`tbcoord` SET valor = valor - ? WHERE matricula = ? LIMIT 1",
    'ds',
    [$valorInserido, $matriculaLogada]
);

registrarLogSupervisor($conn, $databaseName, $idPedido, $decisao, $matriculaSupervisor, $matriculaLogada, $valorPedido, $valorInserido);
voltarAprovacaoCotaSupervisor('Pedido aprovado com sucesso.', 'success');
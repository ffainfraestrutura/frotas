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

if ($perfilLogado !== '3') {
    voltarAprovacaoCotaGerente('Acesso permitido apenas para perfil gerente.');
}

function voltarAprovacaoCotaGerente(string $mensagem, string $tipo = 'danger'): void
{
    $_SESSION['aprovacao_cota_mensagem'] = $mensagem;
    $_SESSION['aprovacao_cota_tipo'] = $tipo;
    header('Location: ../aprovacao-cotas.php');
    exit;
}

function normalizarMoedaAprovacao(string $valor): float
{
    $valor = str_replace(',', '.', trim($valor));
    return is_numeric($valor) ? (float) $valor : 0.0;
}


function saldoAprovadorCota(mysqli $conn, string $databaseCorp, string $tabela, string $matricula): ?float
{
    $linha = buscarUmaLinha(
        $conn,
        "SELECT valor FROM `{$databaseCorp}`.`{$tabela}` WHERE matricula = ? LIMIT 1",
        's',
        [$matricula]
    );

    if ($linha === [] || !is_numeric($linha['valor'] ?? null)) {
        return null;
    }

    return (float) $linha['valor'];
}


function registrarLogAprovacaoCota(mysqli $conn, string $databaseName, int $idPedido, int $decisao, string $matriculaTecnico, string $matriculaAutor, float $valorAnterior, float $valorNovo): void
{
    $acoes = [
        1 => 'Reprovou pedido de cota',
        2 => 'Aprovou pedido de cota',
        3 => 'Escalonou pedido de cota',
    ];
    $acao = ($acoes[$decisao] ?? 'Processou pedido de cota') . ' #' . $idPedido;
    consultaPreparada(
        $conn,
        "INSERT INTO `{$databaseName}`.`tblog` (data_e_hora, acao, flag, matricula, mat_autor, valor_ant, valor_novo) VALUES (?, ?, ?, ?, ?, ?, ?)",
        'ssissdd',
        [date('Y-m-d H:i:s'), $acao, $decisao, $matriculaTecnico, $matriculaAutor, $valorAnterior, $valorNovo]
    );
}

function tabelaAprovacaoExiste(mysqli $conn, string $databaseName, string $tabela): bool
{
    return buscarUmaLinha($conn, 'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1', 'ss', [$databaseName, $tabela]) !== [];
}

function colunaAprovacaoExiste(mysqli $conn, string $databaseName, string $tabela, string $coluna): bool
{
    return buscarUmaLinha($conn, 'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1', 'sss', [$databaseName, $tabela, $coluna]) !== [];
}

function primeiraTabelaAprovacao(mysqli $conn, string $databaseName, array $tabelas): string
{
    foreach ($tabelas as $tabela) {
        if (tabelaAprovacaoExiste($conn, $databaseName, $tabela)) {
            return $tabela;
        }
    }

    return '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarAprovacaoCotaGerente('Método inválido.');
}

$tokenPost = (string) ($_POST['token'] ?? '');
$tokenSessao = (string) ($_SESSION['aprovacao_cota_token'] ?? '');
if ($tokenPost === '' || $tokenSessao === '' || !hash_equals($tokenSessao, $tokenPost)) {
    voltarAprovacaoCotaGerente('Token inválido ou expirado. Atualize a página e tente novamente.');
}

$idPedido = (int) ($_POST['idtbpedidostec'] ?? 0);
$decisao = (int) ($_POST['decisao'] ?? 0);
$valorInseridoRaw = trim((string) ($_POST['valorinserido'] ?? ''));
$valorInserido = normalizarMoedaAprovacao($valorInseridoRaw);

if ($idPedido <= 0 || !in_array($decisao, [1, 2], true)) {
    voltarAprovacaoCotaGerente('Pedido ou decisão inválida.');
}
if ($decisao === 2 && $valorInseridoRaw !== '' && $valorInserido <= 0) {
    voltarAprovacaoCotaGerente('Informe um valor aprovado válido.');
}

$supervisorTabela = 'tbsupervisor';
$coordenadorTabela = 'tbcoord';
if ($supervisorTabela === '') {
    voltarAprovacaoCotaGerente('Tabela de supervisor não encontrada.');
}

$joinCoordenador = $coordenadorTabela !== '' ? "LEFT JOIN `{$databaseCorp}`.`{$coordenadorTabela}` c ON c.idtbcoordenador = s.idtbcoordenador" : '';
$whereEscopo = '';
$tipos = 'ii';
$params = [$idPedido, 1];

$pedido = buscarUmaLinha(
    $conn,
    "SELECT p.*
       FROM `{$databaseName}`.`tbpedidostec` p
       INNER JOIN `{$databaseCorp}`.`tbusuario` u ON u.matricula = p.matricula
       LEFT JOIN `{$databaseCorp}`.`{$supervisorTabela}` s ON s.idtbsupervisor = u.idtbsupervisor
       {$joinCoordenador}
      WHERE p.idtbpedidostec = ?
        AND p.flag = 0
        AND p.escalonado = ?
        AND p.desctec IS NULL
        {$whereEscopo}
      LIMIT 1",
    $tipos,
    $params
);

if ($pedido === []) {
    voltarAprovacaoCotaGerente('Pedido não encontrado, sem permissão ou já processado.');
}

if ($valorInseridoRaw === '') {
    $valorInserido = (float) ($pedido['valor'] ?? 0);
}
if ($decisao === 2 && $valorInserido <= 0) {
    voltarAprovacaoCotaGerente('Informe um valor aprovado válido.');
}

$saldoAprovador = saldoAprovadorCota($conn, $databaseCorp, 'tbgerente', $matriculaLogada);
if ($decisao === 2 && $valorInserido > ($saldoAprovador ?? 0.0)) {
    voltarAprovacaoCotaGerente('Saldo insuficiente.', 'warning');
}

$agora = date('Y-m-d H:i:s');
$tipocota = (string) ($pedido['tipocota'] ?? 'extra');
if ($tipocota === '') {
    $tipocota = 'extra';
}

if ($decisao === 1) {
    consultaPreparada(
        $conn,
        "UPDATE `{$databaseName}`.`tbpedidostec` SET flag = 1, tipocota = ?, dataplantao = NULL WHERE idtbpedidostec = ? AND flag = 0",
        'si',
        [$tipocota, $idPedido]
    );
    registrarLogAprovacaoCota($conn, $databaseName, $idPedido, $decisao, (string) $pedido['matricula'], $matriculaLogada, (float) ($pedido['valor'] ?? 0), 0.0);
    voltarAprovacaoCotaGerente('Pedido reprovado com sucesso.', 'success');
}

consultaPreparada(
    $conn,
    "UPDATE `{$databaseName}`.`tbpedidostec` SET flag = 2, tipocota = ?, dataplantao = NULL, valorinserido = ? WHERE idtbpedidostec = ? AND flag = 0",
    'sdi',
    [$tipocota, $valorInserido, $idPedido]
);

$insertCota = consultaPreparada(
    $conn,
    "INSERT INTO `{$databaseName}`.`tbcotaextraac`
        (matricula, matsup, valor, justificativa, data, flag, dataaceite, dir, valorinserido, kmhodometro, orcsemanal, kmproj, kmos, gps, valordescontado, sldcartao, totalextra, cota_validade, idtbpedidostec)
     VALUES (?, ?, ?, ?, ?, 2, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)",
    'ssdssssdsdddddddi',
    [
        (string) $pedido['matricula'],
        $matriculaLogada,
        (float) $pedido['valor'],
        (string) $pedido['justificativa'],
        (string) $pedido['data'],
        $agora,
        (string) $pedido['dir'],
        $valorInserido,
        (string) $pedido['kmhodometro'],
        (float) $pedido['orcsemanal'],
        (float) $pedido['kmproj'],
        (float) $pedido['kmos'],
        (float) $pedido['gps'],
        (float) $pedido['valordescontado'],
        (float) $pedido['sldcartao'],
        (float) $pedido['totalextra'],
        $idPedido,
    ]
);

$sqlHistorico = "INSERT INTO historico_combustivel 
                    (valor, matricula, operacao, matricula_autor, valor_anterior, valor_atual, acao, data) 
                  VALUES (?, ?, 'adição', ?, ?, ?, 'cota extra', NOW())";

$novoSaldo = $pedido['sldcartao'] + $valorInserido;

$stmtHistorico = mysqli_prepare($conn, $sqlHistorico);
mysqli_stmt_bind_param($stmtHistorico, 'dssdd', $valorInserido, $pedido['matricula'], $matriculaLogada, $pedido['sldcartao'], $novoSaldo);
mysqli_stmt_execute($stmtHistorico);
mysqli_stmt_close($stmtHistorico);

$sql_gerente = "SELECT * FROM bdcorp.tbgerente WHERE matricula = ?";
$stmt = mysqli_prepare($conn, $sql_gerente);
mysqli_stmt_bind_param($stmt, 's', $matriculaLogada);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$dados_gerente = mysqli_fetch_assoc($result); 
mysqli_stmt_close($stmt);


$sqlHistorico = "INSERT INTO historico_combustivel 
                    (valor, matricula, operacao, matricula_autor, valor_anterior, valor_atual, acao, data) 
                  VALUES (?, ?, 'retirada', ?, ?, ?, 'cota extra escalonada', NOW())";

$novoSaldo = $dados_gerente['valor'] - $valorInserido;

$stmtHistorico = mysqli_prepare($conn, $sqlHistorico);
mysqli_stmt_bind_param($stmtHistorico, 'dssdd', $valorInserido, $matriculaLogada, $matriculaLogada, $dados_gerente['valor'], $novoSaldo);
mysqli_stmt_execute($stmtHistorico);
mysqli_stmt_close($stmtHistorico);

if (($insertCota['erro'] ?? '') !== '') {
    consultaPreparada($conn, "UPDATE `{$databaseName}`.`tbpedidostec` SET flag = 0, valorinserido = 0 WHERE idtbpedidostec = ?", 'i', [$idPedido]);
    voltarAprovacaoCotaGerente('Erro ao registrar cota aprovada: ' . $insertCota['erro']);
}

$matriculaTecnico = (string) $pedido['matricula'];
$saldoTecnico = buscarUmaLinha(
    $conn,
    "SELECT COALESCE(saldo, saldo_real_calculado, 0) AS saldo_anterior
       FROM `{$databaseName}`.`tbsaldo`
      WHERE matricula = ?
      ORDER BY data DESC
      LIMIT 1",
    's',
    [$matriculaTecnico]
);
$saldoAnteriorTecnico = is_numeric($saldoTecnico['saldo_anterior'] ?? null) ? (float) $saldoTecnico['saldo_anterior'] : 0.0;

consultaPreparada(
    $conn,
    "UPDATE `{$databaseName}`.`tbsaldo`
        SET saldo = COALESCE(saldo, 0) + ?,
            valoraplicado = COALESCE(valoraplicado, 0) + ?,
            totalextra = COALESCE(totalextra, 0) + ?
      WHERE matricula = ?
      ORDER BY data DESC
      LIMIT 1",
    'ddds',
    [$valorInserido, $valorInserido, $valorInserido, $matriculaTecnico]
);

if (colunaAprovacaoExiste($conn, $databaseName, 'tbsaldo', 'kmorcsem')) {
    consultaPreparada(
        $conn,
        "UPDATE `{$databaseName}`.`tbsaldo`
            SET kmproj = ROUND((kmorcsem / NULLIF(orcsemanal, 0)) * valoraplicado, 2)
          WHERE matricula = ?
          ORDER BY data DESC
          LIMIT 1",
        's',
        [$matriculaTecnico]
    );
}


$atualizaSaldoGerente = consultaPreparada(
    $conn,
    "UPDATE `{$databaseCorp}`.`tbgerente`
        SET valor = valor - ?
      WHERE matricula = ?
      LIMIT 1",
    'ds',
    [$valorInserido, $matriculaLogada]
);

if (($atualizaSaldoGerente['erro'] ?? '') !== '') {
    voltarAprovacaoCotaGerente('Erro ao atualizar saldo do gerente: ' . $atualizaSaldoGerente['erro']);
}

registrarLogAprovacaoCota($conn, $databaseName, $idPedido, $decisao, (string) $pedido['matricula'], $matriculaLogada, (float) ($pedido['valor'] ?? 0), $valorInserido);

voltarAprovacaoCotaGerente('Pedido aprovado com sucesso.', 'success');
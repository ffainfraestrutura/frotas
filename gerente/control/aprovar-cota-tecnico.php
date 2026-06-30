<?php
require_once __DIR__ . '/../../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? ($GLOBALS['conn'] ?? null);
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? ($GLOBALS['databaseName'] ?? 'bdautofrotas'));
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
    $valor = str_replace('.', '', trim($valor));
    $valor = str_replace(',', '.', $valor);
    return is_numeric($valor) ? (float) $valor : 0.0;
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
$valorInserido = normalizarMoedaAprovacao((string) ($_POST['valorinserido'] ?? '0'));

if ($idPedido <= 0 || !in_array($decisao, [1, 2], true)) {
    voltarAprovacaoCotaGerente('Pedido ou decisão inválida.');
}
if ($decisao === 2 && $valorInserido <= 0) {
    voltarAprovacaoCotaGerente('Informe um valor aprovado válido.');
}

$supervisorTabela = primeiraTabelaAprovacao($conn, $databaseName, ['tbequipe_supervisor', 'tbsupervisor']);
$coordenadorTabela = primeiraTabelaAprovacao($conn, $databaseName, ['tbequipe_coordenador', 'tbcoordenador']);
if ($supervisorTabela === '') {
    voltarAprovacaoCotaGerente('Tabela de supervisor não encontrada.');
}

$joinCoordenador = $coordenadorTabela !== '' ? "LEFT JOIN `{$databaseName}`.`{$coordenadorTabela}` c ON c.idtbcoordenador = s.idtbcoordenador" : '';
$whereEscopo = '';
$tipos = 'ii';
$params = [$idPedido, 1];

$pedido = buscarUmaLinha(
    $conn,
    "SELECT p.*
       FROM `{$databaseName}`.`tbpedidostec` p
       INNER JOIN `{$databaseName}`.`tbusuario` u ON u.matricula = p.matricula
       LEFT JOIN `{$databaseName}`.`{$supervisorTabela}` s ON s.idtbsupervisor = u.idtbsupervisor
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

if (($insertCota['erro'] ?? '') !== '') {
    consultaPreparada($conn, "UPDATE `{$databaseName}`.`tbpedidostec` SET flag = 0, valorinserido = 0 WHERE idtbpedidostec = ?", 'i', [$idPedido]);
    voltarAprovacaoCotaGerente('Erro ao registrar cota aprovada: ' . $insertCota['erro']);
}

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
    [$valorInserido, $valorInserido, $valorInserido, (string) $pedido['matricula']]
);

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

$historicoCota = consultaPreparada(
    $conn,
    "INSERT INTO `{$databaseName}`.`historico_combustivel`
        (matricula, valor, operacao, matricula_autor, valor_anterior, valor_atual, acao, data)
     VALUES (?, ?, 'adicao', ?, ?, ?, 'cota_extra', ?)",
    'sdsdds',
    [$matriculaTecnico, $valorInserido, $matriculaLogada, $saldoAnteriorTecnico, $saldoAnteriorTecnico + $valorInserido, $agora]
);
if (($historicoCota['erro'] ?? '') !== '') {
    voltarAprovacaoCotaGerente('Erro ao registrar histórico da cota aprovada: ' . $historicoCota['erro']);
}

voltarAprovacaoCotaGerente('Pedido aprovado com sucesso.', 'success');
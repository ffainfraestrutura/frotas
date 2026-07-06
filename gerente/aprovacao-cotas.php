<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

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
    http_response_code(403);
    exit('Acesso permitido apenas para perfil gerente.');
}

function escCota($valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

function moedaCota($valor): string
{
    return number_format((float) ($valor ?? 0), 2, ',', '.');
}

function tabelaAutofrotaExiste(mysqli $conn, string $databaseName, string $tabela): bool
{
    return buscarUmaLinha(
        $conn,
        'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1',
        'ss',
        [$databaseName, $tabela]
    ) !== [];
}

function primeiraTabelaAutofrota(mysqli $conn, string $databaseName, array $tabelas): string
{
    foreach ($tabelas as $tabela) {
        if (tabelaAutofrotaExiste($conn, $databaseName, $tabela)) {
            return $tabela;
        }
    }

    return '';
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

$supervisorTabela = 'tbsupervisor';
$coordenadorTabela = 'tbcoord';
$mensagem = (string) ($_SESSION['aprovacao_cota_mensagem'] ?? '');
$tipoMensagem = (string) ($_SESSION['aprovacao_cota_tipo'] ?? 'info');
unset($_SESSION['aprovacao_cota_mensagem'], $_SESSION['aprovacao_cota_tipo']);

$_SESSION['aprovacao_cota_token'] = bin2hex(random_bytes(32));
$token = $_SESSION['aprovacao_cota_token'];
$saldoAprovador = saldoAprovadorCota($conn, $databaseCorp, 'tbgerente', $matriculaLogada);

$pedidos = [];
$erroTela = '';

if ($supervisorTabela === '') {
    $erroTela = 'Tabela de supervisor não encontrada.';
} else {
    $joinCoordenador = $coordenadorTabela !== '' ? "LEFT JOIN `{$databaseCorp}`.`{$coordenadorTabela}` c ON c.idtbcoordenador = s.idtbcoordenador LEFT JOIN `{$databaseCorp}`.`tbusuario` uc ON uc.matricula = c.matricula" : "LEFT JOIN `{$databaseCorp}`.`tbusuario` uc ON 1 = 0";
    $whereEscopo = '';
    $tipos = '';
    $params = [];

    $consulta = consultaPreparada(
        $conn,
        "SELECT
                p.idtbpedidostec,
                p.matricula,
                COALESCE(u.nome, p.matricula) AS nome_tecnico,
                p.placa,
                p.valor,
                p.valorinserido,
                p.justificativa,
                p.data,
                p.dir,
                p.kmhodometro,
                p.orcsemanal,
                p.kmproj,
                p.kmos,
                p.gps,
                p.valordescontado,
                p.sldcartao,
                p.totalextra,
                us.nome AS nome_supervisor,
                uc.nome AS nome_coordenador
           FROM `{$databaseName}`.`tbpedidostec` p
           INNER JOIN `{$databaseCorp}`.`tbusuario` u ON u.matricula = p.matricula
           LEFT JOIN `{$databaseCorp}`.`{$supervisorTabela}` s ON s.idtbsupervisor = u.idtbsupervisor
           LEFT JOIN `{$databaseCorp}`.`tbusuario` us ON us.matricula = s.matricula
           {$joinCoordenador}
          WHERE p.flag = 0
            AND p.escalonado = 1
            AND p.desctec IS NULL
            {$whereEscopo}
          ORDER BY p.data DESC",
        $tipos,
        $params
    );

    if (($consulta['erro'] ?? '') !== '') {
        $erroTela = $consulta['erro'];
    } else {
        $pedidos = $consulta['linhas'] ?? [];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aprovar Cotar Escalonada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body { background:#f3f6fc; font-size:14px; }
        #layoutSidenav_content { padding: 14px 12px 0; }
        .page-wrapper { max-width: 1440px; margin: 0 auto; }
        .panel-card { border: 1px solid #e5e7eb; border-radius: 14px; box-shadow: 0 10px 28px rgba(15, 23, 42, .06); }
        .table td, .table th { vertical-align: middle; }
        .pedidos-table { min-width: 1560px; }
        .pedidos-table th, .pedidos-table td { padding: .65rem .75rem; white-space: nowrap; }
        .pedidos-table th:nth-child(1), .pedidos-table td:nth-child(1) { min-width: 170px; white-space: normal; }
        .pedidos-table th:nth-child(2), .pedidos-table td:nth-child(2) { min-width: 95px; }
        .pedidos-table th:nth-child(3), .pedidos-table td:nth-child(3) { min-width: 85px; }
        .pedidos-table th:nth-child(4), .pedidos-table td:nth-child(4) { min-width: 130px; }
        .pedidos-table th:nth-child(5), .pedidos-table td:nth-child(5) { min-width: 95px; }
        .pedidos-table th:nth-child(6), .pedidos-table td:nth-child(6) { min-width: 70px; text-align: center; }
        .pedidos-table th:nth-child(14), .pedidos-table td:nth-child(14) { min-width: 200px; }
        .pedidos-table tbody tr > td { transition: background-color .2s ease; }
        .pedidos-table tbody tr:hover > td {
            background-color: #e7f0ff !important;
        }
        .justificativa { min-width: 220px; max-width: 360px; white-space: normal; }
    </style>
</head>
<body class="sb-nav-fixed">
<?php autofrotaMenu(); ?>
<div id="layoutSidenav_content">
    <main class="page-wrapper py-2">
        <section class="card panel-card mb-4">
            <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <h1 class="h3 mb-1">Aprovação de pedidos de cota escalonados</h1>
                </div>
                </div>
            </div>
        </section>

        <?php if ($mensagem !== ''): ?>
            <div class="alert alert-<?= escCota($tipoMensagem) ?> alert-dismissible fade show" role="alert">
                <?= escCota($mensagem) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        <?php endif; ?>

        <?php if ($erroTela !== ''): ?>
            <div class="alert alert-danger"><?= escCota($erroTela) ?></div>
        <?php endif; ?>


        <section class="card panel-card mb-4">
            <div class="card-body py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div class="fw-semibold"><i class="fas fa-wallet me-2"></i>Saldo</div>
                <div class="fs-5 fw-bold text-primary">
                    <?php if ($saldoAprovador === null): ?>
                        Não encontrado
                    <?php else: ?>
                        R$ <?= escCota(moedaCota($saldoAprovador)) ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="card panel-card">
            <div class="card-header bg-white fw-semibold"><i class="fas fa-check-double me-2"></i>Pedidos escalonados pendentes</div>
            <div class="card-body table-responsive">
                <table class="table table-hover align-middle pedidos-table">
                    <thead>
                    <tr>
                        <th>Técnico</th>
                        <th>Matrícula</th>
                        <th>Placa</th>
                        <th>Data</th>
                        <th>Hodômetro</th>
                        <th>Foto</th>
                        <th>Orç. semanal</th>
                        <th>KM proj.</th>
                        <th>KM OS</th>
                        <th>Saldo cartão</th>
                        <th>Total extra</th>
                        <th>Valor pedido</th>
                        <th>Justificativa</th>
                        <th>Histórico</th>
                        <th>Ação</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($pedidos === []): ?>
                        <tr><td colspan="14" class="text-muted">Nenhum pedido escalonado pendente encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pedidos as $pedido): ?>
                            <tr>
                                <td><strong><?= escCota($pedido['nome_tecnico']) ?></strong></td>
                                <td><?= escCota($pedido['matricula']) ?></td>
                                <td><?= escCota($pedido['placa']) ?></td>
                                <td><?= escCota(formatarDataPortal($pedido['data'] ?? '')) ?></td>
                                <td><?= escCota($pedido['kmhodometro']) ?></td>
                                <td><?php if (!empty($pedido['dir'])): ?><a class="btn btn-outline-secondary btn-sm" href=".<?= escCota($pedido['dir']) ?>" target="_blank"><i class="fas fa-eye"></i></a><?php endif; ?></td>
                                <td>R$ <?= escCota(moedaCota($pedido['orcsemanal'])) ?></td>
                                <td><?= escCota(number_format((float) $pedido['kmproj'], 0, ',', '.')) ?></td>
                                <td><?= escCota(number_format((float) $pedido['kmos'], 0, ',', '.')) ?></td>
                                <td>R$ <?= escCota(moedaCota($pedido['sldcartao'])) ?></td>
                                <td>R$ <?= escCota(moedaCota($pedido['totalextra'])) ?></td>
                                <td>R$ <?= escCota(moedaCota($pedido['valor'])) ?></td>
                                <td class="justificativa"><?= escCota($pedido['justificativa']) ?></td>
                                <td>
                                    <a href="../combustivel/historico_combustivel.php?matricula=<?= $pedido['matricula'] ?>">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-2" style="min-width:180px">
                                        <form action="control/aprovar-cota-tecnico.php" method="post" class="d-flex flex-column gap-2">
                                            <input type="hidden" name="token" value="<?= escCota($token) ?>">
                                            <input type="hidden" name="idtbpedidostec" value="<?= escCota($pedido['idtbpedidostec']) ?>">
                                            <input type="text" name="valorinserido" class="form-control form-control-sm" value="<?= escCota(moedaCota($pedido['valor'])) ?>" aria-label="Valor aprovado">
                                            <div class="d-flex gap-1">
                                                <button type="submit" name="decisao" value="2" class="btn btn-success btn-sm flex-fill" title="Aprovar" aria-label="Aprovar"><i class="fas fa-check"></i></button>
                                                <button type="submit" name="decisao" value="1" class="btn btn-outline-danger btn-sm flex-fill" title="Reprovar" aria-label="Reprovar"><i class="fas fa-times"></i></button>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
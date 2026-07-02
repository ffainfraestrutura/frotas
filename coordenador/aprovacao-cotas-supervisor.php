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

function moedaCotaSupervisor($valor): string
{
    return number_format((float) ($valor ?? 0), 2, ',', '.');
}

function saldoCoordenadorSupervisor(mysqli $conn, string $databaseCorp, string $matricula): ?float
{
    $linha = buscarUmaLinha($conn, "SELECT valor FROM `{$databaseCorp}`.`tbcoord` WHERE matricula = ? LIMIT 1", 's', [$matricula]);
    return is_numeric($linha['valor'] ?? null) ? (float) $linha['valor'] : null;
}

$mensagem = (string) ($_SESSION['aprovacao_cota_supervisor_mensagem'] ?? '');
$tipoMensagem = (string) ($_SESSION['aprovacao_cota_supervisor_tipo'] ?? 'info');
unset($_SESSION['aprovacao_cota_supervisor_mensagem'], $_SESSION['aprovacao_cota_supervisor_tipo']);

$_SESSION['aprovacao_cota_supervisor_token'] = bin2hex(random_bytes(32));
$token = $_SESSION['aprovacao_cota_supervisor_token'];
$saldoAprovador = saldoCoordenadorSupervisor($conn, $databaseCorp, $matriculaLogada);

$whereEscopo = '';
$tipos = '';
$params = [];
if ($perfilLogado === '2') {
    $whereEscopo = ' AND c.matricula = ?';
    $tipos = 's';
    $params[] = $matriculaLogada;
} elseif (!in_array($perfilLogado, ['3', '4', '5'], true)) {
    $whereEscopo = ' AND 1 = 0';
}

$consulta = consultaPreparada(
    $conn,
    "SELECT
            p.idtbpedidossupcota,
            p.matricula,
            COALESCE(u.nome, p.matricula) AS nome_supervisor,
            p.valor,
            p.justificativa,
            p.data,
            s.saldo AS saldo_supervisor,
            s.totcotaextra,
            c.matricula AS matricula_coordenador,
            uc.nome AS nome_coordenador,
            v.placa
       FROM `{$databaseName}`.`tbpedidossup` p
       INNER JOIN `{$databaseCorp}`.`tbusuario` u ON u.matricula = p.matricula
       LEFT JOIN `{$databaseCorp}`.`tbsupervisor` s ON s.matricula = p.matricula
       LEFT JOIN `{$databaseCorp}`.`tbcoord` c ON c.idtbcoordenador = s.idtbcoordenador
       LEFT JOIN `{$databaseCorp}`.`tbusuario` uc ON uc.matricula = c.matricula
       LEFT JOIN `{$databaseName}`.`tbveiculo` v ON v.matcond = p.matricula
      WHERE p.flag = 0
        {$whereEscopo}
      ORDER BY p.data DESC",
    $tipos,
    $params
);
$erroTela = (string) ($consulta['erro'] ?? '');
$pedidos = $consulta['linhas'] ?? [];

renderCabecalhoAutofrota('Aprovação de Cotas de Supervisores');
?>
<div class="container-fluid px-4">
    <section class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <h1 class="h3 mb-1">Aprovação de pedidos de cota de supervisores</h1>
            </div>
            <div class="text-lg-end">
                <div class="text-muted small">Saldo</div>
                <div class="fs-5 fw-bold text-primary"><?= $saldoAprovador === null ? 'Não encontrado' : 'R$ ' . esc(moedaCotaSupervisor($saldoAprovador)) ?></div>
            </div>
        </div>
    </section>

    <?php if ($mensagem !== ''): ?>
        <div class="alert alert-<?= esc($tipoMensagem) ?> alert-dismissible fade show" role="alert"><?= esc($mensagem) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button></div>
    <?php endif; ?>
    <?php if ($erroTela !== ''): ?><div class="alert alert-danger"><?= esc($erroTela) ?></div><?php endif; ?>

    <section class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-check-double me-2"></i>Pedidos pendentes</div>
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle" data-datatable="1">
                <thead><tr><th>Supervisor</th><th>Matrícula</th><th>Placa</th><th>Data</th><th>Saldo supervisor</th><th>Total extra</th><th>Valor pedido</th><th>Justificativa</th><th>Ação</th></tr></thead>
                <tbody>
                <?php if ($pedidos === []): ?>
                    <tr>
                        <td class="text-muted">Nenhum pedido pendente encontrado.</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                <?php else: foreach ($pedidos as $pedido): ?>
                    <tr>
                        <td><strong><?= esc($pedido['nome_supervisor']) ?></strong></td>
                        <td><?= esc($pedido['matricula']) ?></td>
                        <td><?= esc($pedido['placa']) ?></td>
                        <td><?= esc(formatarDataPortal($pedido['data'] ?? '')) ?></td>
                        <td>R$ <?= esc(moedaCotaSupervisor($pedido['saldo_supervisor'])) ?></td>
                        <td>R$ <?= esc(moedaCotaSupervisor($pedido['totcotaextra'])) ?></td>
                        <td>R$ <?= esc(moedaCotaSupervisor($pedido['valor'])) ?></td>
                        <td style="min-width:240px;white-space:normal"><?= esc($pedido['justificativa']) ?></td>
                        <td style="min-width:220px">
                            <form action="control/aprovar-cota-supervisor.php" method="post" class="d-flex flex-column gap-2">
                                <input type="hidden" name="token" value="<?= esc($token) ?>">
                                <input type="hidden" name="idtbpedidossupcota" value="<?= esc($pedido['idtbpedidossupcota']) ?>">
                                <input type="text" name="valorinserido" class="form-control form-control-sm" value="<?= esc(moedaCotaSupervisor($pedido['valor'])) ?>" aria-label="Valor aprovado">
                                <div class="d-flex gap-1">
                                    <button type="submit" name="decisao" value="2" class="btn btn-success btn-sm flex-fill"><i class="fas fa-check me-1"></i>Aprovar</button>
                                    <button type="submit" name="decisao" value="1" class="btn btn-outline-danger btn-sm flex-fill"><i class="fas fa-times me-1"></i>Reprovar</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php renderRodapeAutofrota(); ?>
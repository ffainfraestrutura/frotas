<?php
require_once __DIR__ . '/includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? ($GLOBALS['conn'] ?? null);
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? ($GLOBALS['databaseName'] ?? 'bdautofrotas'));
$matriculaTecnico = (string) ($autofrotaSessao['matricula'] ?? $_SESSION['matricula'] ?? '');

if (!$conn instanceof mysqli) {
    exit('Conexão indisponível.');
}

$tecnico = buscarUmaLinha(
    $conn,
    "SELECT nome FROM `{$databaseName}`.`tbusuario` WHERE matricula = ? LIMIT 1",
    's',
    [$matriculaTecnico]
);
$nomeTecnico = trim((string) ($tecnico['nome'] ?? $autofrotaSessao['usuario'] ?? 'Técnico'));

if (!function_exists('escTecnico')) {
    function escTecnico($valor): string { return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8'); }
}

function moedaTecnico($valor): string
{
    return number_format((float) ($valor ?? 0), 2, ',', '.');
}

function numeroTecnico($valor, int $casas = 2): string
{
    return number_format((float) ($valor ?? 0), $casas, ',', '.');
}

$mensagem = (string) ($_SESSION['tecnico_pedido_mensagem'] ?? '');
$tipoMensagem = (string) ($_SESSION['tecnico_pedido_tipo'] ?? 'info');
$avisoImagem = (string) ($_SESSION['tecnico_pedido_aviso'] ?? '');
unset($_SESSION['tecnico_pedido_mensagem'], $_SESSION['tecnico_pedido_tipo'], $_SESSION['tecnico_pedido_aviso']);

$_SESSION['form_token_tecnico'] = bin2hex(random_bytes(32));
$formToken = $_SESSION['form_token_tecnico'];

$inicioSemana = date('Y-m-d', strtotime(date('N') === '1' ? 'today' : 'last monday'));
$fimSemana = date('Y-m-d', strtotime($inicioSemana . ' +6 days'));

$supervisor = buscarUmaLinha(
    $conn,
    "SELECT u2.matricula AS matricula_supervisor, u2.nome AS nome_supervisor
       FROM `{$databaseName}`.`tbusuario` u1
       LEFT JOIN `{$databaseName}`.`tbequipe_supervisor` s ON u1.idtbsupervisor = s.idtbsupervisor
       LEFT JOIN `{$databaseName}`.`tbusuario` u2 ON s.matricula = u2.matricula
      WHERE u1.matricula = ?
      LIMIT 1",
    's',
    [$matriculaTecnico]
);
$nomeSupervisor = trim((string) ($supervisor['nome_supervisor'] ?? ''));
$matriculaSupervisor = trim((string) ($supervisor['matricula_supervisor'] ?? ''));
$temSupervisor = $nomeSupervisor !== '' && $matriculaSupervisor !== '';

$veiculo = buscarUmaLinha(
    $conn,
    "SELECT placa FROM `{$databaseName}`.`tbveiculo` WHERE matcond = ? LIMIT 1",
    's',
    [$matriculaTecnico]
);
$placa = (string) ($veiculo['placa'] ?? '');
$temVeiculo = $placa !== '';

$saldo = buscarUmaLinha(
    $conn,
    "SELECT
            ROUND(COALESCE(ts.orcsemanal, 0) + COALESCE(cota_semana.total_cotas, 0), 2) AS valoraplicado,
            ROUND(COALESCE(ts.kmproj, 0), 2) AS kmproj,
            ROUND(COALESCE(ts.kmosatual, 0), 2) AS kmosatual,
            ROUND(COALESCE(ts.kmproj, 0) * 0.75, 2) AS limite,
            ROUND(COALESCE(ts.orcsemanal, 0), 2) AS orcsemanal,
            ROUND(COALESCE(ts.valoraplicado, 0) - COALESCE(ts.orcsemanal, 0), 2) AS totalextra,
            ROUND(COALESCE(ts.slddscnt, 0), 2) AS valordescontado
       FROM (
            SELECT *
              FROM `{$databaseName}`.`tbsaldo`
             WHERE matricula = ?
             ORDER BY data DESC
             LIMIT 1
       ) ts
       LEFT JOIN (
            SELECT matricula, SUM(valorinserido) AS total_cotas
              FROM `{$databaseName}`.`tbcotaextraac`
             WHERE matricula = ?
               AND data >= ?
               AND data <= ?
             GROUP BY matricula
       ) cota_semana ON ts.matricula = cota_semana.matricula",
    'ssss',
    [$matriculaTecnico, $matriculaTecnico, $inicioSemana . ' 00:00:00', $fimSemana . ' 23:59:59']
);

$valorAplicado = (float) ($saldo['valoraplicado'] ?? 0);
$kmProj = (float) ($saldo['kmproj'] ?? 0);
$kmOsAtual = (float) ($saldo['kmosatual'] ?? 0);
$limite = (float) ($saldo['limite'] ?? 0);
$temSaldo = $saldo !== [];
$podeSolicitar = $matriculaTecnico !== '' && $temSupervisor && $temVeiculo && $temSaldo;

function statusPedidoTecnico(array $pedido): string
{
    if (($pedido['desctec'] ?? null) !== null && (string) $pedido['desctec'] !== '') {
        return 'DESCONTADO';
    }

    $flag = (string) ($pedido['flag'] ?? '0');
    if ($flag === '0') {
        return 'PENDENTE';
    }
    if ($flag === '1') {
        return 'APROVADO';
    }
    if ($flag === '2') {
        return 'REPROVADO';
    }

    return 'FLAG ' . $flag;
}

$pedidosRecentes = consultaPreparada(
    $conn,
    "SELECT valor, justificativa, data, flag, tipocota, escalonado, desctec, placa, kmhodometro
       FROM `{$databaseName}`.`tbpedidostec`
      WHERE matricula = ?
      ORDER BY data DESC
      LIMIT 5",
    's',
    [$matriculaTecnico]
)['linhas'] ?? [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="AutoFrota" />
    <meta name="author" content="FFA" />
    <title>AutoFrota - Solicitação de Combustível</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body { background: linear-gradient(180deg, #f3f6fc 0%, #eef2f8 100%); color: #212529; font-size: 14px; }
        #layoutSidenav_content { padding: 14px 12px 0; }
        .page-wrapper { max-width: 1280px; margin: 0 auto; }
        .hero-card, .panel-card { border: 1px solid #cbd5e1; border-radius: 14px; box-shadow: 0 10px 28px rgba(15, 23, 42, .08); }
        .hero-card { background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%); }
        .status-card { border: 1px solid #bfcde0; border-radius: 12px; background: #fff; height: 100%; box-shadow: inset 0 0 0 1px #eef4fb; }
        .status-icon { width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; }
    </style>
</head>
<body class="sb-nav-fixed">
    <?php autofrotaMenu(); ?>
    <div id="layoutSidenav_content">
        <main class="page-wrapper py-2">
            <section class="hero-card p-4 mb-4">
                <h1 class="h3 mb-2">Solicitação de combustível</h1>
                <p class="fs-5 fw-bold mb-1"><i class="fas fa-user me-2"></i>Técnico: <?= escTecnico($nomeTecnico) ?></p>
                <p class="text-muted mb-0"><small>Matrícula: <?= escTecnico($matriculaTecnico !== '' ? $matriculaTecnico : 'não informada') ?> · Supervisor: <?= $temSupervisor ? escTecnico($nomeSupervisor . ' (' . $matriculaSupervisor . ')') : 'não atribuído' ?></small></p>
            </section>

            <?php if ($mensagem !== ''): ?>
                <div class="alert alert-<?= escTecnico($tipoMensagem) ?> alert-dismissible fade show" role="alert">
                    <?= escTecnico($mensagem) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endif; ?>
            <?php if ($avisoImagem !== ''): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <?= escTecnico($avisoImagem) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endif; ?>

            <?php if (!$podeSolicitar): ?>
                <div class="alert alert-warning">
                    <strong>Atenção:</strong><br> complete os dados obrigatórios para realizar pedido.
                    <?php if (!$temSupervisor): ?><div>Supervisor não encontrado para sua matrícula.</div><?php endif; ?>
                    <?php if (!$temVeiculo): ?><div>Veículo não encontrado para sua matrícula.</div><?php endif; ?>
                    <?php if (!$temSaldo): ?><div>Saldo não encontrado para sua matrícula.</div><?php endif; ?>
                </div>
            <?php endif; ?>

            <section class="row g-3 mb-4">
                <div class="col-12 col-md-6"><div class="status-card p-3"><span class="status-icon text-bg-primary mb-3"><i class="fas fa-gas-pump"></i></span><h2 class="h6 mb-1">Valor recebido na semana</h2><div class="fs-4 fw-bold">R$ <?= escTecnico(moedaTecnico($valorAplicado)) ?></div></div></div>
                <div class="col-12 col-md-6"><div class="status-card p-3"><span class="status-icon text-bg-success mb-3"><i class="fas fa-route"></i></span><h2 class="h6 mb-1">KM Projetado</h2><div class="fs-4 fw-bold"><?= escTecnico(numeroTecnico($kmProj)) ?> km</div></div></div>
            </section>

            <section class="panel-card card mb-4">
                <div class="card-header bg-white fw-semibold"><i class="fas fa-clipboard-list me-2"></i>Formulário de solicitação</div>
                <div class="card-body">
                    <form class="row g-3" action="control/tecnico.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="form_token" value="<?= escTecnico($formToken) ?>">
                        <div class="col-12 col-md-4"><label class="form-label" for="placa">Placa <span class="text-danger">*</span></label><input id="placa" name="placa" class="form-control" value="<?= escTecnico($placa) ?>" readonly required></div>
                        <div class="col-12 col-md-4"><label class="form-label" for="kmhodometro">Hodômetro atual <span class="text-danger">*</span></label><input id="kmhodometro" name="kmhodometro" type="number" min="0" step="1" class="form-control" placeholder="Digite o valor do hodômetro" required></div>
                        <div class="col-12 col-md-4"><label class="form-label" for="valor">Valor solicitado <span class="text-danger">*</span></label><div class="input-group"><span class="input-group-text">R$</span><input id="valor" name="valor" class="form-control" placeholder="0,00" maxlength="10" required></div></div>
                        <div class="col-12"><label class="form-label" for="justificativa">Justificativa <span class="text-danger">*</span></label><textarea id="justificativa" name="justificativa" class="form-control" rows="3" placeholder="Informe uma justificativa para solicitação" required></textarea></div>
                        <div class="col-12 col-md-6"><label class="form-label" for="arquivo">Foto do hodômetro <span class="text-muted">(Opcional)</span></label><input id="arquivo" name="arquivo" type="file" class="form-control" accept="image/*" capture="environment"></div>
                        <div class="col-12"><div class="form-text">A foto é opcional. O pedido será processado mesmo sem imagem.</div></div>
                        <div class="col-12 d-flex flex-wrap gap-2"><button type="submit" class="btn btn-primary" <?= $podeSolicitar ? '' : 'disabled' ?>><i class="fas fa-paper-plane me-2"></i>Enviar solicitação</button></div>
                    </form>
                </div>
            </section>

            <section class="panel-card card mb-4">
                <div class="card-header bg-white fw-semibold"><i class="fas fa-clock-rotate-left me-2"></i>Últimos pedidos</div>
                <div class="card-body table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Data</th><th>Placa</th><th>Valor</th><th>Hodômetro</th><th>Status</th><th>Justificativa</th></tr></thead>
                        <tbody>
                        <?php if ($pedidosRecentes === []): ?>
                            <tr><td colspan="6" class="text-muted">Nenhum pedido encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pedidosRecentes as $pedido): ?>
                                <tr><td><?= escTecnico(formatarDataPortal($pedido['data'] ?? '')) ?></td><td><?= escTecnico($pedido['placa'] ?? '') ?></td><td>R$ <?= escTecnico(moedaTecnico($pedido['valor'] ?? 0)) ?></td><td><?= escTecnico($pedido['kmhodometro'] ?? '') ?></td><td><span class="badge text-bg-secondary"><?= escTecnico(statusPedidoTecnico($pedido)) ?></span></td><td><?= escTecnico($pedido['justificativa'] ?? '') ?></td></tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', e => { e.preventDefault(); document.body.classList.toggle('sb-sidenav-toggled'); });
        document.getElementById('valor')?.addEventListener('input', function () {
            let valor = this.value.replace(/\D/g, '');
            if (valor === '') { this.value = ''; return; }
            valor = (parseInt(valor, 10) / 100).toFixed(2).replace('.', ',');
            valor = valor.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            this.value = valor;
        });
    </script>
</body>
</html>

<!--  -->
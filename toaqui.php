<?php
require_once __DIR__ . '/includes/autofrota_common.php';
$sessao = autofrotaInit();

$conn = $sessao['conn'] ?? null;
$databaseCorp = (string) ($sessao['databaseCorp'] ?? '');
$matricula = trim((string) ($sessao['matricula'] ?? ''));
$latitude = filter_input(INPUT_GET, 'latitude', FILTER_VALIDATE_FLOAT);
$longitude = filter_input(INPUT_GET, 'longitude', FILTER_VALIDATE_FLOAT);
$precisao = filter_input(INPUT_GET, 'precisao', FILTER_VALIDATE_FLOAT);

if ($latitude === false || $latitude === null || $longitude === false || $longitude === null
    || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    header('Location: localizacao.php');
    exit;
}

$nome = trim((string) ($_SESSION['nome'] ?? $sessao['usuario'] ?? 'Técnico'));
$permiteAtividade = false;
$centrosAtividade = [
    'CLARO - REDE EXTERNA', 'OPERAÇÃO - CLARO - MAN', 'OPERAÇÃO -CLARO -OSP GDE OBRAS',
    'OPERAÇÃO -CLARO -OSP PEQ OBRAS', 'TIM RJ - IMPLANTAÇÃO', 'TIM RJ - REDE EXTERNA',
    'TIM SP - IMPLANTAÇÃO', 'TIM SP - REDE EXTERNA', 'TIM SP IMPLANTAÇÃO',
];

if ($conn instanceof mysqli && preg_match('/^[a-zA-Z0-9_]+$/', $databaseCorp)) {
    $placeholders = implode(',', array_fill(0, count($centrosAtividade), '?'));
    $tipos = 's' . str_repeat('s', count($centrosAtividade));
    $consulta = consultaPreparada(
        $conn,
        "SELECT 1 FROM `{$databaseCorp}`.`tbfuncionario` WHERE matricula = ? AND ccusto IN ({$placeholders}) LIMIT 1",
        $tipos,
        array_merge([$matricula], $centrosAtividade)
    );
    $permiteAtividade = ($consulta['linhas'] ?? []) !== [];
}

$_SESSION['toaqui_token'] = bin2hex(random_bytes(32));
$mensagem = (string) ($_SESSION['toaqui_mensagem'] ?? '');
$tipoMensagem = (string) ($_SESSION['toaqui_tipo'] ?? 'info');
unset($_SESSION['toaqui_mensagem'], $_SESSION['toaqui_tipo']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AutoFrota - Tô Aqui</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body { background: linear-gradient(180deg, #f3f6fc 0%, #eef2f8 100%); font-size: 14px; }
        .page-wrapper { max-width: 900px; margin: 0 auto; }
        .hero-card, .form-card { border: 1px solid #cbd5e1; border-radius: 14px; box-shadow: 0 10px 28px rgba(15, 23, 42, .08); }
    </style>
</head>
<body class="sb-nav-fixed">
<?php autofrotaMenu(); ?>
<main class="page-wrapper px-3 py-3">
    <section class="hero-card bg-white p-4 mb-4">
        <h1 class="h3 mb-2"><i class="fas fa-location-dot text-primary me-2"></i>Tô Aqui</h1>
        <p class="fs-5 fw-semibold mb-1">Técnico: <?= esc($nome) ?></p>
        <p class="text-muted mb-0">Confirme o motivo para registrar sua posição atual.</p>
    </section>

    <?php if ($mensagem !== ''): ?><div class="alert alert-<?= esc($tipoMensagem) ?>"><?= esc($mensagem) ?></div><?php endif; ?>

    <section class="card form-card">
        <div class="card-body p-4">
            <div class="alert alert-success py-2"><i class="fas fa-circle-check me-2"></i>Localização capturada<?= $precisao ? ' (precisão aproximada de ' . esc((string) round($precisao)) . ' m)' : '' ?>.</div>
            <form action="control/toaqui.php" method="post">
                <input type="hidden" name="token" value="<?= esc($_SESSION['toaqui_token']) ?>">
                <input type="hidden" name="latitude" value="<?= esc((string) $latitude) ?>">
                <input type="hidden" name="longitude" value="<?= esc((string) $longitude) ?>">
                <input type="hidden" id="endereco" name="endereco" value="">
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="motivo">Motivo <span class="text-danger">*</span></label>
                    <select class="form-select" name="motivo" id="motivo" required>
                        <option value="">Selecione o motivo</option>
                        <option value="base">Base</option>
                        <option value="ponto de encontro">Ponto de Encontro</option>
                        <option value="matriz">Matriz</option>
                        <?php if ($permiteAtividade): ?><option value="atividade">Atividade</option><?php endif; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="justificativa">Observação</label>
                    <textarea class="form-control" id="justificativa" name="justificativa" rows="5" maxlength="2000" placeholder="Escreva uma observação, se necessário"></textarea>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-success" type="submit"><i class="fas fa-check me-2"></i>Confirmar</button>
                    <a class="btn btn-outline-secondary" href="localizacao.php"><i class="fas fa-location-crosshairs me-2"></i>Atualizar localização</a>
                </div>
            </form>
        </div>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=<?= rawurlencode((string) $latitude) ?>&lon=<?= rawurlencode((string) $longitude) ?>&accept-language=pt-BR')
    .then(response => response.ok ? response.json() : Promise.reject())
    .then(data => { document.getElementById('endereco').value = (data.display_name || '').slice(0, 500); })
    .catch(() => { /* As coordenadas continuam sendo registradas se o endereço não for resolvido. */ });
</script>
</body>
</html>

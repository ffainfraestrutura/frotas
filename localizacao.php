<?php
require_once __DIR__ . '/includes/autofrota_common.php';
autofrotaInit();
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
        body { background: linear-gradient(180deg, #f3f6fc 0%, #eef2f8 100%); color: #212529; }
        .location-card { max-width: 620px; border: 1px solid #cbd5e1; border-radius: 16px; box-shadow: 0 12px 32px rgba(15, 23, 42, .1); }
        .location-icon { width: 72px; height: 72px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: #e7f1ff; color: #0d6efd; font-size: 30px; }
    </style>
</head>
<body class="sb-nav-fixed">
<?php autofrotaMenu(); ?>
<main class="container py-4">
    <section class="card location-card mx-auto" aria-live="polite">
        <div class="card-body p-4 p-md-5 text-center">
            <span class="location-icon mb-3"><i class="fas fa-location-crosshairs"></i></span>
            <h1 class="h3">Registrar Tô Aqui</h1>
            <p id="locationStatus" class="text-muted mb-4">Para continuar, permita que o navegador acesse sua localização atual.</p>
            <div id="locationAlert" class="alert alert-info text-start" role="status">
                <i class="fas fa-spinner fa-spin me-2"></i>Solicitando sua localização...
            </div>
            <button id="retryLocation" class="btn btn-primary d-none" type="button">
                <i class="fas fa-rotate-right me-2"></i>Tentar novamente
            </button>
        </div>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const status = document.getElementById('locationStatus');
    const alert = document.getElementById('locationAlert');
    const retry = document.getElementById('retryLocation');

    function showError(message) {
        status.textContent = 'Não foi possível obter sua localização.';
        alert.className = 'alert alert-danger text-start';
        alert.innerHTML = '<i class="fas fa-triangle-exclamation me-2"></i>' + message;
        retry.classList.remove('d-none');
    }

    function locate() {
        retry.classList.add('d-none');
        status.textContent = 'Aguarde enquanto obtemos sua localização atual.';
        alert.className = 'alert alert-info text-start';
        alert.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Solicitando sua localização...';

        if (!navigator.geolocation) {
            showError('A geolocalização não é suportada por este navegador.');
            return;
        }

        navigator.geolocation.getCurrentPosition(position => {
            const params = new URLSearchParams({
                latitude: position.coords.latitude.toFixed(7),
                longitude: position.coords.longitude.toFixed(7),
                precisao: Math.round(position.coords.accuracy).toString()
            });
            status.textContent = 'Localização obtida. Redirecionando...';
            alert.className = 'alert alert-success text-start';
            alert.innerHTML = '<i class="fas fa-circle-check me-2"></i>Localização obtida com sucesso.';
            window.location.assign('toaqui.php?' + params.toString());
        }, error => {
            const messages = {
                1: 'A permissão foi negada. Habilite a localização nas configurações do navegador e tente novamente.',
                2: 'A localização está indisponível. Verifique o GPS ou a conexão do aparelho.',
                3: 'O tempo para obter a localização expirou. Tente novamente.'
            };
            showError(messages[error.code] || 'Ocorreu um erro ao consultar a localização.');
        }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
    }

    retry.addEventListener('click', locate);
    locate();
})();
</script>
</body>
</html>

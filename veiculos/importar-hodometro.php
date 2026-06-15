<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../control/conecta.php';
exigirLogin();

date_default_timezone_set('America/Sao_Paulo');

$perfilLogado = (string) ($_SESSION['perfil'] ?? $_POST['perfil_autor'] ?? '');
$matriculaLogada = (string) ($_SESSION['matricula'] ?? $_POST['matr_autor'] ?? $_SESSION['usuario'] ?? '');

$_SESSION['perfil'] = $perfilLogado;
$_SESSION['matricula'] = $matriculaLogada;

function esc(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="AutoFrota">
    <meta name="author" content="FFA">
    <title>AutoFrota - Atualização de Hodômetro em Lote via Excel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="src/autofrota-botoes.css">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body { background: #fff; color: #000; font-size: 12px; }
        .page-title { font-size: 24px; font-weight: 600; margin-bottom: 14px; }
        .form-label, .form-control, .btn, .alert, .card { font-size: 12px; }
        .required-note { color: #dc3545; }
    </style>
</head>
<body class="sb-nav-fixed">
    <?php include __DIR__ . '/../includes/menu_superior_simples.php'; ?>

    <div id="layoutSidenav_content">
        <main class="page-wrapper py-3">
            <h1 class="page-title">Atualização de Hodômetro em Lote via Excel</h1>

            <div class="alert alert-info" role="alert">
                Envie uma planilha <strong>.xls</strong> ou <strong>.xlsx</strong> com as colunas <strong>Placa</strong> e <strong>Hodômetro</strong>.
                Use o botão <strong>Modelo Excel</strong> para baixar o arquivo padrão antes da importação.
            </div>

            <form method="post" action="./control/importar-hodometro.php" enctype="multipart/form-data" class="pb-5">
                <input type="hidden" name="matr_autor" value="<?= esc($matriculaLogada) ?>">
                <input type="hidden" name="perfil_autor" value="<?= esc($perfilLogado) ?>">

                <div class="card mb-3">
                    <div class="card-header">Arquivo de hodômetro</div>
                    <div class="card-body row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="arquivo">Selecione o arquivo <span class="text-danger">*</span></label>
                            <input class="form-control" type="file" id="arquivo" name="arquivo" accept=".xls,.xlsx" required>
                        </div>
                    </div>
                </div>

                <p class="required-note">* Campos obrigatórios.</p>

                <div class="d-flex gap-3 pb-4 flex-wrap">
                    <button class="btn btn-success" type="submit">Confirmar</button>
                    <a class="btn btn-danger" href="inventario-veiculo.php" onclick="if (window.opener && !window.opener.closed) { window.close(); return false; }">Voltar</a>
                    <a class="btn btn-secondary" href="/../autofrota/control/docs/modelos/ImportModeloHodometro.xls" download>Modelo Excel</a>
                </div>
            </form>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', function(event) {
            event.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
        });
    </script>
</body>
</html>
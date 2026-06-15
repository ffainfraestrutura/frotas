<?php
require_once __DIR__ . '/../auth.php';

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

require_once __DIR__ . '/../control/conecta.php';
exigirLogin();

$matriculaLogada = (string) ($_POST['matr_autor'] ?? $_SESSION['matricula'] ?? $_SESSION['usuario'] ?? '');
$perfilLogado = (string) ($_POST['perfil_autor'] ?? $_SESSION['perfil'] ?? '');

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
    <title>AutoFrota - Atualizar Hodômetro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../src/autofrota-botoes.css">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body { background: #fff; color: #000; font-size: 12px; }
        .page-title { font-size: 24px; font-weight: 600; margin-bottom: 14px; }
        .form-label, .form-control, .btn, .alert { font-size: 12px; }
        .required-note { color: #dc3545; }
    </style>
</head>
<body class="sb-nav-fixed">
    <?php include __DIR__ . '/../includes/menu_superior_simples.php'; ?>

    <div id="layoutSidenav_content">
        <main class="page-wrapper py-3">
            <h1 class="page-title">Atualizar Hodômetro</h1>

            <div class="alert alert-info" role="alert">
                Envie uma planilha <strong>.xls</strong> ou <strong>.xlsx</strong> com as colunas <strong>Placa</strong> e <strong>Hodômetro</strong>, conforme a tela original de importação.
            </div>

            <form method="post" action="control/importarhodometro.php" enctype="multipart/form-data" class="pb-5">
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

                <div class="d-flex gap-3 pb-4">
                    <button class="btn btn-success" type="submit">Confirmar</button>
                    <button class="btn btn-danger" type="button" onclick="window.close();">Voltar</button>
                    <a class="btn btn-secondary" href="veiculos.php">Lista de veículos</a>
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

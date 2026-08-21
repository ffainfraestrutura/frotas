<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$matricula = $autofrotaSessao['matricula'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="FFA" />
    <meta name="author" content="FFA" />
    <title>AutoFrota - Importar CNHs</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body { background: #ffffff; color: #212529; font-size: 14px; }
        .content-wrap { width: 100%; }
        .form-wrap { width: 80%; }
        @media (max-width: 768px) { .form-wrap { width: 100%; } }
    </style>
</head>
<body class="sb-nav-fixed vsc-initialized sb-sidenav-toggled">
    <?php include __DIR__ . '/../includes/menu_superior_simples.php'; ?>

        <div id="layoutSidenav_content">
            <main class="content-wrap mb-2">
                <div class="container-fluid px-4 content-wrap">
                    <h1 class="h1 pt-2 pb-2 ms-3">Importar CNHs</h1>
    
                    <div class="form-wrap">
                         <form method="post" action="control/importarcnh.php" enctype="multipart/form-data" style="width: 90%;" class="m-auto">
                            <input type="hidden" name="matr_autor" value="<?= htmlspecialchars($matricula) ?>">
                            <div class="col-md-12 d-flex justify-content-start">
                                <div class="ms-2 d-flex col-sm-8 flex-column">
                                    <label for="arquivo" class="form-label">Selecione o arquivo:</label>
                                    <input class="form-control" type="file" id="arquivo" name="arquivo" accept=".xls, .xlsx" required>
                                </div>
                            </div>

                            <div style="color: red;" class="pt-2">
                                * Campos obrigatórios.
                            </div>

                            <div class="mt-3 pb-2 d-flex col-sm-12 justify-content-start">
                                <div>
                                    <input class="btn btn-success" type="submit" value="Confirmar cadastro">
                                </div>

                                <div class="ms-5">
                                    <button class="btn btn-secondary" type="button" onclick="window.history.back()">Voltar</button>
                                </div>

                                <div class="ms-5">
                                    <a class="btn btn-secondary" href="control/docs/modelos/ImportModeloCNH.xlsx" download>Modelo Excel</a>
                                </div>
                            </div>
                        </form>

                        <ul class="font-italic mb-0" style="font-size: 12px; font-style: italic;">
                            <li>Substituia as anotações no modelo pelos dados corretos. Para data e dados númericos, lembre-se utilizar a apóstrofo ('). Porém, evite o uso do apóstrofo em nomes e outros dados textuais.</li>
                            <li>Caso não possua informação para preencher o campo, deixe a célula em branco (lembre-se de apagar o conteúdo pré-preenchido do modelo).</li>
                            <li>Não se esqueça de excluir a linha ilustrativa.</li>
                        </ul>
                    </div>
                </div>
            </main>

            <footer class="mt-4 py-4 bg-light mt-auto" style="position: fixed; bottom: 0; start: 0; width: 100%;">
                <div class="container-fluid px-4">
                    <div class="d-flex align-items-center justify-content-between small">
                        <div class="text-muted">Copyright &copy; FFA Infraestrutura</div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.15/jquery.mask.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', function(event) {
            event.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
        });
    </script>
</body>
</html>
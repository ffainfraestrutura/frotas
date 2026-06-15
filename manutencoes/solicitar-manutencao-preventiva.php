<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../control/conecta.php';
exigirLogin();

date_default_timezone_set('America/Sao_Paulo');

$perfilLogado = (string) ($_SESSION['perfil'] ?? $_POST['perfil'] ?? '');
$matriculaLogada = (string) ($_SESSION['matricula'] ?? $_POST['mat_autor'] ?? $_SESSION['usuario'] ?? '');
$usuarioLogado = $_SESSION['usuario'] ?? $_SESSION['nome'] ?? 'Usuário';

$placaInicial = strtoupper(trim((string) ($_GET['placa'] ?? $_POST['placa'] ?? '')));
$placaInicial = str_replace(['-', ' '], '', $placaInicial);

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
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="AutoFrota" />
    <meta name="author" content="FFA" />
    <title>AutoFrota - Cadastro de Manutenção</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="src/autofrota-botoes.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body { background: #ffffff; color: #000000; font-size: 12px; }
        .page-wrapper {
            width: 100%;
            margin: 0;
            padding: 16px 20px 28px;
        }
        .page-title { font-size: 24px; font-weight: 600; margin: 0 0 18px; }
        .card-form { max-width: 760px; margin: 0; }
        .form-label { margin-bottom: 6px; }
        .form-control { font-size: 12px; border-radius: 2px; text-transform: uppercase; }
        .btn { font-size: 12px; border-radius: 3px; padding: 6px 10px; }
        .required-note { color: red; }
        @media (max-width: 576px) {
            .page-wrapper {
                padding: 12px 12px 22px;
            }
        }
    </style>
</head>
<body class="sb-nav-fixed">
    <?php include __DIR__ . '/../includes/menu_superior_simples.php'; ?>

        <div id="layoutSidenav_content">
        <main class="page-wrapper">
            <h1 class="page-title">Cadastro de Manutenção</h1>

            <section class="card-form">
                <form method="get" action="cadastrar-manutencao-preventiva.php">
                    <input type="hidden" name="perfil_autor" value="<?= esc($perfilLogado) ?>">
                    <input type="hidden" name="matr_autor" value="<?= esc($matriculaLogada) ?>">

                    <div class="mt-2 col-sm-8 ps-0">
                        <label class="form-label" for="placa">Informe a placa:<span class="required-note">*</span></label>
                        <input class="form-control" type="text" name="placa" id="placa" placeholder="Placa" maxlength="8" value="<?= esc($placaInicial) ?>" required>
                    </div>

                    <div class="mt-2 pt-2 col-sm-8 ps-0 required-note">
                        <p>* Campos obrigatórios.</p>
                    </div>

                    <div class="mt-3 pb-2 d-flex col-sm-12 justify-content-start gap-4 ps-0">
                        <button class="btn btn-success" type="submit">Confirmar</button>
                        <button class="btn btn-secondary" type="button" onclick="history.back()">Voltar</button>
                        <button class="btn btn-danger" type="button" onclick="history.back()">Cancelar</button>
                    </div>
                </form>
            </section>
        </main>
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

        $(document).ready(function() {
            $('#placa').mask('AAA0U00', {
                translation: {
                    'A': { pattern: /[A-Za-z]/ },
                    'U': { pattern: /[A-Za-z0-9]/ }
                },
                onKeyPress: function(value, event, field, options) {
                    event.currentTarget.value = value.toUpperCase();
                    const valueWithoutMask = value.replace(/[^\w]/g, '');
                    const fifthCharacterIsNumeric = !isNaN(parseFloat(valueWithoutMask[4])) && isFinite(valueWithoutMask[4]);
                    const mask = valueWithoutMask.length > 4 && fifthCharacterIsNumeric ? 'AAA-0000' : 'AAA 0U00';
                    $(field).mask(mask, options);
                }
            });
        });
    </script>
</body>
</html>

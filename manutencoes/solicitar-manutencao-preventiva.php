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

$placas = [];
$erroCarregamentoPlacas = '';

if ($conn instanceof mysqli) {
    mysqli_set_charset($conn, 'utf8mb4');
    $sqlPlacas = "SELECT DISTINCT placa
                    FROM `{$databaseName}`.`tbveiculo`
                   WHERE placa IS NOT NULL
                     AND TRIM(placa) <> ''
                   ORDER BY placa";
    $resultadoPlacas = mysqli_query($conn, $sqlPlacas);

    if ($resultadoPlacas) {
        while ($veiculo = mysqli_fetch_assoc($resultadoPlacas)) {
            $placa = strtoupper(trim((string) ($veiculo['placa'] ?? '')));
            if ($placa !== '') {
                $placas[] = $placa;
            }
        }
        mysqli_free_result($resultadoPlacas);
    } else {
        error_log('Erro ao carregar placas para manutenção preventiva: ' . mysqli_error($conn));
        $erroCarregamentoPlacas = 'Não foi possível carregar as placas cadastradas. Tente novamente mais tarde.';
    }
} else {
    $erroCarregamentoPlacas = 'Não foi possível carregar as placas cadastradas. Tente novamente mais tarde.';
}

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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
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
        .form-control, .form-select { font-size: 12px; border-radius: 2px; text-transform: uppercase; }
        .select2-container--bootstrap-5 { font-size: 12px; }
        .select2-container--bootstrap-5 .select2-selection { border-radius: 2px; }
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
                        <label class="form-label" for="placa">Selecione a placa:<span class="required-note">*</span></label>
                        <select class="form-select" name="placa" id="placa" aria-describedby="placa-ajuda" required <?= $erroCarregamentoPlacas !== '' ? 'disabled' : '' ?>>
                            <option value="">Digite para buscar uma placa</option>
                            <?php foreach ($placas as $placa): ?>
                                <?php $placaNormalizada = str_replace(['-', ' '], '', $placa); ?>
                                <option value="<?= esc($placa) ?>" <?= $placaNormalizada === $placaInicial ? 'selected' : '' ?>><?= esc($placa) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="placa-ajuda" class="form-text">Digite parte da placa e escolha uma das opções cadastradas.</div>
                    </div>

                    <?php if ($erroCarregamentoPlacas !== ''): ?>
                        <div class="alert alert-danger mt-3 col-sm-8" role="alert"><?= esc($erroCarregamentoPlacas) ?></div>
                    <?php endif; ?>

                    <div class="mt-2 pt-2 col-sm-8 ps-0 required-note">
                        <p>* Campos obrigatórios.</p>
                    </div>

                    <div class="mt-3 pb-2 d-flex col-sm-12 justify-content-start gap-4 ps-0">
                        <button class="btn btn-success" type="submit" <?= $erroCarregamentoPlacas !== '' ? 'disabled' : '' ?>>Confirmar</button>
                        <button class="btn btn-secondary" type="button" onclick="history.back()">Voltar</button>
                        <button class="btn btn-danger" type="button" onclick="history.back()">Cancelar</button>
                    </div>
                </form>
            </section>
        </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/pt-BR.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function normalizarPlaca(valor) {
            return String(valor || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
        }

        function buscarPlaca(params, data) {
            const termo = normalizarPlaca(params.term);

            if (termo === '' || normalizarPlaca(data.text).includes(termo)) {
                return data;
            }

            return null;
        }

        document.getElementById('sidebarToggle')?.addEventListener('click', function(event) {
            event.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
        });

        $(document).ready(function() {
            $('#placa:not(:disabled)').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Digite para buscar uma placa',
                language: 'pt-BR',
                allowClear: false,
                matcher: buscarPlaca
            });
        });
    </script>
</body>
</html>
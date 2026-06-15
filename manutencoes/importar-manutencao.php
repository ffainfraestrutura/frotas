<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../control/conecta.php';
exigirLogin();

date_default_timezone_set('America/Sao_Paulo');

$perfilLogado = (string) ($_SESSION['perfil'] ?? $_POST['perfil'] ?? '');
$matriculaLogada = (string) ($_SESSION['matricula'] ?? $_POST['mat_autor'] ?? $_SESSION['usuario'] ?? '');
$usuarioLogado = $_SESSION['usuario'] ?? $_SESSION['nome'] ?? 'Usuário';

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
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="AutoFrota" />
    <meta name="author" content="FFA" />
    <title>AutoFrota - Importação de Manutenção</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="src/autofrota-botoes.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body { background: #ffffff; color: #000000; font-size: 12px; }
        .page-wrapper {
            width: 100%;
            margin: 0;
            padding: 16px 14px 28px;
        }
        .page-title { font-size: 24px; font-weight: 600; margin: 0 0 18px; }
        .form-area { max-width: 900px; }
        .form-label { margin-bottom: 6px; }
        .form-control { font-size: 12px; border-radius: 2px; }
        .btn { font-size: 12px; border-radius: 3px; padding: 6px 10px; }
        .required-note { color: red; }
        .help-text { font-size: 12px; font-style: italic; }
        @media (max-width: 576px) {
            .page-wrapper {
                padding: 12px 10px 22px;
            }
        }
    </style>
</head>
<body class="sb-nav-fixed">
    <?php include __DIR__ . '/../includes/menu_superior_simples.php'; ?>

        <div id="layoutSidenav_content">
        <main class="page-wrapper">
            <h1 class="page-title">Importação de Manutenção</h1>

            <section class="form-area">
                <form method="post" action="./control/importarmanutencao.php" enctype="multipart/form-data" class="m-auto">
                    <input type="hidden" name="matr_autor" value="<?= esc($matriculaLogada) ?>">
                    <input type="hidden" name="perfil_autor" value="<?= esc($perfilLogado) ?>">

                    <div class="col-md-12 d-flex justify-content-start">
                        <div class="d-flex col-sm-8 flex-column">
                            <label for="arquivo" class="form-label">Selecione o arquivo:<span class="required-note">*</span></label>
                            <input class="form-control" type="file" id="arquivo" name="arquivo" accept=".xls, .xlsx" required>
                        </div>
                    </div>

                    <div class="pt-2 required-note">* Campos obrigatórios.</div>

                    <div class="mt-3 pb-2 d-flex col-sm-12 justify-content-start gap-4 flex-wrap">
                        <input class="btn btn-success" type="submit" value="Confirmar cadastro">
                        <button class="btn btn-danger" type="button" onclick="window.close();">Voltar</button>
                        <a class="btn btn-secondary" href="/../autofrota/control/docs/modelos/ModeloImportManutencao.xlsx" download>Modelo Excel</a>
                    </div>
                </form>

        
                <div class="alert alert-danger mt-3" role="alert">
                    <strong>⚠️ Colunas Obrigatórias:</strong>
                    <ul class="mb-0">
                        <li><strong>Coluna A - Tipo Solicitação:</strong> Tipo de manutenção ou solicitação</li>
                        <li><strong>Coluna B - Placa:</strong> Placa do veículo (ex: ABC-1234)</li>
                        <li><strong>Coluna C - Data Abertura:</strong> Data de abertura/ocorrência (formato: dd/mm/aaaa ou usar apostrofo)</li>
                        <li><strong>Coluna N - Data Agendamento Oficina:</strong> data do agendamento da manutenção (formato: dd/mm/aaaa ou usar apostrofo)</li>
                    </ul>
                </div>

                <!-- <div class="alert alert-info mt-3" role="alert">
                    <strong>Colunas que aparecem na listagem:</strong>
                    <ul class="mb-0">
                        <li><strong>Coluna A - Tipo Solicitação:</strong> preenche o campo Tipo</li>
                        <li><strong>Coluna B - Placa:</strong> preenche o campo Placa</li>
                        <li><strong>Coluna C - Data Abertura:</strong> preenche a Data de Cadastro</li>
                        <li><strong>Coluna F - Status:</strong> preenche o campo Status</li>
                        <li><strong>Coluna K - Descrição:</strong> preenche a Observação exibida na listagem</li>
                        <li><strong>Coluna M - Oficina:</strong> preenche o campo Oficina</li>
                        <li><strong>Coluna N - Data Agendamento Oficina:</strong> preenche a Data de Agendamento</li>
                    </ul>
                </div> -->

                <ul class="help-text mt-3 ps-3">
                    <li>Sem essas 4 colunas preenchidas, a linha será ignorada na importação.</li>
                    <li>Para a manutenção aparecer completa na listagem, preencha também Status, Descrição e Oficina.</li>
                    <li>Substitua as anotações no modelo pelos dados corretos.</li>
                    <li>Para data e dados numéricos, lembre-se de utilizar o apóstrofo (') no início da célula.</li>
                    <li>Evite o uso do apóstrofo em nomes e outros dados textuais.</li>
                    <li>Caso não possua informação para preencher um campo <u>opcional</u>, deixe a célula em branco (lembre-se de apagar o conteúdo pré-preenchido do modelo).</li>
                    <li>Não se esqueça de excluir a linha de exemplo (Manutenção ELUESA4).</li>
                </ul>
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
    </script>
</body>
</html>

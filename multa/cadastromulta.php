<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

const APP_DEBUG = true;

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

$perfil = $_SESSION['perfil'] ?? null;
/*
if (empty($perfil)) {
    header('Location: ../index.php?erro=login');
    exit;
}
*/
$nome = (string) ($_SESSION['nome'] ?? '');
$usuario = (string) ($_SESSION['usuario'] ?? '');
$matricula = (string) ($_SESSION['matricula'] ?? '');
$tipo = (string) ($_SESSION['tipo'] ?? '');

require_once '../control/conecta.php';

if (isset($conn) && $conn instanceof mysqli) {
    mysqli_set_charset($conn, 'utf8mb4');
}

header('Content-Type: text/html; charset=utf-8');

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function carregarOpcoes(mysqli $conn, string $sql, string $valueField, string $labelField): array
{
    $options = [];
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        error_log('Erro ao carregar opcoes: ' . mysqli_error($conn));
        return $options;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $options[] = [
            'value' => (string) ($row[$valueField] ?? ''),
            'label' => (string) ($row[$labelField] ?? ''),
        ];
    }

    mysqli_free_result($result);
    return $options;
}

$colaboradores = carregarOpcoes(
    $conn,
    'SELECT matricula, nome FROM tbcondutor WHERE status = "Ativo" ORDER BY nome',
    'matricula',
    'nome'
);


$filiais = carregarOpcoes(
    $conn,
    'SELECT idtbfilial, descricao AS nome FROM bdcorp.tbfilial ORDER BY descricao',
    'idtbfilial',
    'nome'
);

$centrosCusto = carregarOpcoes(
    $conn,
    "SELECT descricao as ccusto FROM bdcorp.tbccusto WHERE visivel = 1 ORDER BY descricao",
    'ccusto',
    'ccusto'
);

$fornecedores = carregarOpcoes(
    $conn,
    'SELECT idtbfornecedor, fantasia FROM tbfornecedor WHERE tipo = 4 ORDER BY fantasia',
    'idtbfornecedor',
    'fantasia'
);

$placas = carregarOpcoes(
    $conn,
    'SELECT placa, placa FROM tbveiculo WHERE statusvel = 1 ORDER BY placa',
    'placa',
    'placa'
);
?>
<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Cadastro de multa">
    <meta name="author" content="FFA">
    <link rel="icon" type="image/png" href="../src/images/favicon.png">
    <title>Cadastro de Multa</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />
    <link href="../src/css/styles.css" rel="stylesheet">

    <style>
        body {
            background: #f5f7fb;
        }

        a {
            text-decoration: none;
            color: black;
        }

        .fa-arrow-left {
            background-color: #fff;
            border-radius: 50%;
        }

        .page-shell {
            max-width: 1180px;
        }

        .form-section {
            background: #fff;
            border: 1px solid rgba(15, 23, 42, .08);
            border-radius: .5rem;
            box-shadow: 0 .5rem 1.5rem rgba(15, 23, 42, .06);
        }

        .section-title {
            color: #344054;
            font-size: .95rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .required {
            color: #dc3545;
            font-weight: 700;
        }

        /* Ajustes para Select2 com Bootstrap */
        .select2-container--bootstrap-5 .select2-selection {
            min-height: calc(2.5rem + 2px);
            font-size: 1rem;
        }

        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: calc(2.5rem + 2px);
        }

        .was-validated .select2-container--bootstrap-5 .select2-selection,
        .select2-container--bootstrap-5.invalid .select2-selection {
            border-color: #dc3545;
        }

        .form-section .select2-container--bootstrap-5 .select2-selection {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }

        .form-section .select2-container--bootstrap-5 .select2-selection:hover {
            border-color: #86b7fe;
        }
    </style>
</head>

<body class="sb-nav-fixed">
    <?php include __DIR__ . '/../includes/menu_superior_simples.php'; ?>

    <div id="layoutSidenav_content">
        <main class="container page-shell py-4">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 mb-4">
                <div>
                    <h1 class="h3 mb-1"><a href="./multasfrota.php"><i
                                class="fa-solid fa-arrow-left p-2"></i></a></i> Cadastro de Multa</h1>
                    <p class="text-muted mb-0">Preencha os dados da infração para registrar a multa.</p>
                </div>
                <span class="badge text-bg-light border align-self-start align-self-lg-center">
                    Usuário: <?= e($nome ?: $usuario) ?>
                </span>
            </div>

            <form method="post" action="../control/cadastrarmulta.php" id="formCadastroMulta" novalidate>
                <input type="hidden" name="matr_autor" id="matr_autor" value="<?= e($matricula) ?>">

                <section class="form-section p-3 p-md-4 mb-3">
                    <h2 class="section-title mb-3">Dados iniciais</h2>
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="placa">Placa <span class="required">*</span></label>
                            <select class="form-select select2-dropdown" name="placa" id="placa" required>
                                <option value="">Selecione a placa</option>
                                <?php foreach ($placas as $placa): ?>
                                    <option value="<?= e($placa['value']) ?>"> <?= e($placa['label']) ?> </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Selecione uma placa.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="filial">Filial <span class="required">*</span></label>
                            <select class="form-select select2-dropdown" name="filial" id="filial" required>
                                <option value="">Selecione a filial</option>
                                <?php foreach ($filiais as $filial): ?>
                                    <option value="<?= e($filial['value']) ?>"><?= e($filial['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Selecione uma filial.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="ccusto">Centro de custo <span
                                    class="required">*</span></label>
                            <select class="form-select select2-dropdown" name="ccusto" id="ccusto" required>
                                <option value="">Selecione o centro de custo</option>
                                <?php foreach ($centrosCusto as $centro): ?>
                                    <option value="<?= e($centro['value']) ?>"><?= e($centro['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Selecione um centro de custo.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="fornecedor">Fornecedor <span
                                    class="required">*</span></label>
                            <select class="form-select select2-dropdown" name="fornecedor" id="fornecedor" required>
                                <option value="">Selecione o fornecedor</option>
                                <?php foreach ($fornecedores as $fornecedor): ?>
                                    <option value="<?= e($fornecedor['value']) ?>"><?= e($fornecedor['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Selecione um fornecedor.</div>
                        </div>
                    </div>
                </section>

                <section class="form-section p-3 p-md-4 mb-3">
                    <h2 class="section-title mb-3">Infração</h2>
                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
                            <label class="form-label" for="anotacao">Anotação</label>
                            <textarea class="form-control" name="anotacao" id="anotacao" rows="3"
                                placeholder="Deixe aqui sua anotação"></textarea>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="tipoinfracao">Tipo de infração <span
                                    class="required">*</span></label>
                            <select name="tipoinfracao" id="tipoinfracao" class="form-select select2-dropdown" required>
                                <option value="">Selecione tipo de infração</option>
                                <option value="NOTIFICAÇÃO">Notificação</option>
                                <option value="MULTA">Multa</option>
                                <option value="MULTA FORA DO PRAZO">Multa fora do prazo</option>
                                <option value="AGRAVO">Agravo</option>
                            </select>
                            <div class="invalid-feedback">Selecione o tipo de infração.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="autoinfracao">Número do auto <span
                                    class="required">*</span></label>
                            <input type="text" class="form-control text-uppercase" name="autoinfracao" id="autoinfracao"
                                placeholder="Nº do auto de infração" required>
                            <div class="invalid-feedback">Informe o número do auto.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="datainfracao">Data da infração <span
                                    class="required">*</span></label>
                            <input type="date" class="form-control" name="datainfracao" id="datainfracao" required>
                            <div class="invalid-feedback">Informe a data da infração.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="horainfracao">Hora da infração <span
                                    class="required">*</span></label>
                            <input type="time" class="form-control" name="horainfracao" id="horainfracao" required>
                            <div class="invalid-feedback">Informe a hora da infração.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="datalimitecond">Limite para condutor <span
                                    class="required">*</span></label>
                            <input type="date" class="form-control" name="datalimitecond" id="datalimitecond" required>
                            <div class="invalid-feedback">Informe a data limite.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="datalimiteloc">Limite para locadora <span
                                    class="required">*</span></label>
                            <input type="date" class="form-control" name="datalimiteloc" id="datalimiteloc" required>
                            <div class="invalid-feedback">Informe a data limite.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="expedicao">Multa expedida em <span
                                    class="required">*</span></label>
                            <input type="date" class="form-control" name="expedicao" id="expedicao" required>
                            <div class="invalid-feedback">Informe a data de expedição.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="recebimento">Multa recebida em <span
                                    class="required">*</span></label>
                            <input type="date" class="form-control" name="recebimento" id="recebimento" required>
                            <div class="invalid-feedback">Informe a data de recebimento.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="vencimento">Vencimento da multa <span
                                    class="required">*</span></label>
                            <input type="date" class="form-control" name="vencimento" id="vencimento" required>
                            <div class="invalid-feedback">Informe a data de vencimento.</div>
                        </div>
                    </div>
                </section>

                <section class="form-section p-3 p-md-4 mb-3">
                    <h2 class="section-title mb-3">Valores e classificação</h2>
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="codigom">Código da multa <span
                                    class="required">*</span></label>
                            <input type="text" class="form-control" name="codigom" id="codigom" inputmode="numeric"
                                placeholder="000-00" required>
                            <div class="invalid-feedback">Informe o código da multa.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="pontos">Pontos <span class="required">*</span></label>
                            <input type="number" class="form-control" name="pontos" id="pontos" min="0" step="1"
                                placeholder="Nº de pontos" required>
                            <div class="invalid-feedback">Informe a pontuação.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="gravidade">Gravidade <span class="required">*</span></label>
                            <select name="gravidade" id="gravidade" class="form-select select2-dropdown" required>
                                <option value="">Selecione a gravidade</option>
                                <option value="LEVE">Leve</option>
                                <option value="MEDIA">Média</option>
                                <option value="GRAVE">Grave</option>
                                <option value="GRAVISSIMA">Gravíssima</option>
                            </select>
                            <div class="invalid-feedback">Selecione a gravidade.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="valor">Valor da multa <span class="required">*</span></label>
                            <input type="text" class="form-control valor" name="valor" id="valor" inputmode="decimal"
                                placeholder="R$ 0,00" required>
                            <div class="invalid-feedback">Informe o valor da multa.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="valdesconto">Valor do desconto</label>
                            <input type="text" class="form-control valor" name="valdesconto" id="valdesconto"
                                inputmode="decimal" placeholder="R$ 0,00">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="juros">Juros (%)</label>
                            <input type="text" class="form-control" name="juros" id="juros" inputmode="decimal"
                                placeholder="000.00" maxlength="6">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="valtotal">Valor total</label>
                            <input type="text" class="form-control valor" name="valtotal" id="valtotal"
                                inputmode="decimal" placeholder="R$ 0,00">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="valcomdesc">Valor com desconto</label>
                            <input type="text" class="form-control valor" name="valcomdesc" id="valcomdesc"
                                inputmode="decimal" placeholder="R$ 0,00">
                        </div>
                    </div>
                </section>

                <section class="form-section p-3 p-md-4 mb-3">
                    <h2 class="section-title mb-3">Local e condutor</h2>
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="orgao">Órgão autuador <span class="required">*</span></label>
                            <input type="text" class="form-control text-uppercase" name="orgao" id="orgao"
                                placeholder="Nome do órgão" required>
                            <div class="invalid-feedback">Informe o órgão autuador.</div>
                        </div>

                        <div class="col-12 col-lg-6">
                            <label class="form-label" for="endereco">Endereço <span class="required">*</span></label>
                            <input type="text" class="form-control text-uppercase" name="endereco" id="endereco"
                                placeholder="Endereço" required>
                            <div class="invalid-feedback">Informe o endereço.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="municipio">Município <span class="required">*</span></label>
                            <input type="text" class="form-control text-uppercase" name="municipio" id="municipio"
                                placeholder="Município" required>
                            <div class="invalid-feedback">Informe o município.</div>
                        </div>

                        <div class="col-12 col-lg-6">
                            <label class="form-label" for="descricao">Descrição da multa</label>
                            <textarea class="form-control" name="descricao" id="descricao" rows="3"
                                placeholder="Descrição da infração"></textarea>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="nomec">Nome do colaborador <span
                                    class="required">*</span></label>
                            <select class="form-select select2-dropdown" name="nomec" id="nomec" required>
                                <option value="">Nome do colaborador</option>
                                <?php foreach ($colaboradores as $colaborador): ?>
                                    <option value="<?= e($colaborador['value']) ?>">
                                        <?= e($colaborador['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Informe o condutor.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="matriculac">Matrícula do condutor <span
                                    class="required">*</span></label>
                            <input type="text" class="form-control" name="matriculac" id="matriculac"
                                inputmode="numeric" maxlength="6" placeholder="Matrícula" required disabled>
                            <div class="invalid-feedback">Informe a matrícula do condutor.</div>
                        </div>

                    </div>
                </section>

                <section class="form-section p-3 p-md-4 mb-3">
                    <h2 class="section-title mb-3">Recursos e protocolo</h2>
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="datarecorg">Data de recurso ao órgão</label>
                            <input type="date" class="form-control" name="datarecorg" id="datarecorg">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="protrecorg">Protocolo de recurso</label>
                            <input type="text" class="form-control" name="protrecorg" id="protrecorg">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="datanumprotocolo">Protocolo da multa</label>
                            <input type="text" class="form-control" name="datanumprotocolo" id="datanumprotocolo">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="expprotocolo">Dia de expedição</label>
                            <input type="date" class="form-control" name="expprotocolo" id="expprotocolo">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="recprotocolo">Dia de recebimento</label>
                            <input type="date" class="form-control" name="recprotocolo" id="recprotocolo">
                        </div>
                    </div>
                </section>

                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
                    <p class="text-danger mb-0"><strong>*</strong> Campos obrigatórios.</p>
                    <div>
                        <a class="btn btn-danger px-4" href="./multasfrota.php">
                            <i class="fa-solid fa-x me-2"></i>Cancelar
                        </a>
                        <button class="btn btn-success px-4" type="submit" id="btnSubmit">
                            <i class="fa-solid fa-check me-2"></i>Confirmar cadastro
                        </button>
                    </div>
                </div>
            </form>
        </main>

        <footer class="py-4 bg-white border-top">
            <div class="container page-shell">
                <div class="text-muted small">Copyright &copy; FFA Infraestrutura</div>
            </div>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/plentz/jquery-maskmoney@master/dist/jquery.maskMoney.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/pt-BR.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../src/js/scripts.js"></script>

    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', function (event) {
            event.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
        });
    </script>

    <script>
        (() => {
            'use strict';

            const form = document.getElementById('formCadastroMulta');
            const submitButton = document.getElementById('btnSubmit');
            const uppercaseFields = document.querySelectorAll('.text-uppercase');
            const numericFields = ['matriculac'];

            // Referências para os campos
            const selectColaborador = document.getElementById('nomec');
            const inputMatriculaCondutor = document.getElementById('matriculac');

            // Inicializar Select2 em todos os dropdowns
            $('.select2-dropdown').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: function () {
                    return $(this).data('placeholder') || 'Selecione uma opção';
                },
                allowClear: false,
                language: 'pt-BR'
            });

            // Função para preencher matrícula do condutor
            function preencherMatriculaCondutor() {
                // Para Select2, precisamos pegar o valor de forma diferente
                const matricula = $(selectColaborador).val();


                if (matricula && matricula !== '') {
                    inputMatriculaCondutor.value = matricula;
                    inputMatriculaCondutor.disabled = true;
                    inputMatriculaCondutor.classList.add('bg-light');

                    // Remover validação de required do campo desabilitado
                    inputMatriculaCondutor.removeAttribute('required');

                    // Adicionar um campo hidden para enviar a matrícula no formulário
                    let hiddenField = document.getElementById('matriculac_hidden');
                    if (!hiddenField) {
                        hiddenField = document.createElement('input');
                        hiddenField.type = 'hidden';
                        hiddenField.name = 'matriculac';
                        hiddenField.id = 'matriculac_hidden';
                        form.appendChild(hiddenField);
                    }
                    hiddenField.value = matricula;
                } else {
                    inputMatriculaCondutor.value = '';
                    inputMatriculaCondutor.disabled = false;
                    inputMatriculaCondutor.classList.remove('bg-light');
                    inputMatriculaCondutor.setAttribute('required', 'required');

                    // Remover o campo hidden se existir
                    const hiddenField = document.getElementById('matriculac_hidden');
                    if (hiddenField) {
                        hiddenField.remove();
                    }
                }
            }

            // Adicionar evento de change no select de colaborador usando jQuery para Select2
            if (selectColaborador) {
                // Usar o evento do jQuery para Select2
                $(selectColaborador).on('change', function (e) {
                    preencherMatriculaCondutor();
                });

                // Também funciona com o evento nativo do Select2
                $(selectColaborador).on('select2:select', function (e) {
                    preencherMatriculaCondutor();
                });

                // Se já tiver um valor selecionado, preencher automaticamente
                if ($(selectColaborador).val()) {
                    preencherMatriculaCondutor();
                }
            }

            uppercaseFields.forEach((field) => {
                field.addEventListener('input', () => {
                    field.value = field.value.toLocaleUpperCase('pt-BR');
                });
            });

            numericFields.forEach((id) => {
                const field = document.getElementById(id);
                if (!field) return;

                field.addEventListener('input', () => {
                    field.value = field.value.replace(/\D/g, '');
                });
            });

            $('#codigom').mask('000-00');
            $('.valor').maskMoney({
                prefix: 'R$ ',
                allowNegative: false,
                thousands: '.',
                decimal: ',',
                affixesStay: true
            });
            $('#juros').maskMoney({
                allowNegative: false,
                decimal: '.',
                affixesStay: false
            });

            form.addEventListener('submit', (event) => {
                // Validar selects do Select2
                let isValid = true;
                const selects = document.querySelectorAll('.select2-dropdown');
                selects.forEach(select => {
                    if (select.hasAttribute('required') && !select.value) {
                        select.classList.add('is-invalid');
                        $(select).next('.select2-container').addClass('invalid');
                        isValid = false;
                    } else {
                        select.classList.remove('is-invalid');
                        $(select).next('.select2-container').removeClass('invalid');
                    }
                });

                if (!form.checkValidity() || !isValid) {
                    event.preventDefault();
                    event.stopPropagation();
                    form.classList.add('was-validated');
                    return;
                }

                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Processando...';
            });
        })();
    </script>
</body>

</html>

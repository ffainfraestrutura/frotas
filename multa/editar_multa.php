<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

const APP_DEBUG = false;

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

$nome = (string) ($_SESSION['nome'] ?? '');
$usuario = (string) ($_SESSION['usuario'] ?? '');
$matricula = (string) ($_SESSION['matricula'] ?? '');
$tipo = (string) ($_SESSION['tipo'] ?? '');

require_once '../control/conecta.php';

if (isset($conn) && $conn instanceof mysqli) {
    mysqli_set_charset($conn, 'utf8mb4');
}

header('Content-Type: text/html; charset=utf-8');

// FUNÇÃO CORRIGIDA - ACEITA QUALQUER TIPO
function e($value): string
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

// Validar ID da URL
$idmulta = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
// if (!$idmulta) {
//     header('Location: multasfrota.php');
//     exit;
// }

// Buscar dados da multa com JOIN na tbmovidatramite
$sqlMulta = "
    SELECT 
        m.*,
        mt.idtbmovidatramite,
        mt.dtcons,
        mt.apCondDV,
        mt.tramite,
        mt.tdp,
        mt.tsup,
        mt.tfin,
        mt.reciboass,
        mt.ndesc,
        mt.descontocol,
        mt.dtdesconto,
        mt.dtpag,
        mt.multapaga,
        mt.emailconfdp,
        mt.recibo,
        mt.locadora,
        mt.idmulta,
        mt.dataenvioemail,
        mt.parecer,
        mt.parecerpor,
        mt.parecerdp,
        mt.parecerpordp,
        mt.termodetran,
        mt.recibolocass,
        mt.dt_envio_dp
    FROM tbmulta m
    LEFT JOIN tbmovidatramite mt ON m.idtbmulta = mt.idmulta
    WHERE mt.idtbmovidatramite = ?
    LIMIT 1
";

$stmtMulta = mysqli_prepare($conn, $sqlMulta);
mysqli_stmt_bind_param($stmtMulta, 'i', $idmulta);
mysqli_stmt_execute($stmtMulta);
$resultMulta = mysqli_stmt_get_result($stmtMulta);
$multa = mysqli_fetch_assoc($resultMulta);

// if (!$multa) {
//     header('Location: multasfrota.php');
//     exit;
// }

$colaboradores = carregarOpcoes(
    $conn,
    'SELECT matricula, nome FROM tbfuncionario WHERE status = "Ativo" ORDER BY nome',
    'matricula',
    'nome'
);

$filiais = carregarOpcoes(
    $conn,
    'SELECT idtbfilial, descricao AS nome FROM tbfilial ORDER BY descricao',
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
    'SELECT placa, placa FROM tbveiculo WHERE statusvel = 1 AND tipoposse != "particular" ORDER BY placa',
    'placa',
    'placa'
);

// Funções auxiliares
function getValue($array, $key, $default = '') {
    return isset($array[$key]) ? $array[$key] : $default;
}

function formatDate($date) {
    if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
        return '';
    }
    return date('Y-m-d', strtotime($date));
}

function formatTime($time) {
    if (empty($time)) {
        return '';
    }
    return substr($time, 0, 5);
}

function formatMoney($value) {
    if (empty($value)) {
        return '0,00';
    }
    return number_format((float)$value, 2, ',', '.');
}

function isSelected($valor1, $valor2) {
    return (string)$valor1 === (string)$valor2;
}
?>
<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Edição de multa">
    <meta name="author" content="FFA">
    <link rel="icon" type="image/png" href="../src/images/favicon.png">
    <title>Editar Multa</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="../src/css/styles.css" rel="stylesheet">

    <style>
        body { background: #f5f7fb; }
        a { text-decoration: none; color: black; }
        .fa-arrow-left { background-color: #fff; border-radius: 50%; }
        .page-shell { max-width: 1180px; }
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
        .required { color: #dc3545; font-weight: 700; }
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
        .info-badge {
            font-size: 0.8rem;
            background: #e9ecef;
            padding: 2px 10px;
            border-radius: 12px;
            color: #495057;
        }
    </style>
</head>

<body class="sb-nav-fixed">
    <?php include __DIR__ . '/../includes/menu_superior_simples.php'; ?>

    <div id="layoutSidenav_content">
        <main class="container page-shell py-4">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 mb-4">
                <div>
                    <h1 class="h3 mb-1">
                        <a href="./multasfrota.php"><i class="fa-solid fa-arrow-left p-2"></i></a>
                        Editar Multa
                    </h1>
                    <p class="text-muted mb-0">
                        Atualize os dados da infração cadastrada.
                        <?php if (getValue($multa, 'idtbmovidatramite')): ?>
                            <span class="info-badge ms-2">
                                <i class="fa-regular fa-file-lines me-1"></i>
                                ID Trâmite: <?= e(getValue($multa, 'idtbmovidatramite')) ?>
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
                <span class="badge text-bg-light border align-self-start align-self-lg-center">
                    Usuário: <?= e($nome ?: $usuario) ?>
                </span>
            </div>

            <form method="post" action="../control/editarmulta.php" id="formEditarMulta" novalidate>
                <input type="hidden" name="idtbmulta" value="<?= e(getValue($multa, 'idtbmulta')) ?>">
                <input type="hidden" name="matr_autor" value="<?= e($matricula) ?>">
                <?php if (getValue($multa, 'idtbmovidatramite')): ?>
                    <input type="hidden" name="idtbmovidatramite" value="<?= e(getValue($multa, 'idtbmovidatramite')) ?>">
                <?php endif; ?>

                <!-- Dados iniciais -->
                <section class="form-section p-3 p-md-4 mb-3">
                    <h2 class="section-title mb-3">Dados iniciais</h2>
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="placa">Placa <span class="required">*</span></label>
                            <select class="form-select select2-dropdown" name="placa" id="placa" required>
                                <option value="">Selecione a placa</option>
                                <?php foreach ($placas as $placa): ?>
                                    <option value="<?= e($placa['value']) ?>" <?= isSelected(getValue($multa, 'placa'), $placa['value']) ? 'selected' : '' ?>>
                                        <?= e($placa['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Selecione uma placa.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="filial">Filial <span class="required">*</span></label>
                            <select class="form-select select2-dropdown" name="filial" id="filial" required>
                                <option value="">Selecione a filial</option>
                                <?php foreach ($filiais as $filial): ?>
                                    <option value="<?= e($filial['value']) ?>" <?= isSelected(getValue($multa, 'filial'), $filial['value']) ? 'selected' : '' ?>>
                                        <?= e($filial['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Selecione uma filial.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="ccusto">Centro de custo <span class="required">*</span></label>
                            <select class="form-select select2-dropdown" name="ccusto" id="ccusto" required>
                                <option value="">Selecione o centro de custo</option>
                                <?php foreach ($centrosCusto as $centro): ?>
                                    <option value="<?= e($centro['value']) ?>" <?= isSelected(getValue($multa, 'ccusto'), $centro['value']) ? 'selected' : '' ?>>
                                        <?= e($centro['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Selecione um centro de custo.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="fornecedor">Fornecedor <span class="required">*</span></label>
                            <select class="form-select select2-dropdown" name="fornecedor" id="fornecedor" required>
                                <option value="">Selecione o fornecedor</option>
                                <?php foreach ($fornecedores as $fornecedor): ?>
                                    <option value="<?= e($fornecedor['value']) ?>" <?= isSelected(getValue($multa, 'fornecedor'), $fornecedor['value']) ? 'selected' : '' ?>>
                                        <?= e($fornecedor['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Selecione um fornecedor.</div>
                        </div>
                    </div>
                </section>

                <!-- Infração -->
                <section class="form-section p-3 p-md-4 mb-3">
                    <h2 class="section-title mb-3">Infração</h2>
                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
                            <label class="form-label" for="anotacao">Anotação</label>
                            <textarea class="form-control" name="anotacao" id="anotacao" rows="3"
                                placeholder="Deixe aqui sua anotação"><?= e(getValue($multa, 'anotacao')) ?></textarea>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="tipoinfracao">Tipo de infração <span class="required">*</span></label>
                            <select name="tipoinfracao" id="tipoinfracao" class="form-select select2-dropdown" required>
                                <option value="">Selecione tipo de infração</option>
                                <option value="NOTIFICAÇÃO" <?= isSelected(getValue($multa, 'tipoinfracao'), 'NOTIFICAÇÃO') ? 'selected' : '' ?>>Notificação</option>
                                <option value="MULTA" <?= isSelected(getValue($multa, 'tipoinfracao'), 'MULTA') ? 'selected' : '' ?>>Multa</option>
                                <option value="MULTA FORA DO PRAZO" <?= isSelected(getValue($multa, 'tipoinfracao'), 'MULTA FORA DO PRAZO') ? 'selected' : '' ?>>Multa fora do prazo</option>
                                <option value="AGRAVO" <?= isSelected(getValue($multa, 'tipoinfracao'), 'AGRAVO') ? 'selected' : '' ?>>Agravo</option>
                            </select>
                            <div class="invalid-feedback">Selecione o tipo de infração.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="autoinfracao">Número do auto <span class="required">*</span></label>
                            <input type="text" class="form-control text-uppercase" name="autoinfracao" id="autoinfracao"
                                placeholder="Nº do auto de infração" value="<?= e(getValue($multa, 'autoinfracao')) ?>" required>
                            <div class="invalid-feedback">Informe o número do auto.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="datainfracao">Data da infração <span class="required">*</span></label>
                            <input type="date" class="form-control" name="datainfracao" id="datainfracao"
                                value="<?= e(formatDate(getValue($multa, 'datainfracao'))) ?>" required>
                            <div class="invalid-feedback">Informe a data da infração.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="horainfracao">Hora da infração <span class="required">*</span></label>
                            <input type="time" class="form-control" name="horainfracao" id="horainfracao"
                                value="<?= e(formatTime(getValue($multa, 'horainfracao'))) ?>" required>
                            <div class="invalid-feedback">Informe a hora da infração.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="datalimitecond">Limite para condutor <span class="required">*</span></label>
                            <input type="date" class="form-control" name="datalimitecond" id="datalimitecond"
                                value="<?= e(formatDate(getValue($multa, 'datalimitecond'))) ?>" required>
                            <div class="invalid-feedback">Informe a data limite.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="datalimiteloc">Limite para locadora <span class="required">*</span></label>
                            <input type="date" class="form-control" name="datalimiteloc" id="datalimiteloc"
                                value="<?= e(formatDate(getValue($multa, 'datalimiteloc'))) ?>" required>
                            <div class="invalid-feedback">Informe a data limite.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="expedicao">Multa expedida em <span class="required">*</span></label>
                            <input type="date" class="form-control" name="expedicao" id="expedicao"
                                value="<?= e(formatDate(getValue($multa, 'expedicao'))) ?>" required>
                            <div class="invalid-feedback">Informe a data de expedição.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="recebimento">Multa recebida em <span class="required">*</span></label>
                            <input type="date" class="form-control" name="recebimento" id="recebimento"
                                value="<?= e(formatDate(getValue($multa, 'recebimento'))) ?>" required>
                            <div class="invalid-feedback">Informe a data de recebimento.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="vencimento">Vencimento da multa <span class="required">*</span></label>
                            <input type="date" class="form-control" name="vencimento" id="vencimento"
                                value="<?= e(formatDate(getValue($multa, 'vencimento'))) ?>" required>
                            <div class="invalid-feedback">Informe a data de vencimento.</div>
                        </div>
                    </div>
                </section>

                <!-- Valores e classificação -->
                <section class="form-section p-3 p-md-4 mb-3">
                    <h2 class="section-title mb-3">Valores e classificação</h2>
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="codigom">Código da multa <span class="required">*</span></label>
                            <input type="text" class="form-control" name="codigom" id="codigom" inputmode="numeric"
                                placeholder="000-00" value="<?= e(getValue($multa, 'codigom')) ?>" required>
                            <div class="invalid-feedback">Informe o código da multa.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="pontos">Pontos <span class="required">*</span></label>
                            <input type="number" class="form-control" name="pontos" id="pontos" min="0" step="1"
                                placeholder="Nº de pontos" value="<?= e(getValue($multa, 'pontos')) ?>" required>
                            <div class="invalid-feedback">Informe a pontuação.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="gravidade">Gravidade <span class="required">*</span></label>
                            <select name="gravidade" id="gravidade" class="form-select select2-dropdown" required>
                                <option value="">Selecione a gravidade</option>
                                <option value="LEVE" <?= isSelected(getValue($multa, 'gravidade'), 'LEVE') ? 'selected' : '' ?>>Leve</option>
                                <option value="MEDIA" <?= isSelected(getValue($multa, 'gravidade'), 'MEDIA') ? 'selected' : '' ?>>Média</option>
                                <option value="GRAVE" <?= isSelected(getValue($multa, 'gravidade'), 'GRAVE') ? 'selected' : '' ?>>Grave</option>
                                <option value="GRAVISSIMA" <?= isSelected(getValue($multa, 'gravidade'), 'GRAVISSIMA') ? 'selected' : '' ?>>Gravíssima</option>
                            </select>
                            <div class="invalid-feedback">Selecione a gravidade.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="valor">Valor da multa <span class="required">*</span></label>
                            <input type="text" class="form-control valor" name="valor" id="valor" inputmode="decimal"
                                placeholder="R$ 0,00" value="R$ <?= e(formatMoney(getValue($multa, 'valor'))) ?>" required>
                            <div class="invalid-feedback">Informe o valor da multa.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="valdesconto">Valor do desconto</label>
                            <input type="text" class="form-control valor" name="valdesconto" id="valdesconto"
                                inputmode="decimal" placeholder="R$ 0,00" value="R$ <?= e(formatMoney(getValue($multa, 'valdesconto'))) ?>">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="juros">Juros (%)</label>
                            <input type="text" class="form-control" name="juros" id="juros" inputmode="decimal"
                                placeholder="000.00" maxlength="6" value="<?= e(getValue($multa, 'juros')) ?>">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="valtotal">Valor total</label>
                            <input type="text" class="form-control valor" name="valtotal" id="valtotal"
                                inputmode="decimal" placeholder="R$ 0,00" value="R$ <?= e(formatMoney(getValue($multa, 'valtotal'))) ?>">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="valcomdesc">Valor com desconto</label>
                            <input type="text" class="form-control valor" name="valcomdesc" id="valcomdesc"
                                inputmode="decimal" placeholder="R$ 0,00" value="R$ <?= e(formatMoney(getValue($multa, 'valcomdesc'))) ?>">
                        </div>
                    </div>
                </section>

                <!-- Local e condutor -->
                <section class="form-section p-3 p-md-4 mb-3">
                    <h2 class="section-title mb-3">Local e condutor</h2>
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="orgao">Órgão autuador <span class="required">*</span></label>
                            <input type="text" class="form-control text-uppercase" name="orgao" id="orgao"
                                placeholder="Nome do órgão" value="<?= e(getValue($multa, 'orgao')) ?>" required>
                            <div class="invalid-feedback">Informe o órgão autuador.</div>
                        </div>

                        <div class="col-12 col-lg-6">
                            <label class="form-label" for="endereco">Endereço <span class="required">*</span></label>
                            <input type="text" class="form-control text-uppercase" name="endereco" id="endereco"
                                placeholder="Endereço" value="<?= e(getValue($multa, 'endereco')) ?>" required>
                            <div class="invalid-feedback">Informe o endereço.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="municipio">Município <span class="required">*</span></label>
                            <input type="text" class="form-control text-uppercase" name="municipio" id="municipio"
                                placeholder="Município" value="<?= e(getValue($multa, 'municipio')) ?>" required>
                            <div class="invalid-feedback">Informe o município.</div>
                        </div>

                        <div class="col-12 col-lg-6">
                            <label class="form-label" for="descricao">Descrição da multa</label>
                            <textarea class="form-control" name="descricao" id="descricao" rows="3"
                                placeholder="Descrição da infração"><?= e(getValue($multa, 'descricao')) ?></textarea>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="nomec">Nome do colaborador <span class="required">*</span></label>
                            <select class="form-select select2-dropdown" name="nomec" id="nomec" required>
                                <option value="">Nome do colaborador</option>
                                <?php foreach ($colaboradores as $colaborador): ?>
                                    <option value="<?= e($colaborador['value']) ?>" <?= isSelected(getValue($multa, 'matriculac'), $colaborador['value']) ? 'selected' : '' ?>>
                                        <?= e($colaborador['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Informe o condutor.</div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="matriculac">Matrícula do condutor <span class="required">*</span></label>
                            <input type="text" class="form-control" name="matriculac_display" id="matriculac"
                                inputmode="numeric" maxlength="6" placeholder="Matrícula"
                                value="<?= e(getValue($multa, 'matriculac')) ?>" disabled>
                            <input type="hidden" name="matriculac" id="matriculac_hidden" value="<?= e(getValue($multa, 'matriculac')) ?>">
                            <div class="invalid-feedback">Informe a matrícula do condutor.</div>
                        </div>
                    </div>
                </section>

                <!-- Recursos e protocolo -->
                <section class="form-section p-3 p-md-4 mb-3">
                    <h2 class="section-title mb-3">Recursos e protocolo</h2>
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="datarecorg">Data de recurso ao órgão</label>
                            <input type="date" class="form-control" name="datarecorg" id="datarecorg"
                                value="<?= e(formatDate(getValue($multa, 'datarecorg'))) ?>">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="protrecorg">Protocolo de recurso</label>
                            <input type="text" class="form-control" name="protrecorg" id="protrecorg"
                                value="<?= e(getValue($multa, 'protrecorg')) ?>">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="datanumprotocolo">Protocolo da multa</label>
                            <input type="text" class="form-control" name="datanumprotocolo" id="datanumprotocolo"
                                value="<?= e(getValue($multa, 'datanumprotocolo')) ?>">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="expprotocolo">Dia de expedição</label>
                            <input type="date" class="form-control" name="expprotocolo" id="expprotocolo"
                                value="<?= e(formatDate(getValue($multa, 'expprotocolo'))) ?>">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="recprotocolo">Dia de recebimento</label>
                            <input type="date" class="form-control" name="recprotocolo" id="recprotocolo"
                                value="<?= e(formatDate(getValue($multa, 'recprotocolo'))) ?>">
                        </div>
                    </div>
                </section>

                <!-- Dados da tbmovidatramite (se existir) -->
                <!-- <?php if (getValue($multa, 'idtbmovidatramite')): ?>
                <section class="form-section p-3 p-md-4 mb-3">
                    <h2 class="section-title mb-3">
                        <i class="fa-regular fa-clock me-2" style="color: #6c757d;"></i>
                        Dados do Trâmite
                    </h2>
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="tramite">Tramitação</label>
                            <input type="text" class="form-control" name="tramite" id="tramite"
                                value="<?= e(getValue($multa, 'tramite')) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="tdp">TDP</label>
                            <input type="text" class="form-control" name="tdp" id="tdp"
                                value="<?= e(getValue($multa, 'tdp')) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="tsup">TSUP</label>
                            <input type="text" class="form-control" name="tsup" id="tsup"
                                value="<?= e(getValue($multa, 'tsup')) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="tfin">TFIN</label>
                            <input type="text" class="form-control" name="tfin" id="tfin"
                                value="<?= e(getValue($multa, 'tfin')) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="reciboass">Recibo Assinado</label>
                            <input type="text" class="form-control" name="reciboass" id="reciboass"
                                value="<?= e(getValue($multa, 'reciboass')) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="ndesc">Nº Desconto</label>
                            <input type="text" class="form-control" name="ndesc" id="ndesc"
                                value="<?= e(getValue($multa, 'ndesc')) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="descontocol">Desconto Colaborador</label>
                            <input type="text" class="form-control" name="descontocol" id="descontocol"
                                value="<?= e(getValue($multa, 'descontocol')) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="dtdesconto">Data Desconto</label>
                            <input type="text" class="form-control" name="dtdesconto" id="dtdesconto"
                                value="<?= e(formatDate(getValue($multa, 'dtdesconto'))) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="dtpag">Data Pagamento</label>
                            <input type="text" class="form-control" name="dtpag" id="dtpag"
                                value="<?= e(formatDate(getValue($multa, 'dtpag'))) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="multapaga">Multa Paga</label>
                            <input type="text" class="form-control" name="multapaga" id="multapaga"
                                value="<?= e(getValue($multa, 'multapaga')) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="emailconfdp">Email Conf. DP</label>
                            <input type="text" class="form-control" name="emailconfdp" id="emailconfdp"
                                value="<?= e(getValue($multa, 'emailconfdp')) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="recibo">Recibo</label>
                            <input type="text" class="form-control" name="recibo" id="recibo"
                                value="<?= e(getValue($multa, 'recibo')) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="locadora">Locadora</label>
                            <input type="text" class="form-control" name="locadora" id="locadora"
                                value="<?= e(getValue($multa, 'locadora')) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="dataenvioemail">Data Envio Email</label>
                            <input type="text" class="form-control" name="dataenvioemail" id="dataenvioemail"
                                value="<?= e(formatDate(getValue($multa, 'dataenvioemail'))) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="parecer">Parecer</label>
                            <input type="text" class="form-control" name="parecer" id="parecer"
                                value="<?= e(getValue($multa, 'parecer')) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="parecerpor">Parecer por</label>
                            <input type="text" class="form-control" name="parecerpor" id="parecerpor"
                                value="<?= e(getValue($multa, 'parecerpor')) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="parecerdp">Parecer DP</label>
                            <input type="text" class="form-control" name="parecerdp" id="parecerdp"
                                value="<?= e(getValue($multa, 'parecerdp')) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="parecerpordp">Parecer por DP</label>
                            <input type="text" class="form-control" name="parecerpordp" id="parecerpordp"
                                value="<?= e(getValue($multa, 'parecerpordp')) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="termodetran">Termo DETRAN</label>
                            <input type="text" class="form-control" name="termodetran" id="termodetran"
                                value="<?= e(getValue($multa, 'termodetran')) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="recibolocass">Recibo Locadora</label>
                            <input type="text" class="form-control" name="recibolocass" id="recibolocass"
                                value="<?= e(getValue($multa, 'recibolocass')) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="dt_envio_dp">Data Envio DP</label>
                            <input type="text" class="form-control" name="dt_envio_dp" id="dt_envio_dp"
                                value="<?= e(formatDate(getValue($multa, 'dt_envio_dp'))) ?>" readonly>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label" for="apCondDV">Ap. Condutor DV</label>
                            <input type="text" class="form-control" name="apCondDV" id="apCondDV"
                                value="<?= e(formatDate(getValue($multa, 'apCondDV'))) ?>" readonly>
                        </div>
                    </div>
                </section>
                <?php endif; ?> -->

                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
                    <p class="text-danger mb-0"><strong>*</strong> Campos obrigatórios.</p>
                    <div>
                        <a class="btn btn-danger px-4" href="./multasfrota.php">
                            <i class="fa-solid fa-x me-2"></i>Cancelar
                        </a>
                        <button class="btn btn-success px-4" type="submit" id="btnSubmit">
                            <i class="fa-solid fa-check me-2"></i>Salvar alterações
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

            const form = document.getElementById('formEditarMulta');
            const submitButton = document.getElementById('btnSubmit');
            const uppercaseFields = document.querySelectorAll('.text-uppercase');

            const selectColaborador = document.getElementById('nomec');
            const inputMatriculaCondutor = document.getElementById('matriculac');
            const inputMatriculaHidden = document.getElementById('matriculac_hidden');

            $('.select2-dropdown').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: function () {
                    return $(this).data('placeholder') || 'Selecione uma opção';
                },
                allowClear: false,
                language: 'pt-BR'
            });

            function preencherMatriculaCondutor() {
                const matricula = $(selectColaborador).val();

                if (matricula && matricula !== '') {
                    inputMatriculaCondutor.value = matricula;
                    inputMatriculaHidden.value = matricula;
                } else {
                    inputMatriculaCondutor.value = '';
                    inputMatriculaHidden.value = '';
                }
            }

            if (selectColaborador) {
                $(selectColaborador).on('change', function (e) {
                    preencherMatriculaCondutor();
                });

                $(selectColaborador).on('select2:select', function (e) {
                    preencherMatriculaCondutor();
                });
            }

            uppercaseFields.forEach((field) => {
                field.addEventListener('input', () => {
                    field.value = field.value.toLocaleUpperCase('pt-BR');
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
<?php
require_once __DIR__ . '/../auth.php';

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

require_once __DIR__ . '/../control/conecta.php';
exigirLogin();

$databaseCorp = trim((string) ($databaseCorp ?? ($GLOBALS['databaseCorp'] ?? '')));
if ($databaseCorp === '') {
    $databaseCorp = 'bdcorp';
}

date_default_timezone_set('America/Sao_Paulo');

$matriculaLogada = (string) ($_POST['matr_autor'] ?? $_SESSION['matricula'] ?? $_SESSION['usuario'] ?? '');
$perfilLogado = (string) ($_POST['perfil_autor'] ?? $_SESSION['perfil'] ?? '');
$usuarioLogado = $_SESSION['usuario'] ?? 'Usuário';
$mensagemRetorno = trim((string) ($_GET['msg'] ?? ''));

function esc(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function consultarOpcoes(mysqli $conn, string $sql): array
{
    $resultado = mysqli_query($conn, $sql);
    if (!$resultado) {
        return [];
    }

    $linhas = [];
    while ($linha = mysqli_fetch_assoc($resultado)) {
        $linhas[] = $linha;
    }
    mysqli_free_result($resultado);

    return $linhas;
}

function opcoesSimNao(): array
{
    return ['SIM' => 'SIM', 'NÃO' => 'NÃO'];
}

function opcoesZeroKm(): array
{
    return ['0' => 'Não', '1' => 'Sim'];
}

function montarOpcoesTabela(array $linhas, array $camposIdPreferidos, array $camposTextoPreferidos): array
{
    $opcoes = [];

    foreach ($linhas as $linha) {
        if (!is_array($linha) || $linha === []) {
            continue;
        }

        $campoId = '';
        foreach ($camposIdPreferidos as $candidato) {
            if (array_key_exists($candidato, $linha)) {
                $campoId = $candidato;
                break;
            }
        }
        if ($campoId === '') {
            $chaves = array_keys($linha);
            $campoId = (string) ($chaves[0] ?? '');
        }

        $campoTexto = '';
        foreach ($camposTextoPreferidos as $candidato) {
            if (array_key_exists($candidato, $linha)) {
                $campoTexto = $candidato;
                break;
            }
        }
        if ($campoTexto === '') {
            $chaves = array_keys($linha);
            $campoTexto = (string) ($chaves[1] ?? ($chaves[0] ?? ''));
        }

        $valor = trim((string) ($linha[$campoId] ?? ''));
        $texto = trim((string) ($linha[$campoTexto] ?? ''));
        if ($valor === '' || $texto === '') {
            continue;
        }

        $opcoes[] = [
            'valor' => $valor,
            'descricao' => $texto,
        ];
    }

    return $opcoes;
}

function renderSelect(string $name, string $label, array $options, string $valueKey = '', string $textKey = '', bool $required = false, string $extraClass = '', string $placeholder = 'Selecione...', string $selectedValue = '', bool $disabled = false): void
{
    $requiredAttr = $required ? ' required' : '';
    $disabledAttr = $disabled ? ' disabled aria-disabled="true"' : '';
    echo '<div class="col-md-4 ' . esc($extraClass) . '">';
    echo '<label class="form-label" for="' . esc($name) . '">' . esc($label) . ($required ? ' <span class="text-danger">*</span>' : '') . '</label>';
    echo '<select class="form-select" name="' . esc($name) . '" id="' . esc($name) . '"' . $requiredAttr . $disabledAttr . '>';
    echo '<option value="">' . esc($placeholder) . '</option>';

    foreach ($options as $valor => $option) {
        if (is_array($option)) {
            $value = $valueKey !== '' ? (string) ($option[$valueKey] ?? '') : (string) $valor;
            $text = $textKey !== '' ? (string) ($option[$textKey] ?? '') : implode(' - ', array_filter($option));
        } else {
            $value = (string) $valor;
            $text = (string) $option;
        }

        if ($value !== '') {
            echo '<option value="' . esc($value) . '"' . ($selectedValue === $value ? ' selected' : '') . '>' . esc($text) . '</option>';
        }
    }

    echo '</select>';
    if ($disabled) {
        echo '<input type="hidden" name="' . esc($name) . '" value="' . esc($selectedValue) . '">';
    }
    echo '</div>';
}

function renderInput(string $name, string $label, string $type = 'text', bool $required = false, string $extra = ''): void
{
    $requiredAttr = $required ? ' required' : '';
    $extraNormalizado = trim($extra);

    if ($extraNormalizado !== '' && stripos($extraNormalizado, 'placeholder=') === false) {
        $labelLimpo = preg_replace('/\s*\*+\s*/', '', $label);
        $labelLimpo = preg_replace('/\?+$/', '', (string) $labelLimpo);
        $labelLimpo = trim((string) $labelLimpo);

        if ($labelLimpo !== '') {
            $extraNormalizado .= ' placeholder="Informe ' . esc(mb_strtolower($labelLimpo, 'UTF-8')) . '"';
        }
    }

    if ($extraNormalizado === '') {
        $labelLimpo = preg_replace('/\s*\*+\s*/', '', $label);
        $labelLimpo = preg_replace('/\?+$/', '', (string) $labelLimpo);
        $labelLimpo = trim((string) $labelLimpo);

        if ($labelLimpo !== '') {
            $extraNormalizado = 'placeholder="Informe ' . esc(mb_strtolower($labelLimpo, 'UTF-8')) . '"';
        }
    }

    echo '<div class="col-md-4">';
    echo '<label class="form-label" for="' . esc($name) . '">' . esc($label) . ($required ? ' <span class="text-danger">*</span>' : '') . '</label>';
    echo '<input class="form-control" type="' . esc($type) . '" name="' . esc($name) . '" id="' . esc($name) . '"' . $requiredAttr . ($extraNormalizado !== '' ? ' ' . $extraNormalizado : '') . '>';
    echo '</div>';
}

$aplicacoes = [];
$modelos = [];
$statusCadastro = [];
$statusVeiculo = [];
$basesGestao = [];
$unidades = [];
$centrosCusto = [];
$blindagens = [];
$categoriasVeiculo = [];
$classificacoesVeiculo = [];
$marcasVeiculo = [];
$tiposVeiculo = [];
$opcoesGpsEmpresa = [];

if (isset($conn) && $conn instanceof mysqli) {
    $aplicacoes = consultarOpcoes($conn, "
        SELECT idtbaplicacaoveic, aplicacao
        FROM `{$databaseName}`.`tbveiculoaplicacao`
        ORDER BY aplicacao
    ");

    $modelos = consultarOpcoes($conn, "
        SELECT idtbmodeloveic, marca, modelo
        FROM `{$databaseName}`.`tbveiculomodelo`
        WHERE modelo IS NOT NULL AND modelo <> ''
        ORDER BY modelo
    ");

    $statusCadastro = consultarOpcoes($conn, "
        SELECT *
        FROM `{$databaseName}`.`tbveiculostatus`
        ORDER BY 1
    ");

    $statusVeiculo = consultarOpcoes($conn, "
        SELECT *
        FROM `{$databaseName}`.`tbvelstatus`
        ORDER BY 1
    ");

    $basesGestao = consultarOpcoes($conn, "
        SELECT DISTINCT basegestao
        FROM `{$databaseName}`.`tbveiculo`
        WHERE basegestao IS NOT NULL AND basegestao <> ''
        ORDER BY basegestao
    ");

    $unidades = consultarOpcoes($conn, "
        SELECT DISTINCT unidade
        FROM `{$databaseName}`.`tbveiculo`
        WHERE unidade IS NOT NULL AND unidade <> ''
        ORDER BY unidade
    ");

    $centrosCusto = consultarOpcoes($conn, "
        SELECT DISTINCT ccusto
        FROM `{$databaseCorp}`.`tbfuncionario`
        WHERE idtbempresa = 2
          AND matricula LIKE '16%'
          AND CHAR_LENGTH(matricula) = 7
          AND status <> 'Demitido'
          AND ccusto IS NOT NULL
          AND ccusto <> ''
        ORDER BY ccusto
    ");

    $blindagens = consultarOpcoes($conn, "
        SELECT DISTINCT blindagem
        FROM `{$databaseName}`.`tbveiculo`
        WHERE blindagem IS NOT NULL AND blindagem <> ''
        ORDER BY blindagem
    ");

    $categoriasVeiculo = consultarOpcoes($conn, "
        SELECT *
        FROM `{$databaseName}`.`tbveiculocategoria`
        ORDER BY 1
    ");

    $classificacoesVeiculo = consultarOpcoes($conn, "
        SELECT *
        FROM `{$databaseName}`.`tbveiculoclassificacao`
        ORDER BY 1
    ");

    $marcasVeiculo = consultarOpcoes($conn, "
        SELECT *
        FROM `{$databaseName}`.`tbveiculomarca`
        ORDER BY 1
    ");

    $tiposVeiculo = consultarOpcoes($conn, "
        SELECT *
        FROM `{$databaseName}`.`tbveltipo`
        ORDER BY 1
    ");

    $fornecedoresGps = consultarOpcoes($conn, "
        SELECT *
        FROM `{$databaseName}`.`tbfornecedor`
        WHERE tipo = '5'
        ORDER BY fantasia
    ");

    $opcoesGpsEmpresa = montarOpcoesTabela(
        $fornecedoresGps,
        ['idtbfornecedor', 'idfornecedor', 'idtb_fornecedor', 'id'],
        ['fantasia', 'nomefantasia', 'nome_fantasia', 'nome', 'razaosocial', 'razao_social']
    );

    $opcoesGpsEmpresa = array_values(array_filter(array_map(static function (array $fornecedor): ?array {
        $valor = trim((string) ($fornecedor['valor'] ?? ''));
        $descricao = trim((string) ($fornecedor['descricao'] ?? ''));
        if ($valor === '' || $descricao === '' || !ctype_digit($valor)) {
            return null;
        }

        return [
            'valor' => $valor,
            'descricao' => $descricao,
        ];
    }, $opcoesGpsEmpresa)));

    $opcoesGpsEmpresa[] = ['valor' => '0', 'descricao' => 'Não possui'];
}

$modelosFormatados = array_map(static function (array $modelo): array {
    $nome = trim((string) ($modelo['modelo'] ?? ''));
    $modelo['descricao'] = $nome;
    return $modelo;
}, $modelos);

$categoriasFormatadas = montarOpcoesTabela(
    $categoriasVeiculo,
    ['idtbveiculocategoria', 'idcategoria', 'id'],
    ['categoria', 'descricao', 'nome']
);

$statusCadastroFormatado = montarOpcoesTabela(
    $statusCadastro,
    ['idtbveiculostatus', 'idtbstatusveic', 'idstatus', 'id'],
    ['status', 'descricao', 'nome']
);

$statusVeiculoFormatado = montarOpcoesTabela(
    $statusVeiculo,
    ['idtbvelstatus', 'idtbstatusvel', 'idtbstatusveic', 'id'],
    ['status', 'descricao', 'nome']
);

$classificacoesFormatadas = montarOpcoesTabela(
    $classificacoesVeiculo,
    ['idtbveiculoclassificacao', 'idclassificacao', 'id'],
    ['classificacao', 'descricao', 'nome', 'tipo']
);

$marcasFormatadas = montarOpcoesTabela(
    $marcasVeiculo,
    ['idtbveiculomarca', 'idmarca', 'id'],
    ['marca', 'descricao', 'nome']
);

$tiposVeiculoFormatado = montarOpcoesTabela(
    $tiposVeiculo,
    ['idtbveltipo', 'idtbatipovel', 'idtipo', 'id'],
    ['tipo', 'descricao', 'nome']
);

$opcoesUf = ['AC'=>'AC','AL'=>'AL','AP'=>'AP','AM'=>'AM','BA'=>'BA','CE'=>'CE','DF'=>'DF','ES'=>'ES','GO'=>'GO','MA'=>'MA','MT'=>'MT','MS'=>'MS','MG'=>'MG','PA'=>'PA','PB'=>'PB','PR'=>'PR','PE'=>'PE','PI'=>'PI','RJ'=>'RJ','RN'=>'RN','RS'=>'RS','RO'=>'RO','RR'=>'RR','SC'=>'SC','SP'=>'SP','SE'=>'SE','TO'=>'TO'];
$opcoesTipoPosse = ['PROPRIO' => 'PRÓPRIO', 'LOCADO' => 'LOCADO', 'AGREGADO' => 'AGREGADO', 'TERCEIRO' => 'TERCEIRO'];
$opcoesCombustivel = ['FLEX' => 'FLEX', 'GASOLINA' => 'GASOLINA', 'ETANOL' => 'ETANOL', 'DIESEL' => 'DIESEL', 'GNV' => 'GNV', 'ELÉTRICO' => 'ELÉTRICO', 'HÍBRIDO' => 'HÍBRIDO'];

$localizarOpcaoPorDescricao = static function (array $opcoes, string $descricao): string {
    $descricaoEsperada = mb_strtoupper(trim($descricao), 'UTF-8');
    foreach ($opcoes as $opcao) {
        if (mb_strtoupper(trim((string) ($opcao['descricao'] ?? '')), 'UTF-8') === $descricaoEsperada) {
            return (string) ($opcao['valor'] ?? '');
        }
    }

    return '';
};

$statusAtivoPadrao = $localizarOpcaoPorDescricao($statusCadastroFormatado, 'ATIVO');
$statusDisponivelPadrao = $localizarOpcaoPorDescricao($statusVeiculoFormatado, 'DISPONÍVEL');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>AutoFrota - Cadastrar Veículo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../src/autofrota-botoes.css">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body { background: #fff; color: #000; font-size: 12px; }
        .page-title { font-size: 24px; font-weight: 600; margin-bottom: 6px; text-align: center; }
        .page-subtitle { font-size: 22px; font-weight: 600; margin-bottom: 6px; text-align: center; }
        .page-author { font-size: 14px; text-align: center; margin-bottom: 14px; }
        .form-label, .form-control, .form-select, .btn { font-size: 12px; }
        .card-header { font-weight: 600; }
        .required-note { color: #dc3545; }
        .form-actions-floating { position: fixed; right: 24px; bottom: 24px; z-index: 1030; padding: 12px; background: rgba(255, 255, 255, .96); border: 1px solid #dee2e6; border-radius: 12px; box-shadow: 0 4px 18px rgba(0, 0, 0, .18); }
        @media (max-width: 575.98px) {
            .form-actions-floating { right: 12px; bottom: 12px; }
        }
    </style>
</head>
<body class="sb-nav-fixed">
    <?php include __DIR__ . '/../includes/menu_superior_simples.php'; ?>

    <div id="layoutSidenav_content">
        <main class="page-wrapper py-3">
            <h1 class="page-title">Cadastrar Veículo</h1>
            <p class="page-author"><strong>Cadastro feito por:</strong> (Matrícula: <?= esc($matriculaLogada) ?>)</p>

            <?php if ($mensagemRetorno !== ''): ?>
                <script>
                    window.addEventListener('load', function () {
                        alert(<?= json_encode($mensagemRetorno, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);

                        if (window.history && window.history.replaceState) {
                            const url = new URL(window.location.href);
                            url.searchParams.delete('msg');
                            window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
                        }
                    });
                </script>
            <?php endif; ?>

            <form method="post" action="control/cadastrarveiculo.php" enctype="multipart/form-data" class="pb-5">
                <input type="hidden" name="matr_autor" value="<?= esc($matriculaLogada) ?>">
                <input type="hidden" name="perfil_autor" value="<?= esc($perfilLogado) ?>">

                <div class="card mb-3">
                    <div class="card-header"><i class="fas fa-id-card me-2"></i>Identificação</div>
                    <div class="card-body row g-3">
                        <?php renderInput('placa', 'Placa', 'text', true, 'maxlength="8" style="text-transform: uppercase;"'); ?>
                        <?php renderSelect('uf', 'UF', $opcoesUf, '', '', true); ?>
                        <?php renderSelect('aplicacaofrota', 'Aplicação da frota', $aplicacoes, 'idtbaplicacaoveic', 'aplicacao', true); ?>
                        <?php renderSelect('modelo', 'Modelo', $modelosFormatados, 'idtbmodeloveic', 'descricao', true); ?>
                        <?php renderSelect('marca', 'Marca', $marcasFormatadas, 'valor', 'descricao', true, '', 'Selecione a marca...'); ?>
                        <?php renderInput('versao', 'Versão'); ?>
                        <?php renderSelect('categoria', 'Categoria', $categoriasFormatadas, 'valor', 'descricao', true, '', 'Selecione a categoria...'); ?>
                        <?php renderSelect('tipoveic', 'Classificação', $classificacoesFormatadas, 'valor', 'descricao', false, '', 'Selecione a classificação...'); ?>
                        <?php renderInput('cor', 'Cor', 'text', true); ?>
                        <?php renderSelect('zerokm', 'O veículo é 0km?', opcoesZeroKm(), '', '', true, '', 'Selecione...'); ?>
                        <?php renderInput('anofabric', 'Ano fabricação', 'number', true, 'min="1900" max="2100"'); ?>
                        <?php renderInput('anomodelo', 'Ano modelo', 'number', false, 'min="1900" max="2100"'); ?>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="fas fa-route me-2"></i>Status, posse e movimentação</div>
                    <div class="card-body row g-3">
                        <?php renderSelect('status', 'Status', $statusCadastroFormatado, 'valor', 'descricao', true, '', 'Selecione o status...', $statusAtivoPadrao, true); ?>
                        <?php renderSelect('statusvel', 'Status do veículo', $statusVeiculoFormatado, 'valor', 'descricao', true, '', 'Selecione o status do veículo...', $statusDisponivelPadrao, true); ?>
                        <?php renderSelect('tipovel', 'Tipo operacional', $tiposVeiculoFormatado, 'valor', 'descricao', true); ?>
                        <?php renderInput('situacao', 'Situação', 'text', true, 'placeholder="FIXO, PROVISÓRIO"'); ?>
                        <?php renderInput('hodometroinicial', 'Hodômetro inicial', 'number', false, 'min="0" step="1"'); ?>
                        <?php renderInput('hodometro', 'Hodômetro atual', 'number', false, 'min="0" step="1"'); ?>
                        <?php renderInput('datamovimentacao', 'Data movimentação', 'date'); ?>
                        <?php renderInput('horamovimentacao', 'Hora movimentação', 'time'); ?>
                        <?php renderSelect('matcond', 'Condutor', [], 'matricula', 'descricao', false, '', 'Condutor não pode ser selecionado no cadastro', '', true); ?>
                        <?php renderInput('dtentrega', 'Data entrega', 'date'); ?>
                        <?php renderInput('dtdevolucao', 'Data devolução', 'date'); ?>
                        <?php renderSelect('tipoposse', 'Tipo de posse', $opcoesTipoPosse, '', '', true); ?>
                        <?php renderInput('locador', 'Locador / fornecedor'); ?>
                        <?php renderInput('dtdevolucaoloc', 'Vencimento da locação', 'date'); ?>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="fas fa-cogs me-2"></i>Dados técnicos</div>
                    <div class="card-body row g-3">
                        <?php renderInput('velmax', 'Velocidade máxima', 'number', false, 'min="0" step="1"'); ?>
                        <?php renderInput('renavam', 'Renavam'); ?>
                        <?php renderInput('chassi', 'Chassi', 'text', false, 'style="text-transform: uppercase;"'); ?>
                        <?php renderInput('nummotor', 'Número do motor'); ?>
                        <?php renderSelect('combustivel', 'Combustível', $opcoesCombustivel, '', '', true); ?>
                        <?php renderInput('tanque', 'Tanque', 'number', false, 'min="0" step="0.01"'); ?>
                        <?php renderInput('motorizacao', 'Motorização'); ?>
                        <?php renderInput('nportas', 'Nº portas', 'number', false, 'min="0" step="1"'); ?>
                        <?php renderInput('npassageiros', 'Nº passageiros', 'number', false, 'min="0" step="1"'); ?>
                        <?php renderInput('calibragem', 'Calibragem'); ?>
                        <?php renderInput('aro', 'Aro'); ?>
                        <?php renderInput('qtdpneus', 'Qtd. pneus', 'number', false, 'min="0" step="1"'); ?>
                        <?php renderInput('qtdestepes', 'Qtd. estepes', 'number', false, 'min="0" step="1"'); ?>
                        <?php renderInput('qtdeixos', 'Qtd. eixos', 'number', false, 'min="0" step="1"'); ?>
                        <?php renderSelect('gnv', 'GNV?', opcoesSimNao(), '', '', true); ?>
                        <?php renderSelect('gps', 'GPS?', opcoesSimNao(), '', '', true); ?>
                        <?php renderSelect('tagpedagio', 'Tag pedágio?', opcoesSimNao()); ?>
                        <?php renderSelect('airbag', 'Airbag?', opcoesSimNao()); ?>
                        <?php renderSelect('rack', 'Rack?', opcoesSimNao()); ?>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="fas fa-file-invoice-dollar me-2"></i>Documentos, valores e gestão</div>
                    <div class="card-body row g-3">
                        <?php renderInput('doccrlv', 'Documento CRLV'); ?>
                        <?php renderInput('dtdisponivelloc', 'Data disponível locação', 'date'); ?>
                        <?php renderSelect('gpsemp', 'Empresa GPS', $opcoesGpsEmpresa, 'valor', 'descricao', true); ?>
                        <?php renderInput('oficina', 'Oficina'); ?>
                        <?php renderInput('ncontloc', 'Nº contrato locação'); ?>
                        <?php renderSelect('blindagem', 'Blindagem', $blindagens, 'blindagem', 'blindagem'); ?>
                        <?php renderInput('valaquisicao', 'Valor aquisição', 'text', false, 'placeholder="0,00"'); ?>
                        <?php renderInput('baseffa', 'Base FFA'); ?>
                        <?php renderSelect('unidade', 'Unidade', $unidades, 'unidade', 'unidade'); ?>
                        <?php renderSelect('basegestao', 'Base gestão', $basesGestao, 'basegestao', 'basegestao'); ?>
                        <?php renderSelect('centrocusto', 'Centro de custo', $centrosCusto, 'ccusto', 'ccusto'); ?>
                        <?php renderInput('dttermo', 'Data termo disponibilização', 'date'); ?>
                        <?php renderInput('dtdisp', 'Data desmobilização', 'date'); ?>
                        <div class="col-12">
                            <label class="form-label" for="obsveiculo">Observações</label>
                            <textarea class="form-control" name="obsveiculo" id="obsveiculo" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="fas fa-paperclip me-2"></i>Anexos</div>
                    <div class="card-body row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="crlv">CRLV</label>
                            <input class="form-control" type="file" name="crlv" id="crlv" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="crv">CRV</label>
                            <input class="form-control" type="file" name="crv" id="crv" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ipva">IPVA</label>
                            <input class="form-control" type="file" name="ipva" id="ipva" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                    </div>
                </div>

                <p class="required-note">* Campos obrigatórios.</p>

                <div class="form-actions-floating d-flex gap-2" role="group" aria-label="Ações do cadastro">
                    <button class="btn btn-success" type="submit">Confirmar</button>
                    <a class="btn btn-danger" href="#" onclick="if (window.history.length > 1) { window.history.back(); } else if (window.opener && !window.opener.closed) { window.close(); } else { window.location.href = 'index.php'; } return false;">Voltar</a>
                </div>
                <a class="btn btn-secondary mb-4" href="inventario-veiculo.php">Inventário de veículos</a>
            </form>
        </main>
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
                onKeyPress: function(value, e, field, options) {
                    e.currentTarget.value = value.toUpperCase();
                    const val = value.replace(/[^\w]/g, '');
                    const isNumeric = !isNaN(parseFloat(val[4])) && isFinite(val[4]);
                    let mask = 'AAA 0U00';
                    if (val.length > 4 && isNumeric) {
                        mask = 'AAA-0000';
                    }
                    $(field).mask(mask, options);
                }
            });
        });
    </script>
</body>
</html>
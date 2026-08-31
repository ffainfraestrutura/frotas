<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? ($GLOBALS['conn'] ?? null);
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? ($GLOBALS['databaseName'] ?? 'bdautofrotas'));
$databaseCorp = (string) ($autofrotaSessao['databaseCorp'] ?? ($GLOBALS['databaseCorp'] ?? 'bdcorp'));
$perfil = (string) ($autofrotaSessao['perfil'] ?? $_SESSION['perfil'] ?? '0');
if ($perfil === '0' || $perfil === '') {
    http_response_code(403);
    exit('Sem permissão.');
}

date_default_timezone_set('America/Sao_Paulo');

$matriculaLogada = (string) ($_POST['matr_autor'] ?? $autofrotaSessao['matricula'] ?? $_SESSION['matricula'] ?? '');
$perfilLogado = (string) ($_POST['perfil_autor'] ?? $perfil);
$usuarioLogado = (string) ($autofrotaSessao['usuario'] ?? $_SESSION['usuario'] ?? 'Usuário');
$mensagemRetorno = trim((string) ($_GET['msg'] ?? ''));
$idtbveiculo = trim((string) ($_GET['idtbveiculo'] ?? $_POST['idtbveiculo'] ?? ''));
$veiculo = [];
$documentosVeiculo = [];
$erroCarregamento = '';

if ($idtbveiculo === '') {
    $erroCarregamento = 'Identificador do veículo não informado.';
} elseif (isset($conn) && $conn instanceof mysqli) {
    $sqlVeiculo = "SELECT * FROM `{$databaseName}`.`tbveiculo` WHERE idtbveiculo = ? LIMIT 1";
    $stmtVeiculo = mysqli_prepare($conn, $sqlVeiculo);
    if ($stmtVeiculo) {
        mysqli_stmt_bind_param($stmtVeiculo, 's', $idtbveiculo);
        mysqli_stmt_execute($stmtVeiculo);
        $resultadoVeiculo = mysqli_stmt_get_result($stmtVeiculo);
        $veiculo = $resultadoVeiculo ? (mysqli_fetch_assoc($resultadoVeiculo) ?: []) : [];
        mysqli_stmt_close($stmtVeiculo);
    }
    if ($veiculo === []) {
        $erroCarregamento = 'Veículo não encontrado para o identificador informado.';
    } else {
        $placaDocumentos = trim((string) ($veiculo['placa'] ?? ''));
        $stmtDocumentos = mysqli_prepare($conn, "SELECT crlv, crv, cert_ipva FROM `{$databaseName}`.`tbveicdocs` WHERE placa = ? LIMIT 1");
        if ($stmtDocumentos) {
            mysqli_stmt_bind_param($stmtDocumentos, 's', $placaDocumentos);
            mysqli_stmt_execute($stmtDocumentos);
            $resultadoDocumentos = mysqli_stmt_get_result($stmtDocumentos);
            $documentosVeiculo = $resultadoDocumentos ? (mysqli_fetch_assoc($resultadoDocumentos) ?: []) : [];
            mysqli_stmt_close($stmtDocumentos);
        }
    }
} else {
    $erroCarregamento = 'Não foi possível conectar ao banco de dados.';
}

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
    return ['1' => 'SIM', '0' => 'NÃO'];
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
            if (array_key_exists($candidato, $linha) && trim((string) ($linha[$candidato] ?? '')) !== '') {
                $campoId = $candidato;
                break;
            }
        }
        if ($campoId === '') {
            foreach ($linha as $chave => $valorCampo) {
                if (trim((string) $valorCampo) !== '') {
                    $campoId = (string) $chave;
                    break;
                }
            }
        }

        $campoTexto = '';
        foreach ($camposTextoPreferidos as $candidato) {
            if (array_key_exists($candidato, $linha) && trim((string) ($linha[$candidato] ?? '')) !== '') {
                $campoTexto = $candidato;
                break;
            }
        }
        if ($campoTexto === '') {
            foreach ($linha as $chave => $valorCampo) {
                if (trim((string) $valorCampo) !== '' && (string) $chave !== $campoId) {
                    $campoTexto = (string) $chave;
                    break;
                }
            }
            if ($campoTexto === '') {
                $campoTexto = $campoId;
            }
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

function normalizarValorOpcaoTexto(string $valor): string
{
    $valor = trim($valor);
    $valor = mb_strtolower($valor, 'UTF-8');
    $valor = preg_replace('/\s+/', '_', $valor);

    return (string) $valor;
}

function renderSelect(string $name, string $label, array $options, string $valueKey = '', string $textKey = '', bool $required = false, string $extraClass = '', string $placeholder = 'Selecione...', string $selectedValue = ''): void
{
    $requiredAttr = $required ? ' required' : '';
    echo '<div class="col-md-4 ' . esc($extraClass) . '">';
    echo '<label class="form-label" for="' . esc($name) . '">' . esc($label) . ($required ? ' <span class="text-danger">*</span>' : '') . '</label>';
    echo '<select class="form-select" name="' . esc($name) . '" id="' . esc($name) . '"' . $requiredAttr . '>';
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
            echo '<option value="' . esc($value) . '"' . ((string) $selectedValue === $value ? ' selected' : '') . '>' . esc($text) . '</option>';
        }
    }

    echo '</select></div>';
}

function renderInput(string $name, string $label, string $type = 'text', bool $required = false, string $extra = '', string $value = ''): void
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
    echo '<input class="form-control" type="' . esc($type) . '" name="' . esc($name) . '" id="' . esc($name) . '" value="' . esc($value) . '"' . $requiredAttr . ($extraNormalizado !== '' ? ' ' . $extraNormalizado : '') . '>';
    echo '</div>';
}

$aplicacoes = [];
$modelos = [];
$statusCadastro = [];
$statusVeiculo = [];
$funcionarios = [];
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

    $funcionarios = consultarOpcoes($conn, "
        SELECT DISTINCT matricula, nome
        FROM `{$databaseName}`.`tbcondutor`
        WHERE matricula IS NOT NULL
          AND TRIM(matricula) <> ''
          AND nome IS NOT NULL
          AND TRIM(nome) <> ''
        ORDER BY nome
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

    $centroCustoAtual = trim((string) ($veiculo['ccusto'] ?? ''));
    if ($centroCustoAtual !== '') {
        $centroCustoExiste = false;
        foreach ($centrosCusto as $centroCusto) {
            if (trim((string) ($centroCusto['ccusto'] ?? '')) === $centroCustoAtual) {
                $centroCustoExiste = true;
                break;
            }
        }

        if (!$centroCustoExiste) {
            array_unshift($centrosCusto, ['ccusto' => $centroCustoAtual]);
        }
    }

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

$funcionariosFormatados = array_map(static function (array $funcionario): array {
    $matricula = trim((string) ($funcionario['matricula'] ?? ''));
    $nome = trim((string) ($funcionario['nome'] ?? ''));
    $funcionario['descricao'] = trim($matricula . ' - ' . $nome);
    return $funcionario;
}, $funcionarios);

$categoriasFormatadas = montarOpcoesTabela(
    $categoriasVeiculo,
    ['idtbveiculocategoria', 'idcategoria', 'id'],
    ['categoria', 'descricao', 'nome']
);

$categoriasFormatadas = array_values(array_filter(array_map(static function (array $categoria): ?array {
    $descricao = trim((string) ($categoria['descricao'] ?? $categoria['categoria'] ?? $categoria['nome'] ?? ''));
    if ($descricao === '') {
        return null;
    }

    return [
        'valor' => normalizarValorOpcaoTexto($descricao),
        'descricao' => $descricao,
    ];
}, $categoriasFormatadas)));

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

$gpsempAtual = trim((string) ($veiculo['gpsemp'] ?? ''));
if ($gpsempAtual !== '' && ctype_digit($gpsempAtual)) {
    $gpsempExiste = false;
    foreach ($opcoesGpsEmpresa as $opcaoGps) {
        if ((string) ($opcaoGps['valor'] ?? '') === $gpsempAtual) {
            $gpsempExiste = true;
            break;
        }
    }

    if (!$gpsempExiste) {
        array_unshift($opcoesGpsEmpresa, [
            'valor' => $gpsempAtual,
            'descricao' => 'Código atual (' . $gpsempAtual . ')',
        ]);
    }
}

$opcoesUf = ['AC'=>'AC','AL'=>'AL','AP'=>'AP','AM'=>'AM','BA'=>'BA','CE'=>'CE','DF'=>'DF','ES'=>'ES','GO'=>'GO','MA'=>'MA','MT'=>'MT','MS'=>'MS','MG'=>'MG','PA'=>'PA','PB'=>'PB','PR'=>'PR','PE'=>'PE','PI'=>'PI','RJ'=>'RJ','RN'=>'RN','RS'=>'RS','RO'=>'RO','RR'=>'RR','SC'=>'SC','SP'=>'SP','SE'=>'SE','TO'=>'TO'];
$opcoesTipoPosse = ['PROPRIO' => 'PRÓPRIO', 'LOCADO' => 'LOCADO', 'AGREGADO' => 'AGREGADO', 'TERCEIRO' => 'TERCEIRO'];
$opcoesCombustivel = ['FLEX' => 'FLEX', 'GASOLINA' => 'GASOLINA', 'ETANOL' => 'ETANOL', 'DIESEL' => 'DIESEL', 'GNV' => 'GNV', 'ELÉTRICO' => 'ELÉTRICO', 'HÍBRIDO' => 'HÍBRIDO'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>AutoFrota - Editar Veículo</title>
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
        .edit-actions { position: fixed; right: 24px; bottom: 24px; z-index: 1030; padding: 12px; background: rgba(255, 255, 255, .96); border: 1px solid #dee2e6; border-radius: 12px; box-shadow: 0 4px 18px rgba(0, 0, 0, .18); }
    </style>
</head>
<body class="sb-nav-fixed">
    <?php include __DIR__ . '/../includes/menu_superior_simples.php'; ?>

    <div id="layoutSidenav_content">
        <main class="page-wrapper py-3">
            <h1 class="page-title">Editar Veículo</h1>
            <p class="page-author"><strong>Edição feita por:</strong> (Matrícula: <?= esc($matriculaLogada) ?>)</p>

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

            <form method="post" action="control/editar-veiculo.php" enctype="multipart/form-data" class="pb-5">
                <input type="hidden" name="idtbveiculo" value="<?= esc($idtbveiculo) ?>">
                <input type="hidden" name="matr_autor" value="<?= esc($matriculaLogada) ?>">
                <input type="hidden" name="perfil_autor" value="<?= esc($perfilLogado) ?>">

                <?php if ($erroCarregamento !== ''): ?>
                    <div class="alert alert-warning"><?= esc($erroCarregamento) ?></div>
                <?php endif; ?>

                <div class="card mb-3">
                    <div class="card-header"><i class="fas fa-id-card me-2"></i>Identificação</div>
                    <div class="card-body row g-3">
                        <?php renderInput('placa', 'Placa', 'text', true, 'maxlength="8" style="text-transform: uppercase;"', (string) ($veiculo['placa'] ?? '')); ?>
                        <?php renderSelect('uf', 'UF', $opcoesUf, '', '', true, selectedValue: (string) ($veiculo['uf'] ?? '')); ?>
                        <?php renderSelect('aplicacaofrota', 'Aplicação da frota', $aplicacoes, 'idtbaplicacaoveic', 'aplicacao', true, selectedValue: (string) ($veiculo['aplicacao'] ?? '')); ?>
                        <?php renderSelect('modelo', 'Modelo', $modelosFormatados, 'idtbmodeloveic', 'descricao', true, selectedValue: (string) ($veiculo['modelo'] ?? '')); ?>
                        <?php renderSelect('marca', 'Marca', $marcasFormatadas, 'valor', 'descricao', true, '', 'Selecione a marca...', selectedValue: (string) ($veiculo['marca'] ?? '')); ?>
                        <?php renderInput('versao', 'Versão', value: (string) ($veiculo['versao'] ?? '')); ?>
                        <?php renderSelect('categoria', 'Categoria', $categoriasFormatadas, 'valor', 'descricao', true, '', 'Selecione a categoria...', selectedValue: normalizarValorOpcaoTexto((string) ($veiculo['categoria'] ?? ''))); ?>
                        <?php renderSelect('tipoveic', 'Classificação', $classificacoesFormatadas, 'valor', 'descricao', false, '', 'Selecione a classificação...', selectedValue: (string) ($veiculo['tipo'] ?? '')); ?>
                        <?php renderInput('cor', 'Cor', 'text', true, value: (string) ($veiculo['cor'] ?? '')); ?>
                        <?php renderSelect('zerokm', 'O veículo é 0km?', opcoesZeroKm(), '', '', true, '', 'Selecione...', selectedValue: (string) ($veiculo['zerokm'] ?? '')); ?>
                        <?php renderInput('anofabric', 'Ano fabricação', 'number', true, 'min="1900" max="2100"', value: (string) ($veiculo['anofabr'] ?? '')); ?>
                        <?php renderInput('anomodelo', 'Ano modelo', 'number', false, 'min="1900" max="2100"', value: (string) ($veiculo['anomodelo'] ?? '')); ?>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="fas fa-route me-2"></i>Status, posse e movimentação</div>
                    <div class="card-body row g-3">
                        <?php renderSelect('status', 'Status', $statusCadastroFormatado, 'valor', 'descricao', true, '', 'Selecione o status...', selectedValue: (string) ($veiculo['status'] ?? '')); ?>
                        <?php renderSelect('statusvel', 'Status do veículo', $statusVeiculoFormatado, 'valor', 'descricao', true, '', 'Selecione o status do veículo...', selectedValue: (string) ($veiculo['statusvel'] ?? '')); ?>
                        <?php renderSelect('tipovel', 'Tipo operacional', $tiposVeiculoFormatado, 'valor', 'descricao', true, selectedValue: (string) ($veiculo['tipovel'] ?? '')); ?>
                        <?php renderInput('situacao', 'Situação', 'text', true, 'placeholder="FIXO, PROVISÓRIO"', value: (string) ($veiculo['situacao'] ?? '')); ?>
                        <?php renderInput('hodometroinicial', 'Hodômetro inicial', 'number', false, 'min="0" step="1"', value: (string) ($veiculo['hodometroinicial'] ?? '')); ?>
                        <?php renderInput('hodometro', 'Hodômetro atual', 'number', false, 'min="0" step="1"', value: (string) ($veiculo['hodometro'] ?? '')); ?>
                        <?php renderInput('datamovimentacao', 'Data movimentação', 'date', value: substr((string) ($veiculo['datamovimentacao'] ?? ''), 0, 10)); ?>
                        <?php renderInput('horamovimentacao', 'Hora movimentação', 'time', value: substr((string) ($veiculo['datamovimentacao'] ?? ''), 11, 5)); ?>
                        <?php renderSelect('matcond', 'Condutor', $funcionariosFormatados, 'matricula', 'descricao', selectedValue: (string) ($veiculo['matcond'] ?? '')); ?>
                        <?php renderInput('dtentrega', 'Data entrega', 'date', value: (string) ($veiculo['dtentrega'] ?? '')); ?>
                        <?php renderInput('dtdevolucao', 'Data devolução', 'date', value: (string) ($veiculo['dtdevolucao'] ?? '')); ?>
                        <?php renderSelect('tipoposse', 'Tipo de posse', $opcoesTipoPosse, '', '', true, selectedValue: (string) ($veiculo['tipoposse'] ?? '')); ?>
                        <?php renderInput('locador', 'Locador / fornecedor', value: (string) ($veiculo['idlocador'] ?? '')); ?>
                        <?php renderInput('dtdevolucaoloc', 'Vencimento da locação', 'date', value: (string) ($veiculo['dtdevolucaoloc'] ?? '')); ?>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="fas fa-cogs me-2"></i>Dados técnicos</div>
                    <div class="card-body row g-3">
                        <?php renderInput('velmax', 'Velocidade máxima', 'number', false, 'min="0" step="1"', value: (string) ($veiculo['velocmax'] ?? '')); ?>
                        <?php renderInput('renavam', 'Renavam', value: (string) ($veiculo['renavam'] ?? '')); ?>
                        <?php renderInput('chassi', 'Chassi', 'text', false, 'style="text-transform: uppercase;"', (string) ($veiculo['chassi'] ?? '')); ?>
                        <?php renderInput('nummotor', 'Número do motor', value: (string) ($veiculo['nummotor'] ?? '')); ?>
                        <?php renderSelect('combustivel', 'Combustível', $opcoesCombustivel, '', '', true, selectedValue: (string) ($veiculo['combustivel'] ?? '')); ?>
                        <?php renderInput('tanque', 'Tanque', 'number', false, 'min="0" step="0.01"', value: (string) ($veiculo['tanque'] ?? '')); ?>
                        <?php renderInput('motorizacao', 'Motorização', value: (string) ($veiculo['motorizacao'] ?? '')); ?>
                        <?php renderInput('nportas', 'Nº portas', 'number', false, 'min="0" step="1"', value: (string) ($veiculo['nportas'] ?? '')); ?>
                        <?php renderInput('npassageiros', 'Nº passageiros', 'number', false, 'min="0" step="1"', value: (string) ($veiculo['npassageiros'] ?? '')); ?>
                        <?php renderInput('calibragem', 'Calibragem', value: (string) ($veiculo['calibragem'] ?? '')); ?>
                        <?php renderInput('aro', 'Aro', value: (string) ($veiculo['aro'] ?? '')); ?>
                        <?php renderInput('qtdpneus', 'Qtd. pneus', 'number', false, 'min="0" step="1"', value: (string) ($veiculo['qtdpneus'] ?? '')); ?>
                        <?php renderInput('qtdestepes', 'Qtd. estepes', 'number', false, 'min="0" step="1"', value: (string) ($veiculo['qtdestepe'] ?? '')); ?>
                        <?php renderInput('qtdeixos', 'Qtd. eixos', 'number', false, 'min="0" step="1"', value: (string) ($veiculo['qtdeixos'] ?? '')); ?>
                        <?php renderSelect('gnv', 'GNV?', opcoesSimNao(), '', '', true, selectedValue: (string) ($veiculo['gnv'] ?? '')); ?>
                        <?php renderSelect('gps', 'GPS?', opcoesSimNao(), '', '', true, selectedValue: (string) ($veiculo['gps'] ?? '')); ?>
                        <?php renderSelect('tagpedagio', 'Tag pedágio?', opcoesSimNao(), selectedValue: (string) ($veiculo['tagpedagio'] ?? '')); ?>
                        <?php renderSelect('airbag', 'Airbag?', opcoesSimNao(), selectedValue: (string) ($veiculo['airbag'] ?? '')); ?>
                        <?php renderSelect('rack', 'Rack?', opcoesSimNao(), selectedValue: (string) ($veiculo['rack'] ?? '')); ?>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="fas fa-file-invoice-dollar me-2"></i>Documentos, valores e gestão</div>
                    <div class="card-body row g-3">
                        <?php renderInput('doccrlv', 'Documento CRLV', value: (string) ($veiculo['doccrlv'] ?? '')); ?>
                        <?php renderInput('dtdisponivelloc', 'Data disponível locação', 'date', value: (string) ($veiculo['dtdisponivelloc'] ?? '')); ?>
                        <?php renderSelect('gpsemp', 'Empresa GPS', $opcoesGpsEmpresa, 'valor', 'descricao', true, '', 'Selecione...', selectedValue: (string) ($veiculo['gpsemp'] ?? '')); ?>
                        <?php renderInput('oficina', 'Oficina', value: (string) ($veiculo['oficina'] ?? '')); ?>
                        <?php renderInput('ncontloc', 'Nº contrato locação', value: (string) ($veiculo['ncontloc'] ?? '')); ?>
                        <?php renderSelect('blindagem', 'Blindagem', $blindagens, 'blindagem', 'blindagem', selectedValue: (string) ($veiculo['blindagem'] ?? '')); ?>
                        <?php renderInput('valaquisicao', 'Valor aquisição', 'text', false, 'placeholder="0,00"', value: (string) ($veiculo['valaquisicao'] ?? '')); ?>
                        <?php renderInput('baseffa', 'Base FFA', value: (string) ($veiculo['baseffa'] ?? '')); ?>
                        <?php renderSelect('unidade', 'Unidade', $unidades, 'unidade', 'unidade', selectedValue: (string) ($veiculo['unidade'] ?? '')); ?>
                        <?php renderSelect('basegestao', 'Base gestão', $basesGestao, 'basegestao', 'basegestao', selectedValue: (string) ($veiculo['basegestao'] ?? '')); ?>
                        <?php renderSelect('centrocusto', 'Centro de custo', $centrosCusto, 'ccusto', 'ccusto', selectedValue: (string) ($veiculo['ccusto'] ?? '')); ?>
                        <?php renderInput('dttermo', 'Data termo disponibilização', 'date', value: (string) ($veiculo['dttermodisp'] ?? '')); ?>
                        <?php renderInput('dtdisp', 'Data desmobilização', 'date', value: (string) ($veiculo['dtdesmobilizacao'] ?? '')); ?>
                        <div class="col-12">
                            <label class="form-label" for="obsveiculo">Observações</label>
                            <textarea class="form-control" name="obsveiculo" id="obsveiculo" rows="3"><?= esc($veiculo['obsveiculo'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="fas fa-paperclip me-2"></i>Anexos</div>
                    <div class="card-body row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="crlv">CRLV</label>
                            <input class="form-control" type="file" name="crlv" id="crlv" accept=".pdf,.jpg,.jpeg,.png">
                            <?php if (!empty($documentosVeiculo['crlv'])): ?>
                                <a class="btn btn-sm btn-outline-secondary mt-2" href="<?= esc(urlDocumentoUploadPortal($documentosVeiculo['crlv'])) ?>" target="_blank" rel="noopener"><i class="fas fa-eye me-1"></i>Abrir CRLV atual</a>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="crv">CRV</label>
                            <input class="form-control" type="file" name="crv" id="crv" accept=".pdf,.jpg,.jpeg,.png">
                            <?php if (!empty($documentosVeiculo['crv'])): ?>
                                <a class="btn btn-sm btn-outline-secondary mt-2" href="<?= esc(urlDocumentoUploadPortal($documentosVeiculo['crv'])) ?>" target="_blank" rel="noopener"><i class="fas fa-eye me-1"></i>Abrir CRV atual</a>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ipva">IPVA</label>
                            <input class="form-control" type="file" name="ipva" id="ipva" accept=".pdf,.jpg,.jpeg,.png">
                            <?php if (!empty($documentosVeiculo['cert_ipva'])): ?>
                                <a class="btn btn-sm btn-outline-secondary mt-2" href="<?= esc(urlDocumentoUploadPortal($documentosVeiculo['cert_ipva'])) ?>" target="_blank" rel="noopener"><i class="fas fa-eye me-1"></i>Abrir IPVA atual</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <p class="required-note">* Campos obrigatórios.</p>

                <div class="edit-actions d-flex gap-2" role="group" aria-label="Ações da edição">
                    <button class="btn btn-success" type="submit" <?= $erroCarregamento !== '' ? 'disabled' : '' ?>>Confirmar edição</button>
                    <a class="btn btn-danger" href="listagem-veiculo.php" onclick="if (window.opener && !window.opener.closed) { window.close(); return false; }">Cancelar edição</a>
                </div>
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
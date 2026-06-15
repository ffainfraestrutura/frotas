<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../control/conecta.php';

$perfil = (string) ($_SESSION['perfil'] ?? '0');
if ($perfil === '0' || $perfil === '') {
    http_response_code(403);
    exit('Sem permissão.');
}

exigirLogin();

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: text/html; charset=utf-8');

$matricula = (string) ($_SESSION['matricula'] ?? $_POST['matr_autor'] ?? '');
$link = $_SERVER['REQUEST_URI'] ?? '';
$idtbveiculo = strpos($link, '=') !== false
    ? (string) ($_GET['idtbveiculo'] ?? '')
    : (string) ($_POST['idtbveiculo'] ?? '');

$veiculo = [];
$erroCarregamento = '';

if ($idtbveiculo !== '') {
    $sql = 'SELECT * FROM `bdautofrotas`.`tbveiculo` WHERE idtbveiculo = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $idtbveiculo);
        mysqli_stmt_execute($stmt);
        $resultado = mysqli_stmt_get_result($stmt);
        $veiculo = $resultado ? (mysqli_fetch_assoc($resultado) ?: []) : [];
        mysqli_stmt_close($stmt);

        if ($veiculo === []) {
            $erroCarregamento = 'Veículo não encontrado para o id informado.';
        }
    } else {
        $erroCarregamento = 'Não foi possível carregar os dados do veículo.';
    }
}

$camposTbveiculo = [
    'status', 'placa', 'uf', 'aplicacao', 'zerokm', 'categoria', 'tipo',
    'marca', 'modelo', 'versao', 'cor', 'anofabr', 'anomodelo',
    'statusvel', 'doc01', 'tipovel', 'situacao', 'hodometroinicial',
    'datamovimentacao', 'horamovimentacao', 'dtentrega', 'dtdevolucao',
    'velocmax', 'combustivel', 'tanque', 'motorizacao', 'calibragem', 'aro',
    'renavam', 'chassi', 'nummotor', 'nportas', 'npassageiros', 'qtdpneus',
    'qtdestepe', 'qtdeixos', 'gnv', 'gps', 'tagpedagio',
    'gpsemp', 'doccrlv', 'airbag', 'rack', 'blindagem',
    'oficina', 'ncontloc', 'dtdisponivelloc', 'dtdevolucaoloc', 'valaquisicao',
    'hodometro', 'tipoposse', 'idlocador',
    'basegestao', 'ccusto', 'unidade',
    'dttermodisp', 'dtdesmobilizacao',
    'obsveiculo'
];

function escVeiculo($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function formatoInput($campo): string
{
    if ($campo === 'doc01') {
        return 'file';
    }

    if ($campo === 'horamovimentacao') {
        return 'time';
    }

    if (strpos($campo, 'data') === 0 || strpos($campo, 'dt') === 0) {
        return 'date';
    }

    if (in_array($campo, ['anofabr', 'anomodelo', 'renavam', 'hodometro', 'hodometroinicial'], true)) {
        return 'number';
    }

    return 'text';
}

function valorInput($campo, $valor): string
{
    $valorTexto = trim((string) $valor);
    $tipo = formatoInput($campo);

    if ($campo === 'horamovimentacao') {
        if ($valorTexto === '' || $valorTexto === '0000-00-00 00:00:00') {
            return '';
        }

        return substr(str_replace(' ', 'T', $valorTexto), 11, 5);
    }

    if ($tipo === 'datetime-local') {
        if ($valorTexto === '' || $valorTexto === '0000-00-00 00:00:00') {
            return '';
        }

        return str_replace(' ', 'T', substr($valorTexto, 0, 16));
    }

    if ($tipo === 'date') {
        if ($valorTexto === '' || $valorTexto === '0000-00-00' || $valorTexto === '0000-00-00 00:00:00') {
            return '';
        }

        return substr($valorTexto, 0, 10);
    }

    return $valorTexto;
}

function nomeCampoPost($campo): string
{
    $mapa = [
        'aplicacao' => 'aplicacaofrota',
        'tipo' => 'tipoveic',
        'idlocador' => 'locador',
        'ccusto' => 'centrocusto',
        'dttermodisp' => 'dttermo',
        'dtdesmobilizacao' => 'dtdisp'
    ];

    return $mapa[$campo] ?? $campo;
}

function campoObrigatorio($campo): bool
{
    $obrigatorios = [
        'status',
        'placa',
        'uf',
        'aplicacao',
        'zerokm',
        'categoria',
        'cor',
        'anofabr',
        'statusvel',
        'tipovel',
        'situacao',
        'combustivel',
        'gnv',
        'gps',
        'gpsemp',
        'tipoposse',
        'ccusto'
    ];

    return in_array($campo, $obrigatorios, true);
}

function atributosValidacao($campo): string
{
    $numericosTexto = [
        'velocmax', 'tanque', 'calibragem', 'aro', 'nportas', 'npassageiros',
        'qtdpneus', 'qtdestepe', 'qtdeixos'
    ];

    if (in_array($campo, $numericosTexto, true)) {
        return ' inputmode="numeric" pattern="[0-9.]+"';
    }

    if (in_array($campo, ['anofabr', 'anomodelo'], true)) {
        return ' min="1900" max="2999" step="1"';
    }

    return '';
}

function rotuloCampo($campo): string
{
    $mapa = [
        'status' => 'Status',
        'placa' => 'Placa',
        'uf' => 'UF',
        'aplicacao' => 'Aplicação na frota',
        'zerokm' => 'O veículo é 0km?',
        'categoria' => 'Categoria',
        'tipo' => 'Classificação',
        'marca' => 'Marca',
        'modelo' => 'Modelo',
        'versao' => 'Versão',
        'cor' => 'Cor',
        'anofabr' => 'Ano de fabricação',
        'anomodelo' => 'Ano do modelo',
        'statusvel' => 'Status do Veículo',
        'doc01' => 'Documento',
        'tipovel' => 'Tipo',
        'situacao' => 'Situação',
        'hodometroinicial' => 'Hodômetro Inicial(em Total de Km)',
        'datamovimentacao' => 'Data Ult. Movimentação',
        'horamovimentacao' => 'Hora Ult. Mov.',
        'dtentrega' => 'Data Entrega',
        'dtdevolucao' => 'Data Devolução',
        'velocmax' => 'Velocidade máxima (em km/h)',
        'combustivel' => 'Tipo de combustível',
        'tanque' => 'Capac tanque(em L)',
        'motorizacao' => 'Motorização',
        'calibragem' => 'Calibragem (em PSI)',
        'aro' => 'Aro',
        'renavam' => 'RENAVAM',
        'chassi' => 'Número do Chassi',
        'nummotor' => 'Número do Motor',
        'nportas' => 'Número de Portas',
        'npassageiros' => 'Nº de passageiros',
        'qtdpneus' => 'Quantidade de Pneus',
        'qtdestepe' => 'Quant. de estepes',
        'qtdeixos' => 'Quantidade de eixos',
        'gnv' => 'O veículo é GNV?',
        'gps' => 'O veículo possui GPS?',
        'tagpedagio' => 'O veículo possui Tag Pedágio?',
        'gpsemp' => 'GPS Empresa',
        'doccrlv' => 'Doc. CRLV',
        'airbag' => 'O veículo possui Airbag?',
        'rack' => 'O veículo possui rack?',
        'blindagem' => 'Tipo de Blindagem',
        'oficina' => 'Oficina',
        'ncontloc' => 'N° Contrato Locação',
        'dtdisponivelloc' => 'Data Disp. Locadora',
        'dtdevolucaoloc' => 'Data Dev. Locadora',
        'valaquisicao' => 'Valor Aquisição',
        'hodometro' => 'Hodômetro (em Total de Km)',
        'tipoposse' => 'Tipo de posse do veículo',
        'idlocador' => 'Locador (se o veículo for locado)',
        'basegestao' => 'Base gestão',
        'ccusto' => 'Centro de Custo',
        'unidade' => 'Unidade',
        'dttermodisp' => 'Data termo de disponibilização',
        'dtdesmobilizacao' => 'Data de desmobilização',
        'obsveiculo' => 'Observações'
    ];

    return $mapa[$campo] ?? ucwords(str_replace('_', ' ', $campo));
}

function placeholderCampo($campo): string
{
    $mapa = [
        'placa' => 'Nº da Placa',
        'versao' => 'Versão do Veículo',
        'cor' => 'Cor do Veículo',
        'anofabr' => 'Ex.: 1990',
        'anomodelo' => 'Ex.: 1990',
        'hodometroinicial' => 'Hodômetro em Total de Km',
        'velocmax' => 'Velocidade máxima',
        'tanque' => 'Capacidade em Litros',
        'calibragem' => 'Calibragem (em PSI)',
        'aro' => 'Aro',
        'renavam' => 'RENAVAM',
        'chassi' => 'Nº do Chassi',
        'nummotor' => 'Nº do motor',
        'nportas' => 'Nº de portas',
        'npassageiros' => 'Nº de passageiros',
        'qtdpneus' => 'Quantidade de Pneus',
        'qtdestepe' => 'Quantidade de estepes',
        'qtdeixos' => 'Quantidade de eixos',
        'doccrlv' => 'Doc. CRLV',
        'oficina' => 'Oficina',
        'ncontloc' => 'Número Contrato Locação',
        'valaquisicao' => 'R$ 0,00',
        'hodometro' => 'Hodômetro em Total de Km',
        'obsveiculo' => 'Observação'
    ];

    return $mapa[$campo] ?? '';
}

function grupoCampoVeiculo(string $campo): string
{
    $identificacao = [
        'status', 'placa', 'uf', 'aplicacao', 'zerokm', 'categoria', 'tipo',
        'marca', 'modelo', 'versao', 'cor', 'anofabr', 'anomodelo',
        'statusvel', 'tipovel', 'situacao'
    ];

    $movimentacao = [
        'hodometroinicial', 'datamovimentacao', 'horamovimentacao', 'dtentrega', 'dtdevolucao',
        'velocmax', 'combustivel', 'tanque', 'motorizacao', 'calibragem', 'aro'
    ];

    $estruturaDocumentacao = [
        'renavam', 'chassi', 'nummotor', 'nportas', 'npassageiros', 'qtdpneus',
        'qtdestepe', 'qtdeixos', 'gnv', 'gps', 'tagpedagio', 'gpsemp',
        'doccrlv', 'doc01', 'airbag', 'rack', 'blindagem'
    ];

    $locacaoGestao = [
        'oficina', 'ncontloc', 'dtdisponivelloc', 'dtdevolucaoloc', 'valaquisicao',
        'hodometro', 'tipoposse', 'idlocador', 'basegestao', 'ccusto',
        'unidade', 'dttermodisp', 'dtdesmobilizacao'
    ];

    if (in_array($campo, $identificacao, true)) {
        return 'Identificação e Classificação';
    }

    if (in_array($campo, $movimentacao, true)) {
        return 'Movimentação e Operação';
    }

    if (in_array($campo, $estruturaDocumentacao, true)) {
        return 'Estrutura e Documentação';
    }

    if (in_array($campo, $locacaoGestao, true)) {
        return 'Locação e Gestão';
    }

    return 'Outros Campos';
}

function iconeGrupoCampoVeiculo(string $grupo): string
{
    $mapa = [
        'Identificação e Classificação' => 'fa-solid fa-id-card',
        'Movimentação e Operação' => 'fa-solid fa-gauge-high',
        'Estrutura e Documentação' => 'fa-solid fa-file-lines',
        'Locação e Gestão' => 'fa-solid fa-briefcase',
        'Outros Campos' => 'fa-solid fa-layer-group',
    ];

    return $mapa[$grupo] ?? 'fa-solid fa-layer-group';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="FFA" />
    <meta name="author" content="FFA" />
    <title>Editar Cadastro de Veículo</title>

    <link href="../src/css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        .grid-campos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 12px;
        }

        .campo-bloco {
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 10px;
            background: #fff;
        }

        .grupo-campos {
            border: 1px solid #dfe3e8;
            border-radius: 10px;
            background: #f8fafc;
            padding: 12px;
        }

        .grupo-titulo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #1f2937;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 6px;
        }
    </style>
</head>
<body class="sb-nav-fixed">
    <?php include __DIR__ . '/../includes/menu_superior_simples.php'; ?>

    <div id="layoutSidenav_content">
        <main style="width: 100%;" class="mb-2">
            <div class="container-fluid px-4" style="width: 100%;">
                <h1 class="h1 pt-2 pb-2 text-center">Editar Cadastro de Veículo</h1>
                <p style="font-size: 12px;" class="ps-5">
                    Visando a unificação dos processos, a mudança no status do veículo deve ser realizada através um checklist (vistoria).
                </p>

                <div>
                    <form method="post" action="./control/editar-veiculo.php" enctype="multipart/form-data" style="width: 90%;" class="pb-5 mb-5 m-auto">
                        <input type="hidden" name="idtbveiculo" value="<?= htmlspecialchars($idtbveiculo, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="matr_autor" value="<?= htmlspecialchars($matricula, ENT_QUOTES, 'UTF-8') ?>">

                        <?php if ($erroCarregamento !== ''): ?>
                            <div class="alert alert-warning"><?= escVeiculo($erroCarregamento) ?></div>
                        <?php endif; ?>

                        <div class="mt-3">
                            <?php $grupoAtual = null; ?>
                            <?php foreach ($camposTbveiculo as $campo): ?>
                                <?php if ($campo === 'obsveiculo') { continue; } ?>
                                <?php $grupoCampo = grupoCampoVeiculo($campo); ?>
                                <?php if ($grupoCampo !== $grupoAtual): ?>
                                    <?php if ($grupoAtual !== null): ?>
                                        </div>
                                    </section>
                                    <?php endif; ?>
                                    <section class="grupo-campos mb-3">
                                        <div class="grupo-titulo">
                                            <i class="<?= escVeiculo(iconeGrupoCampoVeiculo($grupoCampo)) ?>" aria-hidden="true"></i>
                                            <span><?= escVeiculo($grupoCampo) ?></span>
                                        </div>
                                        <div class="grid-campos">
                                    <?php $grupoAtual = $grupoCampo; ?>
                                <?php endif; ?>
                                <div class="campo-bloco">
                                    <label class="form-label mb-1" for="<?= escVeiculo($campo) ?>">
                                        <?= escVeiculo(rotuloCampo($campo)) ?>:
                                        <?php if (campoObrigatorio($campo)): ?>
                                            <span style="color: red;">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <?php if ($campo === 'status'): ?>
                                        <?php $valorStatus = trim((string) ($veiculo['status'] ?? '')); ?>
                                        <select
                                            class="form-select"
                                            id="status"
                                            name="status"
                                            <?= campoObrigatorio($campo) ? 'required' : '' ?>
                                        >
                                            <option value="">Selecione...</option>
                                            <option value="1" <?= $valorStatus === '1' ? 'selected' : '' ?>>Ativo</option>
                                            <option value="0" <?= $valorStatus === '0' ? 'selected' : '' ?>>Inativo</option>
                                        </select>
                                        <?php elseif ($campo === 'basegestao'): ?>
                                            <?php
                                                $valorBaseGestao = trim((string) ($veiculo['basegestao'] ?? ''));
                                                $opcoesBaseGestao = [];
                                                $sqlBaseGestao = 'SELECT descricao FROM `bdautofrotas`.`tbveiculobasegestao` ORDER BY descricao';
                                                $resultadoBaseGestao = mysqli_query($conn, $sqlBaseGestao);
                                                if ($resultadoBaseGestao) {
                                                    while ($linhaBaseGestao = mysqli_fetch_assoc($resultadoBaseGestao)) {
                                                        $descBaseGestao = trim((string) ($linhaBaseGestao['descricao'] ?? ''));
                                                        if ($descBaseGestao !== '') {
                                                            $opcoesBaseGestao[] = $descBaseGestao;
                                                        }
                                                    }
                                                    mysqli_free_result($resultadoBaseGestao);
                                                }
                                            ?>
                                            <select
                                                class="form-select"
                                                id="basegestao"
                                                name="basegestao"
                                                <?= campoObrigatorio($campo) ? 'required' : '' ?>
                                            >
                                                <option value="">Selecione...</option>
                                                <?php foreach ($opcoesBaseGestao as $baseGestaoOpcao): ?>
                                                    <option value="<?= escVeiculo($baseGestaoOpcao) ?>" <?= $valorBaseGestao === $baseGestaoOpcao ? 'selected' : '' ?>>
                                                        <?= escVeiculo($baseGestaoOpcao) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php elseif ($campo === 'unidade'): ?>
                                                <?php
                                                    $valorUnidade = trim((string) ($veiculo['unidade'] ?? ''));
                                                    $opcoesUnidade = [];
                                                    $sqlUnidade = 'SELECT descricao FROM `bdautofrotas`.`tbveiculounidade`';
                                                    $resultadoUnidade = mysqli_query($conn, $sqlUnidade);
                                                    if ($resultadoUnidade) {
                                                        while ($linhaUnidade = mysqli_fetch_assoc($resultadoUnidade)) {
                                                            $descUnidade = trim((string) ($linhaUnidade['descricao'] ?? ''));
                                                            if ($descUnidade !== '') {
                                                                $opcoesUnidade[] = $descUnidade;
                                                            }
                                                        }
                                                        mysqli_free_result($resultadoUnidade);
                                                    }
                                                    sort($opcoesUnidade, SORT_NATURAL | SORT_FLAG_CASE);
                                                ?>
                                                <select
                                                    class="form-select"
                                                    id="unidade"
                                                    name="unidade"
                                                    <?= campoObrigatorio($campo) ? 'required' : '' ?>
                                                >
                                                    <option value="">Selecione...</option>
                                                    <?php foreach ($opcoesUnidade as $unidadeOpcao): ?>
                                                        <option value="<?= escVeiculo($unidadeOpcao) ?>" <?= $valorUnidade === $unidadeOpcao ? 'selected' : '' ?>>
                                                            <?= escVeiculo($unidadeOpcao) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                    <?php elseif ($campo === 'uf'): ?>
                                        <?php
                                            $valorUf = strtoupper(trim((string) ($veiculo['uf'] ?? '')));
                                            $opcoesUf = [];
                                            $sqlUfs = 'SELECT uf, nome_estado FROM `bdautofrotas`.`tb_ufs` ORDER BY uf';
                                            $resultadoUfs = mysqli_query($conn, $sqlUfs);
                                            if ($resultadoUfs) {
                                                while ($linhaUf = mysqli_fetch_assoc($resultadoUfs)) {
                                                    $opcoesUf[] = $linhaUf;
                                                }
                                                mysqli_free_result($resultadoUfs);
                                            }
                                        ?>
                                        <select
                                            class="form-select"
                                            id="uf"
                                            name="uf"
                                            <?= campoObrigatorio($campo) ? 'required' : '' ?>
                                        >
                                            <?php foreach ($opcoesUf as $linhaUf): ?>
                                                <?php
                                                    $siglaUf = strtoupper(trim((string) ($linhaUf['uf'] ?? '')));
                                                ?>
                                                <option value="<?= escVeiculo($siglaUf) ?>" <?= $valorUf === $siglaUf ? 'selected' : '' ?>>
                                                    <?= escVeiculo($siglaUf) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($campo === 'aplicacao'): ?>
                                        <?php
                                            $valorAplicacao = trim((string) ($veiculo['aplicacao'] ?? ''));
                                            $opcoesAplicacao = [];
                                            $sqlAplicacao = 'SELECT idtbaplicacaoveic, aplicacao FROM `bdautofrotas`.`tbveiculoaplicacao` ORDER BY aplicacao';
                                            $resultadoAplicacao = mysqli_query($conn, $sqlAplicacao);
                                            if ($resultadoAplicacao) {
                                                while ($linhaAplicacao = mysqli_fetch_assoc($resultadoAplicacao)) {
                                                    $opcoesAplicacao[] = $linhaAplicacao;
                                                }
                                                mysqli_free_result($resultadoAplicacao);
                                            }
                                        ?>
                                        <select
                                            class="form-select"
                                            id="aplicacao"
                                            name="<?= escVeiculo(nomeCampoPost($campo)) ?>"
                                            <?= campoObrigatorio($campo) ? 'required' : '' ?>
                                        >
                                            <option value="">Selecione...</option>
                                            <?php foreach ($opcoesAplicacao as $linhaAplicacao): ?>
                                                <?php
                                                    $idAplicacao = trim((string) ($linhaAplicacao['idtbaplicacaoveic'] ?? ''));
                                                    $nomeAplicacao = (string) ($linhaAplicacao['aplicacao'] ?? '');
                                                ?>
                                                <option value="<?= escVeiculo($idAplicacao) ?>" <?= $valorAplicacao === $idAplicacao ? 'selected' : '' ?>>
                                                    <?= escVeiculo($nomeAplicacao) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($campo === 'zerokm'): ?>
                                        <?php $valorZeroKm = trim((string) ($veiculo['zerokm'] ?? '')); ?>
                                        <select
                                            class="form-select"
                                            id="zerokm"
                                            name="zerokm"
                                            aria-label="Default select example"
                                            <?= campoObrigatorio($campo) ? 'required' : '' ?>
                                        >
                                            <option value="">Selecione...</option>
                                            <option value="0" <?= $valorZeroKm === '0' ? 'selected' : '' ?>>Não</option>
                                            <option value="1" <?= $valorZeroKm === '1' ? 'selected' : '' ?>>Sim</option>
                                        </select>
                                    <?php elseif ($campo === 'categoria'): ?>
                                        <?php
                                            $valorCategoria = strtolower(trim((string) ($veiculo['categoria'] ?? '')));
                                            $categorias = [];

                                            $sqlCategoria = 'SELECT descricao FROM `bdautofrotas`.`tbveiculocategoria` ORDER BY descricao';
                                            $resultadoCategoria = mysqli_query($conn, $sqlCategoria);
                                            if ($resultadoCategoria) {
                                                while ($linhaCategoria = mysqli_fetch_assoc($resultadoCategoria)) {
                                                    $descricaoCategoria = trim((string) ($linhaCategoria['descricao'] ?? ''));
                                                    if ($descricaoCategoria !== '') {
                                                        $categorias[] = $descricaoCategoria;
                                                    }
                                                }
                                                mysqli_free_result($resultadoCategoria);
                                            }
                                        ?>
                                        <select
                                            class="form-select"
                                            id="categoria"
                                            name="categoria"
                                            aria-label="Default select example"
                                            <?= campoObrigatorio($campo) ? 'required' : '' ?>
                                        >
                                            <option value="">Selecione...</option>
                                            <?php foreach ($categorias as $categoriaOpcao): ?>
                                                <?php $valorOpcao = strtolower(trim(str_replace(' ', '_', $categoriaOpcao))); ?>
                                                <option value="<?= escVeiculo($valorOpcao) ?>" <?= $valorCategoria === $valorOpcao ? 'selected' : '' ?>>
                                                    <?= escVeiculo($categoriaOpcao) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($campo === 'tipo'): ?>
                                        <?php
                                            $valorTipo = strtolower(trim((string) ($veiculo['tipo'] ?? '')));
                                            $opcoesTipo = [];

                                            $sqlTipo = 'SELECT descricao FROM `bdautofrotas`.`tbveiculoclassificacao` ORDER BY descricao';
                                            $resultadoTipo = mysqli_query($conn, $sqlTipo);
                                            if ($resultadoTipo) {
                                                while ($linhaTipo = mysqli_fetch_assoc($resultadoTipo)) {
                                                    $descricaoTipo = trim((string) ($linhaTipo['descricao'] ?? ''));
                                                    if ($descricaoTipo !== '') {
                                                        $opcoesTipo[] = $descricaoTipo;
                                                    }
                                                }
                                                mysqli_free_result($resultadoTipo);
                                            }
                                        ?>
                                        <select
                                            class="form-select"
                                            id="tipo"
                                            name="<?= escVeiculo(nomeCampoPost($campo)) ?>"
                                            aria-label="Default select example"
                                        >
                                            <option value="">Selecione...</option>
                                            <?php foreach ($opcoesTipo as $tipoOpcao): ?>
                                                <?php $valorOpcao = strtolower($tipoOpcao); ?>
                                                <option value="<?= escVeiculo($valorOpcao) ?>" <?= $valorTipo === $valorOpcao ? 'selected' : '' ?> >
                                                    <?= escVeiculo(ucwords(strtolower(str_replace('_', ' ', $tipoOpcao)))) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($campo === 'statusvel'): ?>
                                        <?php
                                            $valorStatusVeiculo = trim((string) ($veiculo['statusvel'] ?? ''));
                                            $statusVeiculoLinhas = [];
                                            $sqlStatusVeiculo = 'SELECT * FROM `bdautofrotas`.`tbvelstatus` ORDER BY status';
                                            $resultadoStatusVeiculo = mysqli_query($conn, $sqlStatusVeiculo);
                                            if ($resultadoStatusVeiculo) {
                                                while ($linhaStatusVeiculo = mysqli_fetch_assoc($resultadoStatusVeiculo)) {
                                                    $statusVeiculoLinhas[] = $linhaStatusVeiculo;
                                                }
                                                mysqli_free_result($resultadoStatusVeiculo);
                                            }

                                            $idsBloqueados = ['18', '7', '13', '19', '39', '32'];
                                        ?>
                                        <select
                                            class="form-select"
                                            id="statusvel"
                                            name="statusvel"
                                            <?= campoObrigatorio($campo) ? 'required' : '' ?>
                                        >
                                            <option value="">Selecione...</option>
                                            <?php if ($valorStatusVeiculo !== '1'): ?>
                                                <?php foreach ($statusVeiculoLinhas as $linhaStatusVeiculo): ?>
                                                    <?php
                                                        $idStatusVeiculo = trim((string) (
                                                            $linhaStatusVeiculo['idtbvelstatus']
                                                            ?? $linhaStatusVeiculo['idtbastatusvel']
                                                            ?? $linhaStatusVeiculo['idtbstatusveic']
                                                            ?? ''
                                                        ));

                                                        if ($idStatusVeiculo === '' || in_array($idStatusVeiculo, $idsBloqueados, true)) {
                                                            continue;
                                                        }

                                                        $nomeStatusVeiculo = trim((string) ($linhaStatusVeiculo['status'] ?? ''));
                                                        $statusGeralBruto = trim((string) ($linhaStatusVeiculo['statusgeral'] ?? ''));
                                                        if ($statusGeralBruto === '0') {
                                                            $classeStatus = 'inativo';
                                                        } elseif ($statusGeralBruto === '1') {
                                                            $classeStatus = 'ativo';
                                                        } else {
                                                            $classeStatus = 'semreg';
                                                        }
                                                    ?>
                                                    <option class="<?= escVeiculo($classeStatus) ?>" value="<?= escVeiculo($idStatusVeiculo) ?>" <?= $idStatusVeiculo === $valorStatusVeiculo ? 'selected' : '' ?>>
                                                        <?= escVeiculo($nomeStatusVeiculo) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <option class="ativo" value="1" selected>CONDUTOR FIXO</option>
                                            <?php endif; ?>
                                        </select>
                                    <?php elseif ($campo === 'tipovel'): ?>
                                        <?php
                                            $valorTipoVeiculo = trim((string) ($veiculo['tipovel'] ?? ''));
                                            $opcoesTipoVeiculo = [];
                                            $sqlTipoVeiculo = 'SELECT * FROM `bdautofrotas`.`tbveltipo` ORDER BY tipo';
                                            $resultadoTipoVeiculo = mysqli_query($conn, $sqlTipoVeiculo);
                                            if ($resultadoTipoVeiculo) {
                                                while ($linhaTipoVeiculo = mysqli_fetch_assoc($resultadoTipoVeiculo)) {
                                                    $opcoesTipoVeiculo[] = $linhaTipoVeiculo;
                                                }
                                                mysqli_free_result($resultadoTipoVeiculo);
                                            }
                                        ?>
                                        <select
                                            class="form-select"
                                            id="tipovel"
                                            name="tipovel"
                                            <?= campoObrigatorio($campo) ? 'required' : '' ?>
                                        >
                                            <option value="">Selecione...</option>
                                            <?php foreach ($opcoesTipoVeiculo as $linhaTipoVeiculo): ?>
                                                <?php
                                                    $idTipoVeiculo = trim((string) (
                                                        $linhaTipoVeiculo['idtbveltipo']
                                                        ?? $linhaTipoVeiculo['idtbatipovel']
                                                        ?? $linhaTipoVeiculo['idtipovel']
                                                        ?? ''
                                                    ));
                                                    $nomeTipoVeiculo = trim((string) ($linhaTipoVeiculo['tipo'] ?? ''));

                                                    if ($idTipoVeiculo === '') {
                                                        continue;
                                                    }
                                                ?>
                                                <option value="<?= escVeiculo($idTipoVeiculo) ?>" <?= $valorTipoVeiculo === $idTipoVeiculo ? 'selected' : '' ?>>
                                                    <?= escVeiculo($nomeTipoVeiculo) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($campo === 'situacao'): ?>
                                        <?php
                                            $valorSituacao = trim((string) ($veiculo['situacao'] ?? ''));
                                            $opcoesSituacao = [];
                                            $sqlSituacao = 'SELECT DISTINCT(situacao) AS situacao FROM `bdautofrotas`.`tbveiculo` WHERE situacao IS NOT NULL AND TRIM(situacao) <> "" ORDER BY situacao';
                                            $resultadoSituacao = mysqli_query($conn, $sqlSituacao);
                                            if ($resultadoSituacao) {
                                                while ($linhaSituacao = mysqli_fetch_assoc($resultadoSituacao)) {
                                                    $opcoesSituacao[] = $linhaSituacao;
                                                }
                                                mysqli_free_result($resultadoSituacao);
                                            }
                                        ?>
                                        <select
                                            class="form-select"
                                            id="situacao"
                                            name="situacao"
                                            <?= campoObrigatorio($campo) ? 'required' : '' ?>
                                        >
                                            <option value="">Selecione...</option>
                                            <?php foreach ($opcoesSituacao as $linhaSituacao): ?>
                                                <?php $situacaoOpcao = trim((string) ($linhaSituacao['situacao'] ?? '')); ?>
                                                <?php if ($situacaoOpcao === '') { continue; } ?>
                                                <option value="<?= escVeiculo($situacaoOpcao) ?>" <?= $valorSituacao === $situacaoOpcao ? 'selected' : '' ?>>
                                                    <?= escVeiculo($situacaoOpcao) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($campo === 'motorizacao'): ?>
                                        <?php
                                            $valorMotorizacao = trim((string) ($veiculo['motorizacao'] ?? ''));

                                            $opcoesMotorizacao = ['1.0', '1.4', '1.6', '1.8', '2.0'];

                                            $sqlMotorizacao = 'SELECT DISTINCT(motorizacao) AS motorizacao FROM `bdautofrotas`.`tbveiculo` WHERE motorizacao IS NOT NULL AND TRIM(motorizacao) <> "" ORDER BY motorizacao';
                                            $resultadoMotorizacao = mysqli_query($conn, $sqlMotorizacao);
                                            if ($resultadoMotorizacao) {
                                                while ($linhaMotorizacao = mysqli_fetch_assoc($resultadoMotorizacao)) {
                                                    $motorizacaoTabela = trim((string) ($linhaMotorizacao['motorizacao'] ?? ''));
                                                    if ($motorizacaoTabela !== '' && !in_array($motorizacaoTabela, $opcoesMotorizacao, true)) {
                                                        $opcoesMotorizacao[] = $motorizacaoTabela;
                                                    }
                                                }
                                                mysqli_free_result($resultadoMotorizacao);
                                            }
                                        ?>
                                        <select
                                            class="form-select"
                                            id="motorizacao"
                                            name="motorizacao"
                                        >
                                            <option value="">Selecione...</option>
                                            <?php foreach ($opcoesMotorizacao as $opcaoMotorizacao): ?>
                                                <option value="<?= escVeiculo($opcaoMotorizacao) ?>" <?= $valorMotorizacao === $opcaoMotorizacao ? 'selected' : '' ?>>
                                                    <?= escVeiculo($opcaoMotorizacao) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($campo === 'gnv'): ?>
                                        <?php
                                            $valorGnv = trim((string) ($veiculo['gnv'] ?? ''));
                                            $opcoesGnv = ['0', '1'];

                                            $sqlGnv = 'SELECT DISTINCT(gnv) AS gnv FROM `bdautofrotas`.`tbveiculo` WHERE gnv IS NOT NULL AND TRIM(gnv) <> "" ORDER BY gnv';
                                            $resultadoGnv = mysqli_query($conn, $sqlGnv);
                                            if ($resultadoGnv) {
                                                while ($linhaGnv = mysqli_fetch_assoc($resultadoGnv)) {
                                                    $gnvTabela = trim((string) ($linhaGnv['gnv'] ?? ''));
                                                    if ($gnvTabela !== '' && !in_array($gnvTabela, $opcoesGnv, true)) {
                                                        $opcoesGnv[] = $gnvTabela;
                                                    }
                                                }
                                                mysqli_free_result($resultadoGnv);
                                            }
                                        ?>
                                        <select
                                            class="form-select"
                                            id="gnv"
                                            name="gnv"
                                            <?= campoObrigatorio($campo) ? 'required' : '' ?>
                                        >
                                            <option value="">Selecione...</option>
                                            <?php foreach ($opcoesGnv as $opcaoGnv): ?>
                                                <?php
                                                    $rotuloGnv = $opcaoGnv === '1' ? 'Sim' : ($opcaoGnv === '0' ? 'Não' : $opcaoGnv);
                                                ?>
                                                <option value="<?= escVeiculo($opcaoGnv) ?>" <?= $valorGnv === $opcaoGnv ? 'selected' : '' ?>>
                                                    <?= escVeiculo($rotuloGnv) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($campo === 'gps'): ?>
                                        <?php
                                            $valorGps = trim((string) ($veiculo['gps'] ?? ''));
                                            $opcoesGps = ['0', '1'];

                                            $sqlGps = 'SELECT DISTINCT(gps) AS gps FROM `bdautofrotas`.`tbveiculo` WHERE gps IS NOT NULL AND TRIM(gps) <> "" ORDER BY gps';
                                            $resultadoGps = mysqli_query($conn, $sqlGps);
                                            if ($resultadoGps) {
                                                while ($linhaGps = mysqli_fetch_assoc($resultadoGps)) {
                                                    $gpsTabela = trim((string) ($linhaGps['gps'] ?? ''));
                                                    if ($gpsTabela !== '' && !in_array($gpsTabela, $opcoesGps, true)) {
                                                        $opcoesGps[] = $gpsTabela;
                                                    }
                                                }
                                                mysqli_free_result($resultadoGps);
                                            }
                                        ?>
                                        <select
                                            class="form-select"
                                            id="gps"
                                            name="gps"
                                            <?= campoObrigatorio($campo) ? 'required' : '' ?>
                                        >
                                            <option value="">Selecione...</option>
                                            <?php foreach ($opcoesGps as $opcaoGps): ?>
                                                <?php
                                                    $rotuloGps = $opcaoGps === '1' ? 'Sim' : ($opcaoGps === '0' ? 'Não' : $opcaoGps);
                                                ?>
                                                <option value="<?= escVeiculo($opcaoGps) ?>" <?= $valorGps === $opcaoGps ? 'selected' : '' ?>>
                                                    <?= escVeiculo($rotuloGps) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($campo === 'tagpedagio'): ?>
                                        <?php
                                            $valorTagPedagio = trim((string) ($veiculo['tagpedagio'] ?? ''));
                                            $opcoesTagPedagio = ['0', '1'];

                                            $sqlTagPedagio = 'SELECT DISTINCT(tagpedagio) AS tagpedagio FROM `bdautofrotas`.`tbveiculo` WHERE tagpedagio IS NOT NULL AND TRIM(tagpedagio) <> "" ORDER BY tagpedagio';
                                            $resultadoTagPedagio = mysqli_query($conn, $sqlTagPedagio);
                                            if ($resultadoTagPedagio) {
                                                while ($linhaTagPedagio = mysqli_fetch_assoc($resultadoTagPedagio)) {
                                                    $tagPedagioTabela = trim((string) ($linhaTagPedagio['tagpedagio'] ?? ''));
                                                    if ($tagPedagioTabela !== '' && !in_array($tagPedagioTabela, $opcoesTagPedagio, true)) {
                                                        $opcoesTagPedagio[] = $tagPedagioTabela;
                                                    }
                                                }
                                                mysqli_free_result($resultadoTagPedagio);
                                            }
                                        ?>
                                        <select
                                            class="form-select"
                                            id="tagpedagio"
                                            name="tagpedagio"
                                        >
                                            <option value="">Selecione...</option>
                                            <?php foreach ($opcoesTagPedagio as $opcaoTagPedagio): ?>
                                                <?php
                                                    $rotuloTagPedagio = $opcaoTagPedagio === '1' ? 'Sim' : ($opcaoTagPedagio === '0' ? 'Não' : $opcaoTagPedagio);
                                                ?>
                                                <option value="<?= escVeiculo($opcaoTagPedagio) ?>" <?= $valorTagPedagio === $opcaoTagPedagio ? 'selected' : '' ?>>
                                                    <?= escVeiculo($rotuloTagPedagio) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($campo === 'rack'): ?>
                                        <?php
                                            $valorRack = trim((string) ($veiculo['rack'] ?? ''));
                                            $opcoesRack = ['0', '1'];

                                            $sqlRack = 'SELECT DISTINCT(rack) AS rack FROM `bdautofrotas`.`tbveiculo` WHERE rack IS NOT NULL AND TRIM(rack) <> "" ORDER BY rack';
                                            $resultadoRack = mysqli_query($conn, $sqlRack);
                                            if ($resultadoRack) {
                                                while ($linhaRack = mysqli_fetch_assoc($resultadoRack)) {
                                                    $rackTabela = trim((string) ($linhaRack['rack'] ?? ''));
                                                    if ($rackTabela !== '' && !in_array($rackTabela, $opcoesRack, true)) {
                                                        $opcoesRack[] = $rackTabela;
                                                    }
                                                }
                                                mysqli_free_result($resultadoRack);
                                            }
                                        ?>
                                        <select
                                            class="form-select"
                                            id="rack"
                                            name="rack"
                                        >
                                            <option value="">Selecione...</option>
                                            <?php foreach ($opcoesRack as $opcaoRack): ?>
                                                <?php
                                                    $rotuloRack = $opcaoRack === '1' ? 'Sim' : ($opcaoRack === '0' ? 'Não' : $opcaoRack);
                                                ?>
                                                <option value="<?= escVeiculo($opcaoRack) ?>" <?= $valorRack === $opcaoRack ? 'selected' : '' ?>>
                                                    <?= escVeiculo($rotuloRack) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($campo === 'blindagem'): ?>
                                        <?php
                                            $valorBlindagem = strtolower(trim((string) ($veiculo['blindagem'] ?? '')));
                                            $opcoesBlindagem = [];

                                            $sqlBlindagem = 'SELECT descricao FROM `bdautofrotas`.`tbveiculoblindagem` ORDER BY descricao';
                                            $resultadoBlindagem = mysqli_query($conn, $sqlBlindagem);
                                            if ($resultadoBlindagem) {
                                                while ($linhaBlindagem = mysqli_fetch_assoc($resultadoBlindagem)) {
                                                    $descricaoBlindagem = trim((string) ($linhaBlindagem['descricao'] ?? ''));
                                                    if ($descricaoBlindagem !== '') {
                                                        $opcoesBlindagem[] = $descricaoBlindagem;
                                                    }
                                                }
                                                mysqli_free_result($resultadoBlindagem);
                                            }
                                        ?>
                                        <select
                                            class="form-select"
                                            id="blindagem"
                                            name="blindagem"
                                        >
                                            <option value="">Selecione...</option>
                                            <?php foreach ($opcoesBlindagem as $blindagemOpcao): ?>
                                                <?php $valorOpcao = strtolower(trim($blindagemOpcao)); ?>
                                                <option value="<?= escVeiculo($blindagemOpcao) ?>" <?= $valorBlindagem === $valorOpcao ? 'selected' : '' ?>>
                                                    <?= escVeiculo($blindagemOpcao) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($campo === 'gpsemp'): ?>
                                        <?php
                                            $valorGpsEmp = trim((string) ($veiculo['gpsemp'] ?? ''));
                                            $opcoesGpsEmp = [];

                                            $sqlGpsEmp = "SELECT fantasia FROM `bdautofrotas`.`tbfornecedor` WHERE tipo = '5' AND TRIM(fantasia) <> '' ORDER BY fantasia";
                                            $resultadoGpsEmp = mysqli_query($conn, $sqlGpsEmp);
                                            if ($resultadoGpsEmp) {
                                                while ($linhaGpsEmp = mysqli_fetch_assoc($resultadoGpsEmp)) {
                                                    $fantasiaGpsEmp = trim((string) ($linhaGpsEmp['fantasia'] ?? ''));
                                                    if ($fantasiaGpsEmp !== '') {
                                                        $opcoesGpsEmp[] = $fantasiaGpsEmp;
                                                    }
                                                }
                                                mysqli_free_result($resultadoGpsEmp);
                                            }
                                        ?>
                                        <select
                                            class="form-select"
                                            id="gpsemp"
                                            name="gpsemp"
                                            <?= campoObrigatorio($campo) ? 'required' : '' ?>
                                        >
                                            <option value="">Selecione...</option>
                                            <?php foreach ($opcoesGpsEmp as $gpsEmpOpcao): ?>
                                                <option value="<?= escVeiculo($gpsEmpOpcao) ?>" <?= $valorGpsEmp === $gpsEmpOpcao ? 'selected' : '' ?>>
                                                    <?= escVeiculo($gpsEmpOpcao) ?>
                                                </option>
                                            <?php endforeach; ?>
                                            <option value="nao possui" <?= $valorGpsEmp === 'nao possui' ? 'selected' : '' ?>>Não possui</option>
                                        </select>
                                    <?php elseif ($campo === 'airbag'): ?>
                                        <?php $valorAirbag = trim((string) ($veiculo['airbag'] ?? '')); ?>
                                        <select
                                            class="form-select"
                                            id="airbag"
                                            name="airbag"
                                            <?= campoObrigatorio($campo) ? 'required' : '' ?>
                                        >
                                            <option value="">Selecione...</option>
                                            <option value="0" <?= $valorAirbag === '0' ? 'selected' : '' ?>>Não</option>
                                            <option value="1" <?= $valorAirbag === '1' ? 'selected' : '' ?>>Sim</option>
                                        </select>
                                    <?php elseif ($campo === 'tipoposse'): ?>
                                        <?php
                                            $valorTipoPosse = trim((string) ($veiculo['tipoposse'] ?? ''));
                                            $opcoesTipoPosse = [];

                                            $sqlTipoPosse = 'SELECT descricao FROM `bdautofrotas`.`tbveiculoposse` ORDER BY descricao';
                                            $resultadoTipoPosse = mysqli_query($conn, $sqlTipoPosse);
                                            if ($resultadoTipoPosse) {
                                                while ($linhaTipoPosse = mysqli_fetch_assoc($resultadoTipoPosse)) {
                                                    $descTipoPosse = trim((string) ($linhaTipoPosse['descricao'] ?? ''));
                                                    if ($descTipoPosse !== '') {
                                                        $opcoesTipoPosse[] = $descTipoPosse;
                                                    }
                                                }
                                                mysqli_free_result($resultadoTipoPosse);
                                            }
                                        ?>
                                        <select
                                            class="form-select"
                                            id="tipoposse"
                                            name="tipoposse"
                                            <?= campoObrigatorio($campo) ? 'required' : '' ?>
                                        >
                                            <option value="">Selecione uma opção</option>
                                            <?php foreach ($opcoesTipoPosse as $tipoOpcao): ?>
                                                <?php $valorOpcaoTipoPosse = strtoupper(trim($tipoOpcao)); ?>
                                                <option value="<?= escVeiculo($valorOpcaoTipoPosse) ?>" <?= $valorTipoPosse === $valorOpcaoTipoPosse ? 'selected' : '' ?>>
                                                    <?= escVeiculo($tipoOpcao) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($campo === 'idlocador'): ?>
                                        <?php
                                            $valorLocador = trim((string) ($veiculo['idlocador'] ?? ''));
                                            $locadores = [];

                                            $sqlLocador = "SELECT idtbfornecedor, fantasia FROM `bdautofrotas`.`tbfornecedor` WHERE tipo = '4' AND status = '1' ORDER BY fantasia";
                                            $resultadoLocador = mysqli_query($conn, $sqlLocador);
                                            if ($resultadoLocador) {
                                                while ($linhaLocador = mysqli_fetch_assoc($resultadoLocador)) {
                                                    $locadores[] = $linhaLocador;
                                                }
                                                mysqli_free_result($resultadoLocador);
                                            }
                                        ?>
                                        <select
                                            class="form-select"
                                            id="idlocador"
                                            name="<?= escVeiculo(nomeCampoPost($campo)) ?>"
                                        >
                                            <option value="">Selecione...</option>
                                            <?php foreach ($locadores as $locador): ?>
                                                <?php
                                                    $idFornecedor = trim((string) ($locador['idtbfornecedor'] ?? ''));
                                                    $fantasiaFornecedor = trim((string) ($locador['fantasia'] ?? ''));
                                                ?>
                                                <option value="<?= escVeiculo($idFornecedor) ?>" <?= $valorLocador === $idFornecedor ? 'selected' : '' ?>>
                                                    <?= escVeiculo($fantasiaFornecedor) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($campo === 'ccusto'): ?>
                                        <?php
                                            $valorCcusto = trim((string) ($veiculo['ccusto'] ?? ''));
                                            $centrosCusto = [];

                                            $sqlCcusto = 'SELECT descricao AS ccusto FROM `bdautofrotas`.`tbccusto` ORDER BY descricao';
                                            $resultadoCcusto = mysqli_query($conn, $sqlCcusto);
                                            if ($resultadoCcusto) {
                                                while ($linhaCcusto = mysqli_fetch_assoc($resultadoCcusto)) {
                                                    $ccustoTabela = trim((string) ($linhaCcusto['ccusto'] ?? ''));
                                                    if ($ccustoTabela !== '') {
                                                        $centrosCusto[] = $ccustoTabela;
                                                    }
                                                }
                                                mysqli_free_result($resultadoCcusto);
                                            }
                                        ?>
                                        <select
                                            class="form-select"
                                            id="ccusto"
                                            name="<?= escVeiculo(nomeCampoPost($campo)) ?>"
                                            <?= campoObrigatorio($campo) ? 'required' : '' ?>
                                        >
                                            <option value="">Selecione...</option>
                                            <?php foreach ($centrosCusto as $ccustoOpcao): ?>
                                                <option value="<?= escVeiculo($ccustoOpcao) ?>" <?= $valorCcusto === $ccustoOpcao ? 'selected' : '' ?>>
                                                    <?= escVeiculo($ccustoOpcao) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($campo === 'combustivel'): ?>
                                        <?php
                                            $valorCombustivel = strtolower(trim((string) ($veiculo['combustivel'] ?? '')));
                                            $opcoesCombustivel = [];

                                            $sqlCombustivel = 'SELECT descricao FROM `bdautofrotas`.`tbveiculocombustivel` ORDER BY descricao';
                                            $resultadoCombustivel = mysqli_query($conn, $sqlCombustivel);
                                            if ($resultadoCombustivel) {
                                                while ($linhaCombustivel = mysqli_fetch_assoc($resultadoCombustivel)) {
                                                    $descricaoCombustivel = trim((string) ($linhaCombustivel['descricao'] ?? ''));
                                                    if ($descricaoCombustivel !== '') {
                                                        $opcoesCombustivel[] = $descricaoCombustivel;
                                                    }
                                                }
                                                mysqli_free_result($resultadoCombustivel);
                                            }
                                        ?>
                                        <select
                                            class="form-select"
                                            id="combustivel"
                                            name="combustivel"
                                            <?= campoObrigatorio($campo) ? 'required' : '' ?>
                                        >
                                            <option value="">Selecione...</option>
                                            <?php foreach ($opcoesCombustivel as $combustivelOpcao): ?>
                                                <?php $valorOpcao = strtolower($combustivelOpcao); ?>
                                                <option value="<?= escVeiculo($valorOpcao) ?>" <?= $valorCombustivel === $valorOpcao ? 'selected' : '' ?> >
                                                    <?= escVeiculo(ucwords(strtolower(str_replace('_', ' ', $combustivelOpcao)))) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif (formatoInput($campo) === 'file'): ?>
                                        <input
                                            class="form-control"
                                            type="file"
                                            id="<?= escVeiculo($campo) ?>"
                                            name="<?= escVeiculo(nomeCampoPost($campo)) ?>"
                                            <?= campoObrigatorio($campo) ? 'required' : '' ?>
                                        >
                                    <?php else: ?>
                                        <?php $valorBruto = $veiculo[$campo] ?? (($campo === 'horamovimentacao') ? ($veiculo['datamovimentacao'] ?? '') : ''); ?>
                                        <input
                                            class="form-control"
                                            type="<?= escVeiculo(formatoInput($campo)) ?>"
                                            id="<?= escVeiculo($campo) ?>"
                                            name="<?= escVeiculo(nomeCampoPost($campo)) ?>"
                                            value="<?= escVeiculo(valorInput($campo, $valorBruto)) ?>"
                                            placeholder="<?= escVeiculo(placeholderCampo($campo)) ?>"
                                            <?= campoObrigatorio($campo) ? 'required' : '' ?><?= atributosValidacao($campo) ?>
                                        >
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($grupoAtual !== null): ?>
                                </div>
                            </section>
                            <?php endif; ?>
                        </div>

                        <div class="col-sm-12 form-floating mt-3 mb-3">
                            <textarea
                                class="form-control"
                                name="obsveiculo"
                                id="obsveiculo"
                                placeholder="Observação"
                                rows="4"
                                cols="50"
                                style="height: 200px !important;"
                            ><?= escVeiculo($veiculo['obsveiculo'] ?? '') ?></textarea>
                            <label for="obsveiculo">Observações:</label>
                        </div>

                        <div class="mt-3 pb-2 d-flex col-sm-12 justify-content-center">
                            <div>
                                <button class="btn btn-success" type="submit">Confirmar edição</button>
                            </div>
                            <div class="ms-5">
                                <a class="btn btn-danger" href="listagem-veiculo.php" onclick="if (window.opener && !window.opener.closed) { window.close(); return false; }">Cancelar edição</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
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
<!--deixado marca e modelo como opcionais e recebendo texto. Lembrar de atualizar depois -->
<?php
require_once __DIR__ . '/../auth.php';

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

require_once __DIR__ . '/../control/conecta.php';
exigirLogin();

$usuarioLogado = $_SESSION['usuario'] ?? 'Usuário';
$matriculaLogada = (string) ($_SESSION['matricula'] ?? $_SESSION['usuario'] ?? '');
$perfilLogado = (string) ($_SESSION['perfil'] ?? '');
$unidades = ['RJ', 'PR', 'SP', 'MG', 'ES', 'TODOS'];
$idsOcultos = [1241, 1767, 1764, 1765, 1893, 1894, 1895, 1896];
$matriculasSemPermissaoEdicao = ['160030', '410109', '501285', '410039', '411425', '003931'];
$basesGestao = [];
$veiculos = [];
$erroConsulta = '';
$hoje = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));

function esc(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function formatarDataVeiculo(?string $data, string $formato = 'd/m/Y'): string
{
    if (empty($data) || $data === '0000-00-00' || $data === '0000-00-00 00:00:00') {
        return '';
    }

    $dataFormatada = date_create($data);

    return $dataFormatada ? date_format($dataFormatada, $formato) : $data;
}

function limparDescricaoAtualizacao(?string $acao): string
{
    if (empty($acao)) {
        return '';
    }

    return str_replace(['-1', '-2', '-3'], '', $acao);
}

function buscarEstadoFuncionario(mysqli $conn, string $databaseName, string $matricula): string
{
    if ($matricula === '') {
        return '';
    }

    $sql = "
        SELECT estado
        FROM `{$databaseName}`.`tbfuncionario`
        WHERE matricula = ?
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return '';
    }

    mysqli_stmt_bind_param($stmt, 's', $matricula);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $funcionario = $resultado ? mysqli_fetch_assoc($resultado) : null;
    mysqli_stmt_close($stmt);

    return $funcionario['estado'] ?? '';
}

function vincularParametros(mysqli_stmt $stmt, string $tipos, array $parametros): bool
{
    $referencias = [];
    foreach ($parametros as $indice => $valor) {
        $referencias[$indice] = &$parametros[$indice];
    }

    return mysqli_stmt_bind_param($stmt, $tipos, ...$referencias);
}

function parametrosSelecionados(array $valores): array
{
    $selecionados = [];

    foreach ($valores as $valor) {
        $valor = trim((string) $valor);
        if ($valor !== '') {
            $selecionados[] = $valor;
        }
    }

    return array_values(array_unique($selecionados));
}

function montarBotaoPost(string $action, array $campos, string $icone, string $titulo): string
{
    $inputs = '';
    foreach ($campos as $nome => $valor) {
        $inputs .= '<input type="hidden" name="' . esc($nome) . '" value="' . esc((string) $valor) . '">';
    }

    return '<form method="post" action="' . esc($action) . '" target="_blank" class="d-inline">'
        . $inputs
        . '<button style="border: none; background-color: transparent;" type="submit" title="' . esc($titulo) . '">'
        . '<span class="material-symbols-outlined">' . esc($icone) . '</span>'
        . '</button></form>';
}

function montarLinhaVeiculo(array $veiculo, DateTimeImmutable $hoje, string $perfilLogado, string $matriculaLogada, bool $bloquearEdicao): array
{
    $tipoposse = (string) ($veiculo['tipoposse'] ?? '');
    if ($tipoposse === 'PROPRIO') {
        $tipoposse = 'PRÓPRIO';
    }

    $corTexto = '';
    if ($tipoposse === 'AGREGADO') {
        $corTexto = 'green';
    } elseif ($tipoposse === 'LOCADO') {
        $corTexto = 'blue';
    } elseif ($tipoposse === 'PRÓPRIO' || $tipoposse === 'PROPRIO') {
        $corTexto = 'purple';
    } elseif ($tipoposse === 'TERCEIRO') {
        $corTexto = 'red';
    }

    $vencLocacaoOriginal = (string) ($veiculo['dtdevolucaoloc'] ?? '');
    $linhaVencida = false;
    if ($vencLocacaoOriginal !== '' && $vencLocacaoOriginal !== '0000-00-00' && $vencLocacaoOriginal !== '0000-00-00 00:00:00') {
        $vencimento = date_create_immutable($vencLocacaoOriginal);
        $linhaVencida = $vencimento instanceof DateTimeImmutable && $hoje > $vencimento;
    }

    $statusGeral = (string) ($veiculo['status'] ?? '');
    if ($statusGeral === '1') {
        $statusGeral = 'ATIVO';
    } elseif ($statusGeral === '0') {
        $statusGeral = 'INATIVO';
    }

    $situacao = (string) ($veiculo['situacao'] ?? 'CONDUTOR FIXO');
    if (($veiculo['ccusto'] ?? '') === 'CIA GRUPO') {
        $situacao = 'MULTICONDUTORES CIA-GRUPO';
    }

    $acao = limparDescricaoAtualizacao($veiculo['ultima_movimentacao'] ?? '');
    $dataMov = (string) ($veiculo['data_ultima_movimentacao'] ?? '');
    $autor = (string) ($veiculo['feita_por'] ?? '');

    if ($acao === 'Associou veículo' && $dataMov !== '' && $dataMov <= '2023-09-20 00:00:00') {
        $dataMov = '';
        $autor = '';
    }

    $placa = (string) ($veiculo['placa'] ?? '');
    $matCond = (string) ($veiculo['matcond'] ?? '');
    $idVeiculo = (string) ($veiculo['idtbveiculo'] ?? '');
    $idManutencao = (string) ($veiculo['idtbmanprev'] ?? '');
    $emManutencao = !empty($idManutencao);

    $camposAutor = [
        'matr_autor' => $matriculaLogada,
        'perfil_autor' => $perfilLogado,
    ];

    $podeExibirAcoes = !$bloquearEdicao;
    $manutencaoHtml = '';
    $editarHtml = '';
    $documentosHtml = '';
    $apagarHtml = '';
    $infoPlaca = '';
    $infoCondutor = '';

    $infoPlaca = montarBotaoPost('dadosplaca.php', [
        'placa' => $placa,
        'perfil' => $perfilLogado,
        'matr_autor' => $matriculaLogada,
    ], 'info', 'Histórico Placa');

    if ($matCond !== '') {
        $infoCondutor = montarBotaoPost('/../autofrota/condutores/dados-condutor.php', [
            'matcond' => $matCond,
        ], 'info', 'Histórico Condutor');
    }

    if ($podeExibirAcoes) {
        if ($emManutencao) {
            $manutencaoHtml = montarBotaoPost('/../autofrota/manutencoes/editar-manutencao.php?idtbmanprev=' . rawurlencode($idManutencao) . '&placa=' . rawurlencode($placa), $camposAutor + [
                'idtbmanprev' => $idManutencao,
                'placa' => $placa,
            ], 'edit_note', 'Editar manutenção cadastrada');
        } else {
            $manutencaoHtml = montarBotaoPost('/../autofrota/manutencoes/cadastrar-manutencao-preventiva.php?placa=' . rawurlencode($placa), $camposAutor + [
                'placa' => $placa,
            ], 'add_circle', 'Cadastrar nova manutenção');
        }

        $editarHtml = montarBotaoPost('editar-veiculo.php', $camposAutor + [
            'idtbveiculo' => $idVeiculo,
        ], 'edit_note', 'Editar Veículo');

        $documentosHtml = montarBotaoPost('enviar-documento-veiculo.php?idtbveiculo=' . rawurlencode($idVeiculo) . '&placa=' . rawurlencode($placa), $camposAutor + [
            'idtbveiculo' => $idVeiculo,
            'placa' => $placa,
        ], 'attach_file', 'Enviar Documentos');

        $apagarHtml = '<button type="button" data-bs-toggle="modal" data-bs-target="#apagarveic" '
            . 'data-idtbveiculo="' . esc($idVeiculo) . '" data-placa="' . esc($placa) . '" '
            . 'style="border: none; background-color: transparent;" title="Apagar registro">'
            . '<span class="material-symbols-outlined">delete</span></button>';
    }

    return [
        'style' => trim(($linhaVencida ? 'background-color: #F0F0F0;' : 'background-color: white;') . ($corTexto ? ' color: ' . $corTexto . ';' : '')),
        'cells' => [
            esc($placa) . $infoPlaca,
            esc($veiculo['nomecond'] ?? '') . $infoCondutor,
            esc($veiculo['cargo_condutor'] ?? ''),
            esc($matCond),
            esc($situacao),
            esc($statusGeral),
            esc($veiculo['aplicacao_nome'] ?? ''),
            '<span title="Oficina: ' . esc($veiculo['oficina'] ?? '') . '">' . ($emManutencao ? 'SIM' : 'NÃO') . '</span>',
            esc($tipoposse),
            esc(formatarDataVeiculo($vencLocacaoOriginal)),
            esc($veiculo['modelo_nome'] ?? ''),
            esc($acao),
            esc(formatarDataVeiculo($dataMov, 'd/m/Y H:i:s')),
            esc($autor),
            $manutencaoHtml,
            $editarHtml,
            $documentosHtml,
            $apagarHtml,
        ],
    ];
}

$estadoFuncionario = '';
if (isset($conn) && $conn instanceof mysqli) {
    $estadoFuncionario = buscarEstadoFuncionario($conn, $databaseName, $matriculaLogada);
}

$unidadePostada = $_POST['unidade'] ?? '';
$unidadeSelecionada = in_array($unidadePostada, $unidades, true) ? $unidadePostada : ($estadoFuncionario ?: 'TODOS');
if (!in_array($unidadeSelecionada, $unidades, true)) {
    $unidadeSelecionada = 'TODOS';
}

$statusSelecionado = trim((string) ($_POST['status'] ?? ''));
$statusAtivoSelecionado = $statusSelecionado === '1' || ($_POST['statusa'] ?? '') === '1';
$statusInativoSelecionado = $statusSelecionado === '0' || ($_POST['statusi'] ?? '') === '0';
$statusSelecionados = [];
if ($statusAtivoSelecionado) {
    $statusSelecionados[] = '1';
}
if ($statusInativoSelecionado) {
    $statusSelecionados[] = '0';
}

$basesPostadas = $_POST['bases'] ?? [];
if (!is_array($basesPostadas)) {
    $basesPostadas = [$basesPostadas];
}
$basesSelecionadas = parametrosSelecionados($basesPostadas);
$bloquearEdicao = in_array($matriculaLogada, $matriculasSemPermissaoEdicao, true);

if (isset($conn) && $conn instanceof mysqli) {
    $sqlBases = "
        SELECT DISTINCT basegestao, unidade
        FROM `{$databaseName}`.`tbveiculo`
        WHERE basegestao IS NOT NULL
          AND basegestao <> ''
        ORDER BY basegestao
    ";
    $resultadoBases = mysqli_query($conn, $sqlBases);
    if ($resultadoBases) {
        while ($base = mysqli_fetch_assoc($resultadoBases)) {
            $basesGestao[] = [
                'basegestao' => $base['basegestao'] ?? '',
                'unidade' => $base['unidade'] ?? '',
            ];
        }
        mysqli_free_result($resultadoBases);
    }

    $where = [
        'v.visivel = 1',
        'v.idtbveiculo NOT IN (' . implode(',', array_fill(0, count($idsOcultos), '?')) . ')',
    ];
    $tipos = str_repeat('i', count($idsOcultos));
    $parametros = $idsOcultos;

    if ($unidadeSelecionada !== 'TODOS') {
        $where[] = 'v.unidade = ?';
        $tipos .= 's';
        $parametros[] = $unidadeSelecionada;
    }

    if ($basesSelecionadas !== []) {
        $where[] = 'v.basegestao IN (' . implode(',', array_fill(0, count($basesSelecionadas), '?')) . ')';
        $tipos .= str_repeat('s', count($basesSelecionadas));
        array_push($parametros, ...$basesSelecionadas);
    }

    if ($statusSelecionados !== []) {
        $where[] = 'v.status IN (' . implode(',', array_fill(0, count($statusSelecionados), '?')) . ')';
        $tipos .= str_repeat('s', count($statusSelecionados));
        array_push($parametros, ...$statusSelecionados);
    }

    $sqlVeiculos = "
        SELECT
            v.idtbveiculo,
            v.placa,
            v.matcond,
            v.ccusto,
            v.status,
            v.statusvel,
            v.aplicacao,
            v.tipoposse,
            v.dtdevolucaoloc,
            v.modelo,
            COALESCE(sv.status, 'CONDUTOR FIXO') AS situacao,
            av.aplicacao AS aplicacao_nome,
            mv.modelo AS modelo_nome,
            func.nome AS nomecond,
            func.cargo AS cargo_condutor,
            man.idtbmanprev,
            man.oficina,
            logv.acao AS ultima_movimentacao,
            logv.mat_autor,
            logv.data_e_hora AS data_ultima_movimentacao,
            CASE
                WHEN logv.mat_autor IN ('003535', '003427') THEN 'EQUIPE TI'
                ELSE COALESCE(autor.nome, logv.mat_autor, '')
            END AS feita_por
        FROM `{$databaseName}`.`tbveiculo` v
        LEFT JOIN `{$databaseName}`.`tbveiculostatus` sv
            ON sv.idtbstatusveic = v.statusvel
        LEFT JOIN `{$databaseName}`.`tbveiculoaplicacao` av
            ON av.idtbaplicacaoveic = v.aplicacao
        LEFT JOIN `{$databaseName}`.`tbveiculomodelo` mv
            ON mv.idtbmodeloveic = v.modelo
        LEFT JOIN `{$databaseName}`.`tbfuncionario` func
            ON func.matricula = v.matcond
        LEFT JOIN (
            SELECT man_atual.*
            FROM `{$databaseName}`.`tbmanprev` man_atual
            INNER JOIN (
                SELECT placa, MAX(idtbmanprev) AS idtbmanprev
                FROM `{$databaseName}`.`tbmanprev`
                WHERE status = 'ABERTO'
                GROUP BY placa
            ) ultima_man
                ON ultima_man.idtbmanprev = man_atual.idtbmanprev
        ) man
            ON man.placa = v.placa
        LEFT JOIN (
            SELECT log_atual.*
            FROM `{$databaseName}`.`tblog` log_atual
            INNER JOIN (
                SELECT placa, MAX(idtblog) AS idtblog
                FROM `{$databaseName}`.`tblog`
                WHERE tipo IN ('cadastro', 'edição', 'checklist')
                GROUP BY placa
            ) ultimo_log
                ON ultimo_log.idtblog = log_atual.idtblog
        ) logv
            ON logv.placa = v.placa
        LEFT JOIN `{$databaseName}`.`tbfuncionario` autor
            ON autor.matricula = logv.mat_autor
        WHERE " . implode(' AND ', $where) . "
        ORDER BY v.placa ASC
    ";

    $stmtVeiculos = mysqli_prepare($conn, $sqlVeiculos);
    if ($stmtVeiculos) {
        vincularParametros($stmtVeiculos, $tipos, $parametros);
        mysqli_stmt_execute($stmtVeiculos);
        $resultadoVeiculos = mysqli_stmt_get_result($stmtVeiculos);

        if ($resultadoVeiculos) {
            while ($veiculo = mysqli_fetch_assoc($resultadoVeiculos)) {
                if (($veiculo['placa'] ?? '') !== '') {
                    $veiculos[] = montarLinhaVeiculo($veiculo, $hoje, $perfilLogado, $matriculaLogada, $bloquearEdicao);
                }
            }
            mysqli_free_result($resultadoVeiculos);
        } else {
            $erroConsulta = mysqli_error($conn);
        }

        mysqli_stmt_close($stmtVeiculos);
    } else {
        $erroConsulta = mysqli_error($conn);
    }
} else {
    $erroConsulta = 'Não foi possível conectar ao banco configurado.';
}

$filtrosAplicados = [];
if ($unidadeSelecionada !== '') {
    $filtrosAplicados[] = '<strong>Unidade:</strong> ' . esc($unidadeSelecionada);
}
if ($basesSelecionadas !== []) {
    $filtrosAplicados[] = '<strong>Base(s) de Gestão:</strong> ' . esc(implode(', ', $basesSelecionadas));
}
$statusAplicados = [];
if ($statusAtivoSelecionado) {
    $statusAplicados[] = 'Ativo';
}
if ($statusInativoSelecionado) {
    $statusAplicados[] = 'Inativo';
}
if ($statusAplicados !== []) {
    $filtrosAplicados[] = '<strong>Status:</strong> ' . esc(implode(', ', $statusAplicados));
}

$mensagemRetorno = trim((string) ($_GET['msg'] ?? ''));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="AutoFrota" />
    <meta name="author" content="FFA" />
    <title>AutoFrota - Veículos Cadastrados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../src/autofrota-botoes.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body { background: #ffffff; color: #000000; font-size: 12px; }
        .page-title { font-size: 24px; font-weight: 600; margin: 0 0 10px; }
        .form-label { margin-bottom: 6px; }
        .form-select, .form-control { font-size: 12px; border-radius: 2px; }
        .btn { font-size: 12px; border-radius: 3px; padding: 6px 10px; }
        .actions { gap: 6px; margin-bottom: 12px; }
        .filter-area { margin-bottom: 16px; }
        .filter-form { width: 100%; }
        .filter-row-inline {
            align-items: end;
            display: flex;
            gap: 8px;
        }
        .filter-col-unidade { flex: 0 1 150px; min-width: 130px; }
        .filter-col-base { flex: 0 1 320px; min-width: 220px; }
        .filter-col-status { flex: 0 1 170px; min-width: 150px; }
        .filter-col-button { flex: 0 0 auto; }
        table.dataTable thead th { color: #000000; font-size: 12px; vertical-align: bottom; }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate { font-size: 12px; }
        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select { font-size: 12px; border: 1px solid #aaa; border-radius: 0; padding: 4px; }
        .legend-list { list-style: none; padding-left: 0; margin-top: 10px; line-height: 2; }
        .material-symbols-outlined { font-size: 18px; vertical-align: middle; }
        @media (max-width: 992px) {
            .filter-row-inline {
                align-items: stretch;
                flex-direction: column;
            }
            .filter-col-unidade,
            .filter-col-base,
            .filter-col-status,
            .filter-col-button {
                min-width: 0;
                width: 100%;
            }
            .filter-col-button .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body class="sb-nav-fixed">
    <?php include __DIR__ . '/../includes/menu_superior_simples.php'; ?>

        <div id="layoutSidenav_content">
        <main class="page-wrapper py-3">
            <h1 class="page-title">Veículos Cadastrados</h1>

            <section class="filter-area">
                <form action="listagem-veiculo.php" method="post" class="filter-form">
                    <div class="filter-row-inline">
                        <div class="mb-2 filter-col-unidade">
                            <label class="form-label" for="unidade">Selecione unidade:</label>
                            <select class="form-select" name="unidade" id="unidade">
                                <?php foreach ($unidades as $unidade): ?>
                                    <option value="<?= esc($unidade) ?>" <?= $unidadeSelecionada === $unidade ? 'selected' : '' ?>><?= esc($unidade) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-2 filter-col-base">
                            <label class="form-label" for="bases">Selecione a base gestão:</label>
                            <select class="form-select" name="bases[]" id="bases">
                                <option value="">Todas as bases</option>
                                <?php $basesRenderizadas = []; ?>
                                <?php foreach ($basesGestao as $base): ?>
                                    <?php $baseNome = (string) ($base['basegestao'] ?? ''); ?>
                                    <?php if ($baseNome !== '' && !isset($basesRenderizadas[$baseNome])): ?>
                                        <?php $basesRenderizadas[$baseNome] = true; ?>
                                        <option value="<?= esc($baseNome) ?>" <?= in_array($baseNome, $basesSelecionadas, true) ? 'selected' : '' ?>><?= esc($baseNome) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-2 filter-col-status">
                            <label class="form-label" for="status">Selecione o status:</label>
                            <select class="form-select" name="status" id="status">
                                <option value="">Todos</option>
                                <option value="1" <?= $statusAtivoSelecionado ? 'selected' : '' ?>>Ativo</option>
                                <option value="0" <?= $statusInativoSelecionado ? 'selected' : '' ?>>Inativo</option>
                            </select>
                        </div>

                        <div class="mb-2 filter-col-button">
                            <button type="submit" class="btn btn-filter-blue">Aplicar Filtros</button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="d-flex justify-content-start actions flex-wrap align-items-center">
                <a class="btn btn-success" href="cadastroveiculo.php">Cadastrar Veículo</a>
                <a class="btn btn-secondary" href="inventario-veiculo.php">Inventário de Veículos</a>
                <a class="btn btn-secondary" href="/../autofrota/manutencoes/manutencao-listagem.php">Veículos em Manutenção</a>
                <a class="btn btn-primary" href="importar-hodometro.php">Atualização de Hodômetro em Lote</a>
                <form action="excel/exportar-veiculo-completo.php" 
                 method="post" target="_blank" class="d-inline">
                    <input type="hidden" name="matr_autor" value="<?= esc($matriculaLogada) ?>">
                    <input type="hidden" name="unidades" value="<?= esc($unidadeSelecionada) ?>">
                    <?php foreach ($basesSelecionadas as $baseSelecionada): ?>
                        <input type="hidden" name="bases[]" value="<?= esc($baseSelecionada) ?>">
                    <?php endforeach; ?>
                    <input type="hidden" name="statusa" value="<?= $statusAtivoSelecionado ? '1' : '' ?>">
                    <input type="hidden" name="statusi" value="<?= $statusInativoSelecionado ? '0' : '' ?>">
                    <button type="submit" class="btn btn-excel-green">Gerar Excel Veículos</button>
                </form>
            </section>

            <?php if ($filtrosAplicados !== []): ?>
                <div class="alert alert-info mt-3" role="alert">
                    <i class="fas fa-filter me-2"></i><strong>Filtros Aplicados:</strong><br>
                    <?= implode(' | ', $filtrosAplicados) ?>
                </div>
            <?php endif; ?>

            <?php if ($erroConsulta !== ''): ?>
                <div class="alert alert-warning" role="alert"><?= esc($erroConsulta) ?></div>
            <?php endif; ?>

            <?php if ($mensagemRetorno !== ''): ?>
                <div class="alert alert-info" role="alert"><?= esc($mensagemRetorno) ?></div>
            <?php endif; ?>

            <section style="width: 100%;" class="mt-4">
                <table id="tabelaVeiculos" class="table table-striped" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Placa</th>
                            <th>Condutor</th>
                            <th>Cargo</th>
                            <th>Matrícula Condutor</th>
                            <th>Situação</th>
                            <th>Status Veículo</th>
                            <th>Aplicação</th>
                            <th>Em manutenção?</th>
                            <th>Tipo de posse</th>
                            <th>Vencimento da locação</th>
                            <th>Modelo</th>
                            <th>Últ. movimentação</th>
                            <th>Data Últ. movimentação</th>
                            <th>Feita por</th>
                            <th>Cadastrar/Editar Manutenção</th>
                            <th>Editar Veículo</th>
                            <th>Enviar Documentos</th>
                            <th>Apagar Registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($veiculos as $veiculo): ?>
                            <tr style="<?= esc($veiculo['style']) ?>">
                                <?php foreach ($veiculo['cells'] as $cell): ?>
                                    <td><?= $cell ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <p class="mt-2 mb-1">Legenda:</p>
            <ul class="legend-list">
                <li style="color: green;">Veículos Agregados</li>
                <li style="color: blue;">Veículos Locados</li>
                <li style="color: purple;">Veículos Próprios</li>
                <li style="color: red;">Veículos Terceiros</li>
            </ul>


        </main>
        </div>
    </div>

    <div class="modal fade" id="apagarveic" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Apagar veículo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    Confirma apagar o registro do veículo <strong id="placaApagar"></strong>?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="post" action="control/apagarveiculo.php">
                        <input type="hidden" name="idtbveiculo" id="idVeiculoApagar" value="">
                        <input type="hidden" name="placa" id="placaApagarInput" value="">
                        <input type="hidden" name="matr_autor" value="<?= esc($matriculaLogada) ?>">
                        <input type="hidden" name="escolha" value="1">
                        <button type="submit" class="btn btn-danger">Apagar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', function(event) {
            event.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
        });

        const modalApagarVeiculo = document.getElementById('apagarveic');
        if (modalApagarVeiculo) {
            modalApagarVeiculo.addEventListener('show.bs.modal', function (event) {
                const botao = event.relatedTarget;
                const idtbveiculo = botao?.getAttribute('data-idtbveiculo') || '';
                const placa = botao?.getAttribute('data-placa') || '';

                document.getElementById('idVeiculoApagar').value = idtbveiculo;
                document.getElementById('placaApagar').textContent = placa;
                document.getElementById('placaApagarInput').value = placa;
            });
        }

        $(document).ready(function() {
            $('#tabelaVeiculos').DataTable({
                language: {
                    emptyTable: 'Nada para exibir',
                    zeroRecords: 'Nada para exibir',
                    lengthMenu: 'Exibir _MENU_ registros',
                    search: 'Buscar:',
                    info: 'Exibindo página _PAGE_ de _PAGES_ de _TOTAL_ registros',
                    infoEmpty: 'Exibindo página 0 de 0 de 0 registros',
                    infoFiltered: '',
                    paginate: {
                        first: 'Primeira',
                        last: 'Última',
                        next: 'Próxima',
                        previous: 'Anterior'
                    }
                }
            });
        });
    </script>
</body>
</html>
<!--  -->
<!--  -->
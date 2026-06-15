<?php
require_once __DIR__ . '/../auth.php';

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

require_once __DIR__ . '/../control/conecta.php';
exigirLogin();

$matriculaLogada = (string) ($_SESSION['matricula'] ?? $_POST['matricula'] ?? $_SESSION['usuario'] ?? '');
$perfilLogado = (string) ($_SESSION['perfil'] ?? $_POST['perfil'] ?? '');
$idsOcultos = [1241, 1767, 1764, 1765, 1893, 1894, 1895, 1896];
$matriculasSemPermissaoEdicao = ['160030', '410109', '500422', '501285', '410039', '501640', '500459', '411425', '003931'];
$unidades = [];
$basesGestao = [];
$veiculos = [];
$erroConsulta = '';
$hoje = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));

function esc(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function formatarDataInventario(?string $data, string $formato = 'd/m/Y'): string
{
    if (empty($data) || $data === '0000-00-00' || $data === '0000-00-00 00:00:00') {
        return '';
    }

    $dataFormatada = date_create($data);

    return $dataFormatada ? date_format($dataFormatada, $formato) : $data;
}

function vincularParametrosInventario(mysqli_stmt $stmt, string $tipos, array $parametros): bool
{
    $referencias = [];
    foreach ($parametros as $indice => $valor) {
        $referencias[$indice] = &$parametros[$indice];
    }

    return mysqli_stmt_bind_param($stmt, $tipos, ...$referencias);
}

function parametrosSelecionadosInventario(array $valores): array
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

function montarBotaoEditarInventario(string $idVeiculo, string $matriculaLogada, string $perfilLogado): string
{
    return '<form method="post" action="editarcadveiculo.php" target="_blank" class="d-inline">'
        . '<input type="hidden" name="matr_autor" value="' . esc($matriculaLogada) . '">'
        . '<input type="hidden" name="perfil_autor" value="' . esc($perfilLogado) . '">'
        . '<input type="hidden" name="idtbveiculo" value="' . esc($idVeiculo) . '">'
        . '<button style="border: none; background-color: transparent;" type="submit" title="Editar Veículo">'
        . '<span class="material-symbols-outlined">edit_note</span>'
        . '</button></form>';
}

function calcularDiasVencimento(?string $data, DateTimeImmutable $hoje): string
{
    if (empty($data) || $data === '0000-00-00' || $data === '0000-00-00 00:00:00') {
        return '';
    }

    $validade = date_create_immutable($data);
    if (!$validade) {
        return '';
    }

    $validade = DateTimeImmutable::createFromFormat('Y-m-d', $validade->format('Y-m-d')) ?: $validade;
    $hojeData = DateTimeImmutable::createFromFormat('Y-m-d', $hoje->format('Y-m-d')) ?: $hoje;
    $dias = (int) $hojeData->diff($validade)->format('%r%a');

    return (string) $dias;
}

function statusVeiculoTexto(?string $status): string
{
    if ((string) $status === '1') {
        return 'ATIVO';
    }

    if ((string) $status === '0') {
        return 'INATIVO';
    }

    return (string) $status;
}

function statusFuncionarioTexto(?string $status): string
{
    $statusNormalizado = strtoupper(trim((string) $status));

    if ($statusNormalizado === '1' || $statusNormalizado === 'A' || $statusNormalizado === 'ATIVO') {
        return 'ATIVO';
    }

    if ($statusNormalizado === '0' || $statusNormalizado === 'I' || $statusNormalizado === 'INATIVO') {
        return 'INATIVO';
    }

    return (string) $status;
}

$unidadePostada = trim((string) ($_POST['unidade'] ?? ''));
$basePostada = $_POST['bases'] ?? [];
if (!is_array($basePostada)) {
    $basePostada = [$basePostada];
}
$basesSelecionadas = parametrosSelecionadosInventario($basePostada);
$statusSelecionado = trim((string) ($_POST['statusa'] ?? ''));
$tipoPosseSelecionado = trim((string) ($_POST['tp'] ?? ''));
if ($tipoPosseSelecionado === 'PRÓPRIO') {
    $tipoPosseSelecionado = 'PROPRIO';
}
$bloquearEdicao = in_array($matriculaLogada, $matriculasSemPermissaoEdicao, true);
$colunaStatusFuncionario = null;

if (isset($conn) && $conn instanceof mysqli) {
    $colunasStatusCandidatas = ['status', 'situacao', 'sitfunc'];
    foreach ($colunasStatusCandidatas as $colunaCandidata) {
        $resultadoColunaStatus = mysqli_query(
            $conn,
            "SHOW COLUMNS FROM `{$databaseName}`.`tbfuncionario` LIKE '" . mysqli_real_escape_string($conn, $colunaCandidata) . "'"
        );

        if ($resultadoColunaStatus && mysqli_num_rows($resultadoColunaStatus) > 0) {
            $colunaStatusFuncionario = $colunaCandidata;
            mysqli_free_result($resultadoColunaStatus);
            break;
        }

        if ($resultadoColunaStatus) {
            mysqli_free_result($resultadoColunaStatus);
        }
    }

    $selectStatusFuncionario = $colunaStatusFuncionario !== null
        ? "func.`{$colunaStatusFuncionario}` AS condutor_status"
        : "'' AS condutor_status";

    $resultadoUnidades = mysqli_query($conn, "
        SELECT DISTINCT unidade
        FROM `{$databaseName}`.`tbveiculo`
        WHERE unidade IS NOT NULL
          AND unidade <> ''
        ORDER BY unidade
    ");
    if ($resultadoUnidades) {
        while ($unidade = mysqli_fetch_assoc($resultadoUnidades)) {
            $unidades[] = (string) ($unidade['unidade'] ?? '');
        }
        mysqli_free_result($resultadoUnidades);
    }

    $resultadoBases = mysqli_query($conn, "
        SELECT DISTINCT basegestao, unidade
        FROM `{$databaseName}`.`tbveiculo`
        WHERE basegestao IS NOT NULL
          AND basegestao <> ''
        ORDER BY basegestao
    ");
    if ($resultadoBases) {
        while ($base = mysqli_fetch_assoc($resultadoBases)) {
            $basesGestao[] = [
                'basegestao' => (string) ($base['basegestao'] ?? ''),
                'unidade' => (string) ($base['unidade'] ?? ''),
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

    if ($unidadePostada !== '' && $unidadePostada !== 'TODOS') {
        $where[] = 'v.unidade = ?';
        $tipos .= 's';
        $parametros[] = $unidadePostada;
    }

    if ($basesSelecionadas !== []) {
        $where[] = 'v.basegestao IN (' . implode(',', array_fill(0, count($basesSelecionadas), '?')) . ')';
        $tipos .= str_repeat('s', count($basesSelecionadas));
        array_push($parametros, ...$basesSelecionadas);
    }

    if ($statusSelecionado === '0' || $statusSelecionado === '1') {
        $where[] = 'v.status = ?';
        $tipos .= 's';
        $parametros[] = $statusSelecionado;
    }

    if ($tipoPosseSelecionado !== '') {
        if ($tipoPosseSelecionado === 'PROPRIO') {
            $where[] = 'v.tipoposse IN (?, ?)';
            $tipos .= 'ss';
            $parametros[] = 'PROPRIO';
            $parametros[] = 'PRÓPRIO';
        } else {
            $where[] = 'v.tipoposse = ?';
            $tipos .= 's';
            $parametros[] = $tipoPosseSelecionado;
        }
    }

    $sqlVeiculos = "
        SELECT
            v.*,
            COALESCE(sv.status, v.statusvel, '') AS status_veiculo,
            COALESCE(av.aplicacao, v.aplicacao, '') AS aplicacao_nome,
            COALESCE(mv.modelo, v.modelo, '') AS modelo_nome,
            func.nome AS condutor_nome,
            func.cpf AS condutor_cpf,
            {$selectStatusFuncionario},
            func.cargo AS condutor_cargo,
            cn.numcnh,
            cn.validade AS validade_cnh,
            man.oficina AS oficina_manutencao,
            logv.acao AS ultima_movimentacao,
            logv.data_e_hora AS data_ultima_movimentacao,
            CASE
                WHEN logv.mat_autor IN ('003535', '003427') THEN 'EQUIPE TI'
                ELSE COALESCE(autor.nome, logv.mat_autor, '')
            END AS realizada_por
        FROM `{$databaseName}`.`tbveiculo` v
        LEFT JOIN `{$databaseName}`.`tbveiculostatus` sv
            ON sv.idtbstatusveic = v.statusvel
        LEFT JOIN `{$databaseName}`.`tbveiculoaplicacao` av
            ON av.idtbaplicacaoveic = v.aplicacao
        LEFT JOIN `{$databaseName}`.`tbveiculomodelo` mv
            ON mv.idtbmodeloveic = v.modelo
        LEFT JOIN `{$databaseName}`.`tbfuncionario` func
            ON func.matricula = v.matcond
        LEFT JOIN `{$databaseName}`.`tbcnh` cn
            ON cn.matricula = v.matcond
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
        vincularParametrosInventario($stmtVeiculos, $tipos, $parametros);
        mysqli_stmt_execute($stmtVeiculos);
        $resultadoVeiculos = mysqli_stmt_get_result($stmtVeiculos);

        if ($resultadoVeiculos) {
            while ($veiculo = mysqli_fetch_assoc($resultadoVeiculos)) {
                $veiculos[] = $veiculo;
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
if ($unidadePostada !== '') {
    $filtrosAplicados[] = '<strong>Unidade:</strong> ' . esc($unidadePostada === 'TODOS' ? 'TODOS' : $unidadePostada);
}
if ($basesSelecionadas !== []) {
    $filtrosAplicados[] = '<strong>Base(s) de Gestão:</strong> ' . esc(implode(', ', $basesSelecionadas));
}
if ($statusSelecionado === '1' || $statusSelecionado === '0') {
    $filtrosAplicados[] = '<strong>Status Operacional:</strong> ' . esc(statusVeiculoTexto($statusSelecionado));
}
if ($tipoPosseSelecionado !== '') {
    $filtrosAplicados[] = '<strong>Propriedade:</strong> ' . esc($tipoPosseSelecionado === 'PROPRIO' ? 'PRÓPRIO' : $tipoPosseSelecionado);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="AutoFrota" />
    <meta name="author" content="FFA" />
    <title>AutoFrota - Relatório de Inventário</title>
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
        .filter-area { margin-bottom: 16px; }
        .actions { gap: 6px; margin-bottom: 12px; }
        .filter-form { width: 100%; }
        .filter-row-inline {
            align-items: end;
            display: flex;
            gap: 8px;
        }
        .filter-col-unidade { flex: 0 1 170px; min-width: 140px; }
        .filter-col-base { flex: 0 1 320px; min-width: 220px; }
        .filter-col-tp { flex: 0 1 180px; min-width: 160px; }
        .filter-col-status { flex: 0 1 180px; min-width: 160px; }
        .filter-col-button { flex: 0 0 auto; }
        table.dataTable thead th { color: #000000; font-size: 12px; vertical-align: bottom; }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate { font-size: 12px; }
        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select { font-size: 12px; border: 1px solid #aaa; border-radius: 0; padding: 4px; }
        .material-symbols-outlined { font-size: 18px; vertical-align: middle; }
        .dias-vencido { color: red; }
        .dias-ok { color: blue; }
        @media (max-width: 992px) {
            .filter-row-inline {
                align-items: stretch;
                flex-direction: column;
            }
            .filter-col-unidade,
            .filter-col-base,
            .filter-col-tp,
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
            <h1 class="page-title">Relatório Inventário de Veículos</h1>

            <section class="filter-area">
                <form action="inventario-veiculo.php" method="post" class="filter-form">
                    <input type="hidden" name="matricula" value="<?= esc($matriculaLogada) ?>">
                    <input type="hidden" name="perfil" value="<?= esc($perfilLogado) ?>">
                    <div class="filter-row-inline">
                        <div class="mb-2 filter-col-unidade">
                            <label class="form-label" for="unidade">Selecione a unidade:</label>
                            <select class="form-select" name="unidade" id="unidade">
                                <option value="">Selecione...</option>
                                <option value="TODOS" <?= $unidadePostada === 'TODOS' ? 'selected' : '' ?>>TODOS</option>
                                <?php foreach ($unidades as $unidade): ?>
                                    <option value="<?= esc($unidade) ?>" <?= $unidadePostada === $unidade ? 'selected' : '' ?>><?= esc($unidade) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-2 filter-col-base">
                            <label class="form-label" for="bases">Selecione a base gestão:</label>
                            <select class="form-select" name="bases" id="bases">
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

                        <div class="mb-2 filter-col-tp">
                            <label class="form-label" for="tp">Propriedade:</label>
                            <select class="form-select" name="tp" id="tp">
                                <option value="">Todos</option>
                                <?php foreach (['AGREGADO', 'LOCADO', 'PROPRIO', 'TERCEIRO'] as $tipoPosse): ?>
                                    <option value="<?= esc($tipoPosse) ?>" <?= $tipoPosseSelecionado === $tipoPosse ? 'selected' : '' ?>><?= esc($tipoPosse === 'PROPRIO' ? 'PRÓPRIO' : $tipoPosse) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-2 filter-col-status">
                            <label class="form-label" for="statusa">Status Operacional:</label>
                            <select class="form-select" name="statusa" id="statusa">
                                <option value="">Todos</option>
                                <option value="1" <?= $statusSelecionado === '1' ? 'selected' : '' ?>>ATIVO</option>
                                <option value="0" <?= $statusSelecionado === '0' ? 'selected' : '' ?>>INATIVO</option>
                            </select>
                        </div>

                        <div class="mb-2 filter-col-button">
                            <button class="btn btn-success" type="submit">Filtrar</button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="d-flex justify-content-start actions flex-wrap align-items-center">
                <form action="excel/exportar-veiculo-completo.php" method="post" target="_blank" class="d-inline">
                    <input type="hidden" name="matr_autor" value="<?= esc($matriculaLogada) ?>">
                    <input type="hidden" name="unidades" value="<?= esc($unidadePostada === '' ? 'TODOS' : $unidadePostada) ?>">
                    <?php foreach ($basesSelecionadas as $baseSelecionada): ?>
                        <input type="hidden" name="bases[]" value="<?= esc($baseSelecionada) ?>">
                    <?php endforeach; ?>
                    <input type="hidden" name="statusa" value="<?= $statusSelecionado === '' || $statusSelecionado === '1' ? '1' : '' ?>">
                    <input type="hidden" name="statusi" value="<?= $statusSelecionado === '' || $statusSelecionado === '0' ? '0' : '' ?>">
                    <input type="hidden" name="tp" value="<?= esc($tipoPosseSelecionado) ?>">
                    <button type="submit" class="btn btn-success"><i class="fas fa-file-excel me-1"></i>Exportar Excel</button>
                </form>
            </section>

            <?php if ($filtrosAplicados !== []): ?>
                <div class="alert alert-info mt-3" role="alert">
                    <i class="fas fa-filter me-2"></i><strong>Filtros Aplicados:</strong><br>
                    <?= implode(' | ', $filtrosAplicados) ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info mt-3" role="alert">
                    <i class="fas fa-info-circle me-2"></i><strong>Exibição completa:</strong> listando todos os veículos visíveis no inventário.
                </div>
            <?php endif; ?>

            <?php if ($erroConsulta !== ''): ?>
                <div class="alert alert-warning" role="alert"><?= esc($erroConsulta) ?></div>
            <?php endif; ?>

            <section style="width: 100%;" class="mt-4">
                <table id="datatablesSimple" class="table table-striped" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Editar</th>
                            <th>Placa</th>
                            <th>Condutor</th>
                            <th>Matrícula</th>
                            <th>CPF</th>
                            <th>Centro de Custo</th>
                            <th>Status Condutor</th>
                            <th>Situação</th>
                            <th>Status Operacional</th>
                            <th>Aplicação</th>
                            <th>Modelo</th>
                            <th>Hodômetro</th>
                            <th>Propriedade</th>
                            <th>Oficina</th>
                            <th>CNH</th>
                            <th>Vencimento CNH</th>
                            <th>Dias p/ Vencimento</th>
                            <th>Cargo</th>
                            <th>Data Entrega</th>
                            <th>Data Devolução</th>
                            <th>Tipo Frota</th>
                            <th>Base Gestão</th>
                            <th>Unidade</th>
                            <th>Ano Fabricação</th>
                            <th>Ano Modelo</th>
                            <th>GPS</th>
                            <th>TAG</th>
                            <th>Locadora</th>
                            <th>Data Disp Locadora</th>
                            <th>Data Dev Locadora</th>
                            <th>Valor Aquisição</th>
                            <th>Data Últ. Vistoria</th>
                            <th>Últ. Movimentação</th>
                            <th>Data Últ. Movimentação</th>
                            <th>Realizada Por</th>
                            <th>Observação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($veiculos as $veiculo): ?>
                            <?php
                            $idVeiculo = (string) ($veiculo['idtbveiculo'] ?? '');
                            $diasVencimento = calcularDiasVencimento($veiculo['validade_cnh'] ?? '', $hoje);
                            $classeDias = $diasVencimento !== '' && (int) $diasVencimento < 0 ? 'dias-vencido' : 'dias-ok';
                            $tagPedagio = (string) ($veiculo['tagpedagio'] ?? '');
                            if ($tagPedagio === '1') {
                                $tagPedagio = 'SIM';
                            } elseif ($tagPedagio === '0') {
                                $tagPedagio = 'NÃO';
                            }
                            $valorAquisicao = (string) ($veiculo['valaquisicao'] ?? '');
                            if ($valorAquisicao !== '') {
                                $valorAquisicao = 'R$ ' . str_replace('.', ',', $valorAquisicao);
                            }
                            ?>
                            <tr>
                                <td><?= $perfilLogado === '4' && !$bloquearEdicao ? montarBotaoEditarInventario($idVeiculo, $matriculaLogada, $perfilLogado) : '' ?></td>
                                <td><?= esc($veiculo['placa'] ?? '') ?></td>
                                <td><?= esc($veiculo['condutor_nome'] ?? $veiculo['nomecol'] ?? '') ?></td>
                                <td><?= esc($veiculo['matcond'] ?? '') ?></td>
                                <td><?= esc($veiculo['condutor_cpf'] ?? '') ?></td>
                                <td><?= esc($veiculo['ccusto'] ?? '') ?></td>
                                <td><?= esc(statusFuncionarioTexto($veiculo['condutor_status'] ?? '')) ?></td>
                                <td><?= esc($veiculo['status_veiculo'] ?? '') ?></td>
                                <td><?= esc(statusVeiculoTexto($veiculo['status'] ?? '')) ?></td>
                                <td><?= esc($veiculo['aplicacao_nome'] ?? '') ?></td>
                                <td><?= esc($veiculo['modelo_nome'] ?? '') ?></td>
                                <td><?= esc($veiculo['hodometro'] ?? '') ?></td>
                                <td><?= esc($veiculo['tipoposse'] ?? '') ?></td>
                                <td><?= esc($veiculo['oficina_manutencao'] ?? $veiculo['oficina'] ?? '') ?></td>
                                <td><?= esc($veiculo['numcnh'] ?? '') ?></td>
                                <td><?= esc(formatarDataInventario($veiculo['validade_cnh'] ?? '')) ?></td>
                                <td class="<?= esc($classeDias) ?>"><?= esc($diasVencimento) ?></td>
                                <td><?= esc($veiculo['condutor_cargo'] ?? $veiculo['cargo'] ?? '') ?></td>
                                <td><?= esc(formatarDataInventario($veiculo['dtentrega'] ?? '')) ?></td>
                                <td><?= esc(formatarDataInventario($veiculo['dtdevolucao'] ?? '')) ?></td>
                                <td><?= esc($veiculo['tipovel'] ?? '') ?></td>
                                <td><?= esc($veiculo['basegestao'] ?? '') ?></td>
                                <td><?= esc($veiculo['unidade'] ?? '') ?></td>
                                <td><?= esc($veiculo['anofabr'] ?? '') ?></td>
                                <td><?= esc($veiculo['anomodelo'] ?? '') ?></td>
                                <td><?= esc($veiculo['gpsemp'] ?? '') ?></td>
                                <td><?= esc($tagPedagio) ?></td>
                                <td><?= esc($veiculo['idlocador'] ?? '') ?></td>
                                <td><?= esc(formatarDataInventario($veiculo['dtdisponivelloc'] ?? '')) ?></td>
                                <td><?= esc(formatarDataInventario($veiculo['dtdevolucaoloc'] ?? '')) ?></td>
                                <td><?= esc($valorAquisicao) ?></td>
                                <td><?= esc(formatarDataInventario($veiculo['datavistoria'] ?? '', 'd/m/Y H:i:s')) ?></td>
                                <td><?= esc(str_replace(['-1', '-2', '-3'], '', (string) ($veiculo['ultima_movimentacao'] ?? ''))) ?></td>
                                <td><?= esc(formatarDataInventario($veiculo['data_ultima_movimentacao'] ?? '', 'd/m/Y H:i:s')) ?></td>
                                <td><?= esc($veiculo['realizada_por'] ?? '') ?></td>
                                <td><?= esc($veiculo['obsveiculo'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </main>

        <footer class="py-4 bg-light mt-auto" style="bottom: 0; start: 0; width: 100%;">
            <div class="container-fluid px-4">
                <div class="d-flex align-items-center justify-content-between small">
                    <div class="text-muted">Copyright &copy; FFA Infraestrutura</div>
                </div>
            </div>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/plug-ins/1.13.7/sorting/date-eu.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarToggle = document.getElementById('sidebarToggle');

            if (!sidebarToggle) {
                return;
            }

            sidebarToggle.addEventListener('click', function (event) {
                event.preventDefault();
                document.body.classList.toggle('sb-sidenav-toggled');
            });
        });
    </script>
    <script>
        $(document).ready(function () {
            $('#datatablesSimple').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
                },
                columnDefs: [{
                    type: 'date-eu',
                    targets: [15, 18, 19, 28, 29, 31, 33]
                }]
            });
        });
    </script>
</body>
</html>
<!--  Baseado em https://ffasip.ddns.net:4545/frotas/gestao/relinventario.php -->
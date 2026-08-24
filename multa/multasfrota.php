<?php
require_once __DIR__ . '/../includes/autofrota_common.php';
session_start();

$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');

$etapaf = trim((string) ($_POST['etapaf'] ?? 'TODAS')); // Padrão TODAS
$dataInicial = trim((string) ($_POST['data_inicial'] ?? ''));
$dataFinal = trim((string) ($_POST['data_final'] ?? ''));
$hoje = new DateTimeImmutable('now');

// Se não houver datas selecionadas, define a semana atual
if ($dataInicial === '' || $dataFinal === '') {
    $dataInicial = $hoje->modify('monday this week')->format('Y-m-d');
    $dataFinal = $hoje->modify('sunday this week')->format('Y-m-d');
}

$opcoesEtapa = [];
$linhasMultas = [];

// CORRIGIDO: Array de tramites corretamente definido
$tramiteEtapa = [
    'Inserir infrator' => 'editar_infrator.php',
    'Finalizado Frota' => 'finalizado_frota.php',
    'Imprimir Recibo DP' => 'imprimir_recibo.php',
    'Finalizado DP' => 'finalizado_frota.php',
];

// CORRIGIDO: $multaEtapa é o mesmo que $tramiteEtapa
$multaEtapa = $tramiteEtapa;

function esc(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function formatarData(?string $valor): string
{
    if (empty($valor) || $valor === '0000-00-00' || $valor === '0000-00-00 00:00:00') {
        return '';
    }

    $data = date_create($valor);
    return $data ? date_format($data, 'd/m/Y') : (string) $valor;
}

function buscarNomeFuncionario(?mysqli $conn, string $matricula): string
{
    if (!$conn || $matricula === '') {
        return '';
    }

    $sql = 'SELECT nome FROM bdautofrotas.tbfuncionario WHERE matricula = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return '';
    }

    mysqli_stmt_bind_param($stmt, 's', $matricula);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return (string) ($row['nome'] ?? '');
}

function buscarStatusFuncionario(?mysqli $conn, string $matricula): string
{
    if (!$conn || $matricula === '') {
        return '';
    }

    $sql = 'SELECT status FROM bdautofrotas.tbfuncionario WHERE matricula = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return '';
    }

    mysqli_stmt_bind_param($stmt, 's', $matricula);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return (string) ($row['status'] ?? '');
}

function buscarCentroCustoFuncionario(?mysqli $conn, string $matricula): string
{
    if (!$conn || $matricula === '') {
        return '';
    }

    $sql = 'SELECT ccusto FROM bdautofrotas.tbfuncionario WHERE matricula = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return '';
    }

    mysqli_stmt_bind_param($stmt, 's', $matricula);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return (string) ($row['ccusto'] ?? '');
}

function buscarDadosVeiculo(?mysqli $conn, string $databaseName, string $placa): array
{
    if (!$conn || $placa === '') {
        return ['unidade' => '', 'aplicacao' => ''];
    }

    $sql = "
        SELECT 
            unidade, ap.aplicacao
        FROM
            tbveiculo v
        LEFT JOIN
            tbveiculoaplicacao ap ON ap.idtbaplicacaoveic = v.aplicacao
        WHERE
            placa = ?
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return ['unidade' => '', 'aplicacao' => ''];
    }

    mysqli_stmt_bind_param($stmt, 's', $placa);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return [
        'unidade' => (string) ($row['unidade'] ?? ''),
        'aplicacao' => (string) ($row['aplicacao'] ?? ''),
    ];
}

function buscarLocadora(?mysqli $conn, string $databaseName, string $locadoraId): string
{
    if ($locadoraId === '') {
        return 'PRÓPRIO';
    }

    if (!$conn) {
        return '';
    }

    $sql = "SELECT fantasia FROM `{$databaseName}`.tbfornecedor WHERE idtbfornecedor = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return '';
    }

    mysqli_stmt_bind_param($stmt, 's', $locadoraId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return (string) ($row['fantasia'] ?? '');
}

function calcularSituacaoCondutor(string $tramite, string $dataLimiteCondutor, DateTimeImmutable $hoje): string
{
    if ($tramite === 'Finalizado Frota') {
        return 'CONDUTOR INDICADO';
    }

    if ($dataLimiteCondutor === '' || $dataLimiteCondutor === '0000-00-00' || $dataLimiteCondutor === '0000-00-00 00:00:00') {
        return 'Prazo indeterminado.';
    }

    $dataLimite = date_create($dataLimiteCondutor);
    if (!$dataLimite) {
        return 'Prazo indeterminado.';
    }

    if ($hoje > DateTimeImmutable::createFromMutable($dataLimite)) {
        return 'Prazo vencido.';
    }

    $intervalo = $hoje->diff(DateTimeImmutable::createFromMutable($dataLimite));
    return 'Prazo em aberto: ' . $intervalo->format('%a dia(s).');
}

if ($conn instanceof mysqli && $databaseName !== '') {
    mysqli_set_charset($conn, 'utf8mb4');

    $sqlEtapas = "SELECT DISTINCT etapa FROM `{$databaseName}`.tbamultaetapa WHERE etapa <> '' ORDER BY etapa";
    $resEtapas = mysqli_query($conn, $sqlEtapas);
    if ($resEtapas) {
        while ($rowEtapa = mysqli_fetch_assoc($resEtapas)) {
            $opcoesEtapa[] = (string) ($rowEtapa['etapa'] ?? '');
        }
        mysqli_free_result($resEtapas);
    }

    if ($dataInicial !== '' && $dataFinal !== '') {
        $sql = "
            SELECT
                m.placa,
                m.autoinfracao,
                m.codigom,
                m.descricaoinfra,
                m.etapa,
                m.orgao,
                m.datalimitecond,
                m.valtotal,
                t.nome,
                t.matricula,
                t.gravidade,
                t.tramite,
                t.locadora,
                t.dtcons,
                t.dtinfra,
                t.parecer,
                t.parecerpor,
                t.parecerdp,
                t.parecerpordp,
                t.dtdesconto,
                t.idtbmovidatramite,
                f.status,
                f.ccusto
            FROM tbmulta m
            LEFT JOIN tbmovidatramite t
                ON t.placa = m.placa
               AND t.autoinfra = m.autoinfracao
            LEFT JOIN bdcorp.tbfuncionario f ON f.matricula = t.matricula
            WHERE m.datahoracadastro BETWEEN ? AND ?
        ";

        if ($etapaf !== 'TODAS') {
            $sql .= ' AND m.etapa = ?';
        }

        $sql .= ' ORDER BY t.dtcons DESC, m.idtbmulta DESC';

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            $dataInicioDt = $dataInicial . ' 00:00:00';
            $dataFinalDt = $dataFinal . ' 23:59:59';

            if ($etapaf != 'TODAS') {
                mysqli_stmt_bind_param($stmt, 'sss', $dataInicioDt, $dataFinalDt, $etapaf);
            } else {
                mysqli_stmt_bind_param($stmt, 'ss', $dataInicioDt, $dataFinalDt);
            }

            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $placa = (string) ($row['placa'] ?? '');
                    $dadosVeiculo = buscarDadosVeiculo($conn, $databaseName, $placa);
                    $tramite = (string) ($row['tramite'] ?? '');
                    $datalimiteCondutor = (string) ($row['datalimitecond'] ?? '');

                    $matriculaCondutor = (string) ($row['matricula'] ?? '');
                    $statusCondutor = buscarStatusFuncionario($conn, $matriculaCondutor);
                    $centroCustoCondutor = buscarCentroCustoFuncionario($conn, $matriculaCondutor);
                    $situacaoCondutor = calcularSituacaoCondutor($tramite, $datalimiteCondutor, $hoje);

                    $matriculaParecer = (string) ($row['parecerpor'] ?? '');
                    $nomeParecer = buscarNomeFuncionario($conn, $matriculaParecer);

                    $idMov = (string) ($row['idtbmovidatramite'] ?? '');
                    $autoInfracao = (string) ($row['autoinfracao'] ?? '');
                    $nomeCondutor = trim((string) ($row['nome'] ?? ''));
                    $arquivoPdf = $autoInfracao . '-' . $nomeCondutor . '-' . $matriculaCondutor . '.pdf';
                    $caminhoArquivo = __DIR__ . '/docs/' . $arquivoPdf;

                    $linkVisualizar = '';
                    $linkEditar = '';
                    if ($idMov !== '' && is_file($caminhoArquivo)) {
                        $linkVisualizar = '<a href="./docs/' . esc($arquivoPdf) . '" class="btn btn-primary btn-sm d-block mb-1" target="_blank"><i class="fa fa-eye"></i> Visualizar</a>';
                        $linkEditar = '<a href="./editarMulta.php?id=' . $idMov . '" class="btn btn-warning btn-sm d-block mb-1"><i class="fa fa-pen"></i> Editar</a>';
                    }

                    // CORRIGIDO: Acesso correto ao array $tramiteEtapa
                    // CORRIGIDO: Acesso correto ao array $tramiteEtapa
                    $tramiteLink = $tramiteEtapa[$tramite] ?? '';
                    $botaoTramite = '';

                    if ($idMov !== '') {
                        // Se for "Finalizado DP" ou "Finalizado Frota", mostra como texto
                        if ($tramite === 'Finalizado DP' || $tramite === 'Finalizado Frota') {
                            $botaoTramite = '<span class="badge bg-secondary w-100 p-2" style="font-size: 12px; font-weight: normal; opacity: 0.8;">' . $tramite . '</span>';
                        } elseif ($tramiteLink !== '') {
                            // Para os demais trâmites que têm link
                            $classeBotaoTramite = 'btn-success';
                            $botaoTramite = '<a class="btn ' . $classeBotaoTramite . ' btn-sm w-100" href="' . $tramiteLink . '?id=' . $idMov . '">' . $tramite . '</a>';
                        }
                    }

                    $botaoEditarInfrator = '';
                    if ($idMov !== '' && $tramite === 'Imprimir Recibo DP') {
                        $botaoEditarInfrator = '<a href="./editar_infrator.php?id=' . $idMov . '" class="btn btn-info btn-sm w-100 d-block mb-1"><i class="fa fa-pen"></i> Editar Infrator</a>';
                    }

                    $htmlTramite = '<div class="d-flex flex-column gap-1" style="min-width:120px;">' . $linkVisualizar . $linkEditar . $botaoTramite . $botaoEditarInfrator . '</div>';

                    $situacaoClasse = '';
                    if ($situacaoCondutor === 'Prazo vencido.') {
                        $situacaoClasse = 'situacao-vencido';
                    } elseif (str_starts_with($situacaoCondutor, 'Prazo em aberto:')) {
                        $situacaoClasse = 'situacao-aberto';
                    }
                    $htmlSituacaoCondutor = '<span class="situacao-condutor ' . $situacaoClasse . '">' . esc($situacaoCondutor) . '</span>';

                    $parecerDp = (string) ($row['parecerdp'] ?? '');
                    $matriculaParecerDp = (string) ($row['parecerpordp'] ?? '');
                    $nomeParecerDp = buscarNomeFuncionario($conn, $matriculaParecerDp);
                    $dataDesconto = formatarData((string) ($row['dtdesconto'] ?? ''));

                    $htmlUltimoParecer = '<div style="display: flex; justify-content: center; align-items: center;">
                        <a href="detalhes_multa.php?id=' . $idMov . '" class="fs-4">
                            <i class="fa-regular fa-eye"></i>
                        </a>
                    </div>';

                    $htmlAdicionarParecer = $idMov !== ''
                        ? '<form method="post" action="./parecermulta.php" target="_blank"><input type="hidden" name="idtbmovidatramite" value="' . esc($idMov) . '"><button title="Adicionar Parecer" style="border:none;background:transparent;" type="submit"><span class="material-symbols-outlined">edit_note</span></button></form>'
                        : '';

                    $htmlDataEnvio = '<button title="Ver Data Envio" class="btn-icon js-ver-envio" type="button" data-auto="' . esc($autoInfracao) . '" data-placa="' . esc($placa) . '" data-data-envio="' . esc($dataDesconto) . '"><span class="material-symbols-outlined">visibility</span></button>';

                    $linhasMultas[] = [
                        'placa' => $placa,
                        'aplicacao' => $dadosVeiculo['aplicacao'],
                        'condutor' => (string) ($row['nome'] ?? ''),
                        'status_condutor' => $row['status'],
                        'centro_custo' => $row['ccusto'],
                        'unidade' => $dadosVeiculo['unidade'],
                        'locadora' => buscarLocadora($conn, $databaseName, (string) ($row['locadora'] ?? '')),
                        'gravidade' => (string) ($row['gravidade'] ?? ''),
                        'auto' => $autoInfracao,
                        'cod_multa' => (string) ($row['codigom'] ?? ''),
                        'desc_multa' => (string) ($row['descricaoinfra'] ?? ''),
                        'data_infracao' => formatarData((string) ($row['dtinfra'] ?? '')),
                        'orgao' => (string) ($row['orgao'] ?? ''),
                        'apresentar_condutor' => formatarData((string) ($row['datalimitecond'] ?? '')),
                        'situacao_condutor' => $htmlSituacaoCondutor,
                        'etapa' => (string) ($row['etapa'] ?? ''),
                        'valor' => (string) ($row['valtotal'] ?? ''),
                        'tramite' => $htmlTramite,
                        'parecer_inicial' => (string) ($row['parecer'] ?? ''),
                        'parecer_inicial_por' => $nomeParecer,
                        'ult_parecer' => $htmlUltimoParecer,
                        'adicionar_parecer' => $htmlAdicionarParecer,
                        'data_envio' => $htmlDataEnvio,
                    ];
                }
                mysqli_free_result($res);
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="AutoFrota" />
    <meta name="author" content="FFA" />
    <title>Listar Multas AUTOFROTA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../src/autofrota-botoes.css">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body {
            background: #ffffff;
            color: #000000;
            font-size: 12px;
            overflow-x: auto;
        }

        .page-wrapper {
            max-width: 100%;
            overflow-x: auto;
            padding: 20px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 10px;
        }

        .notice {
            font-style: italic;
            font-size: 12px;
            margin-bottom: 14px;
        }

        .filter-area {
            border-bottom: 1px solid #212529;
            padding-bottom: 18px;
            margin-bottom: 16px;
        }

        .filter-controls {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 12px 18px;
        }

        .filter-item {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            margin-bottom: 6px;
        }

        .form-select {
            min-width: 92px;
            font-size: 12px;
            border-radius: 2px;
        }

        .btn {
            font-size: 12px;
            border-radius: 3px;
            padding: 6px 10px;
        }

        .actions {
            gap: 10px;
            margin-bottom: 16px;
        }

        .btn-icon {
            border: none;
            background-color: transparent;
            padding: 0;
            color: #0d6efd;
            line-height: 1;
        }

        .btn-icon:hover {
            color: #0a58ca;
        }

        .table-responsive-wrapper {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 20px;
        }

        #tabelaMultas {
            width: 100%;
            min-width: 1600px;
        }

        #tabelaMultas thead th {
            color: #000000;
            font-size: 12px;
            vertical-align: bottom;
            white-space: nowrap;
            padding: 8px 6px;
        }

        #tabelaMultas tbody td {
            font-size: 11px;
            padding: 8px 6px;
            vertical-align: middle;
            white-space: nowrap;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            font-size: 12px;
        }

        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            font-size: 12px;
            border: 1px solid #aaa;
            border-radius: 0;
            padding: 4px;
        }

        .table-placeholder {
            min-height: 24px;
        }

        .situacao-condutor {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 3px;
        }

        .situacao-vencido {
            color: #fff;
            background-color: #733030;
        }

        .situacao-aberto {
            color: #fff;
            background-color: #255573;
        }
    </style>
</head>

<body class="sb-nav-fixed">
    <?php autofrotaMenu(); ?>

    <div id="layoutSidenav_content">
        <main class="page-wrapper py-3">
            <h1 class="page-title">Gerenciar Multas</h1>

            <section class="filter-area">
                <form method="post" action="#" class="col-12">
                    <div class="row align-items-end">
                        <div class="col-md-3">
                            <label class="form-label" for="etapaf">Etapa:<span style="color: red;">*</span></label>
                            <select class="form-select" name="etapaf" id="etapaf" required>
                                <option value="TODAS" <?= $etapaf === 'TODAS' ? 'selected' : '' ?>>TODAS</option>
                                <?php foreach ($opcoesEtapa as $opcaoEtapa): ?>
                                    <option value="<?= esc($opcaoEtapa) ?>" <?= $etapaf === $opcaoEtapa ? 'selected' : '' ?>>
                                        <?= esc($opcaoEtapa) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="data_inicial">Data inicial:<span
                                    style="color: red;">*</span></label>
                            <input class="form-control" type="date" name="data_inicial" id="data_inicial"
                                value="<?= esc($dataInicial) ?>" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="data_final">Data final:<span
                                    style="color: red;">*</span></label>
                            <input class="form-control" type="date" name="data_final" id="data_final"
                                value="<?= esc($dataFinal) ?>" required>
                        </div>

                        <div class="col-md-3">
                            <button class="btn btn-success w-100" type="submit">Filtrar</button>
                        </div>
                    </div>

                    <div class="mt-2">
                        <p style="color: red; font-size: 11px;">* Campos obrigatórios.</p>
                    </div>
                </form>
            </section>

            <?php if ($etapaf !== '' && $dataInicial !== '' && $dataFinal !== ''): ?>
                <?php
                $dataInicialFormatada = DateTime::createFromFormat('Y-m-d', $dataInicial);
                $dataFinalFormatada = DateTime::createFromFormat('Y-m-d', $dataFinal);
                ?>
                <div class="alert alert-info mt-3" role="alert">
                    <strong>Filtros aplicados:</strong><br>
                    <span class="d-block mb-1"><strong>Etapa:</strong> <?= esc($etapaf) ?></span>
                    <span class="d-block">
                        <strong>Data de cadastro da multa no sistema:</strong>
                        <?= $dataInicialFormatada ? $dataInicialFormatada->format('d/m/Y') : esc($dataInicial) ?>
                        até
                        <?= $dataFinalFormatada ? $dataFinalFormatada->format('d/m/Y') : esc($dataFinal) ?>
                    </span>
                </div>
            <?php endif; ?>

            <section class="d-flex justify-content-start actions flex-wrap">
                <a class="btn btn-success" href="cadastromulta.php">Cadastrar Multa</a>
                <a class="btn btn-primary" href="importarmultasnovas.php" target="_blank"
                    rel="noopener noreferrer">Importar Multas Novas</a>
                <a class="btn btn-secondary" href="control/docs/modelos/ImportModeloMultas.xlsx">Modelo
                    Excel</a>
            </section>

            <div class="table-responsive-wrapper">
                <table id="tabelaMultas" class="table table-striped table-placeholder">
                    <thead>
                        <tr>
                            <th>Placa</th>
                            <th>Aplicacao</th>
                            <th>Condutor</th>
                            <th>Status do Condutor</th>
                            <th>Centro de Custo</th>
                            <th>Unidade</th>
                            <th>Locadora</th>
                            <th>Gravidade</th>
                            <th>Nº do Auto</th>
                            <th>Cod. Multa</th>
                            <th>Desc. Multa</th>
                            <th>Data Infração</th>
                            <th>Órgão Autuador</th>
                            <th>Apresentar condutor em</th>
                            <th>Situação (apresentar condutor)</th>
                            <th>Etapa</th>
                            <th>Valor</th>
                            <th>Trâmite</th>
                            <th>Histórico Parecer</th>
                            <th>Adicionar Parecer</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </main>
    </div>

    <div class="modal fade" id="modalDetalhesCondutor" tabindex="-1" aria-labelledby="modalDetalhesCondutorLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetalhesCondutorLabel">Detalhes da atualização</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <dl>
                        <dt>Atualizado em</dt>
                        <dd id="modalAtualizadoEm">-</dd>

                        <dt>Última atualização</dt>
                        <dd id="modalUltimaAtualizacao">-</dd>

                        <dt>Realizado por</dt>
                        <dd id="modalRealizadaPor">-</dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalParecer" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Último Parecer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Parecer</label>
                    <textarea id="modalParecerTexto" class="form-control" rows="5" disabled></textarea>
                    <label class="form-label mt-2">Autor</label>
                    <input id="modalParecerAutor" type="text" class="form-control" disabled>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDataEnvio" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Data Envio para Desconto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Auto de Infração</label>
                    <input id="modalEnvioAuto" type="text" class="form-control" disabled>
                    <label class="form-label mt-2">Data de Envio</label>
                    <input id="modalEnvioData" type="text" class="form-control" disabled>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function () {
            $('#tabelaMultas').DataTable({
                data: <?= json_encode($linhasMultas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                autoWidth: false,
                responsive: true,
                columns: [
                    { data: 'placa', defaultContent: '' },
                    { data: 'aplicacao', defaultContent: '' },
                    { data: 'condutor', defaultContent: '' },
                    { data: 'status_condutor', defaultContent: '' },
                    { data: 'centro_custo', defaultContent: '' },
                    { data: 'unidade', defaultContent: '' },
                    { data: 'locadora', defaultContent: '' },
                    { data: 'gravidade', defaultContent: '' },
                    { data: 'auto', defaultContent: '' },
                    { data: 'cod_multa', defaultContent: '' },
                    { data: 'desc_multa', defaultContent: '' },
                    { data: 'data_infracao', defaultContent: '' },
                    { data: 'orgao', defaultContent: '' },
                    { data: 'apresentar_condutor', defaultContent: '' },
                    { data: 'situacao_condutor', defaultContent: '' },
                    { data: 'etapa', defaultContent: '' },
                    {
                        data: 'valor',
                        defaultContent: '',
                        render: function (data, type) {
                            if (type !== 'display' || data === null || data === undefined || data === '') {
                                return data || '';
                            }
                            return 'R$ ' + parseFloat(data).toFixed(2).replace('.', ',');
                        }
                    },
                    { data: 'tramite', defaultContent: '', orderable: false },
                    { data: 'ult_parecer', defaultContent: '', orderable: false },
                    { data: 'adicionar_parecer', defaultContent: '', orderable: false }
                ],
                order: [],
                language: {
                    emptyTable: 'Nenhum registro encontrado',
                    zeroRecords: 'Nenhum registro encontrado',
                    lengthMenu: 'Exibir _MENU_ resultados por página',
                    search: 'Pesquisar',
                    searchPlaceholder: 'Buscar registros',
                    info: '',
                    infoEmpty: '',
                    infoFiltered: '',
                    paginate: {
                        first: 'Primeiro',
                        last: 'Último',
                        next: 'Próximo',
                        previous: 'Anterior'
                    }
                }
            });

            $(document).on('click', '.js-ver-parecer', function () {
                $('#modalParecerTexto').val($(this).data('parecer') || '');
                $('#modalParecerAutor').val($(this).data('autor') || '');
                new bootstrap.Modal(document.getElementById('modalParecer')).show();
            });

            $(document).on('click', '.js-ver-envio', function () {
                const auto = $(this).data('auto') || '';
                const placa = $(this).data('placa') || '';
                $('#modalEnvioAuto').val(auto);
                $('#modalEnvioData').val($(this).data('data-envio') || '');

                $.ajax({
                    method: 'POST',
                    url: './func/consultarenvio.php',
                    contentType: 'application/json; charset=utf-8',
                    dataType: 'json',
                    data: JSON.stringify({ autoinfracao: auto, placa: placa }),
                    success: function (resposta) {
                        if (resposta && resposta.data_log) {
                            $('#modalEnvioData').val(resposta.data_log);
                        }
                    }
                });

                new bootstrap.Modal(document.getElementById('modalDataEnvio')).show();
            });
        });
    </script>
</body>

</html>
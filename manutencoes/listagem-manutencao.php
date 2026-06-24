<?php
// Referências comuns do módulo (auth, conexão, menu lateral e utilitários).
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

date_default_timezone_set('America/Sao_Paulo');

$usuarioLogado = $autofrotaSessao['usuario'] ?? 'Usuário';
$matriculaLogada = (string) (($autofrotaSessao['matricula'] ?? '') !== '' ? $autofrotaSessao['matricula'] : ($_SESSION['usuario'] ?? ''));
$perfilLogado = (string) ($autofrotaSessao['perfil'] ?? '');
$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');
$hoje = date('Y-m-d');
$dataFinalPadrao = $hoje;
$dataInicialPadrao = date('Y-m-d', strtotime('-6 days'));
$matriculasSemPermissaoEdicao = 
['160030', '410109', '501285', '410039', '411425', '003931'];
$tipoSelecionado = $_GET['tipo'] ?? $_GET['tipo_manutencao'] ?? $_POST['tipo'] ?? $_POST['tipo_manutencao'] ?? '';
$tiposManutencao = [
    '' => 'Todas',
    'MP' => 'Preventiva',
    'MC' => 'Corretiva',
    'OS' => 'Ordem de Serviço',
    'SS' => 'Sinistro',
];
$manutencoes = [];
$erroConsulta = '';

$dataInicial = normalizarDataFiltro(
    $_GET['datai'] ?? $_GET['data_inicial'] ?? $_POST['datai'] ?? $_POST['data_inicial'] ?? null,
    $dataInicialPadrao
);
$dataFinal = normalizarDataFiltro(
    $_GET['dataf'] ?? $_GET['data_final'] ?? $_POST['dataf'] ?? $_POST['data_final'] ?? null,
    $dataFinalPadrao
);

if (!isset($tiposManutencao[$tipoSelecionado])) {
    $tipoSelecionado = '';
}

$filtrosUrl = [
    'datai' => $dataInicial,
    'dataf' => $dataFinal,
    'tipo' => $tipoSelecionado,
];

if (
    !headers_sent()
    && (
        ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        || !array_key_exists('datai', $_GET)
        || !array_key_exists('dataf', $_GET)
        || !array_key_exists('tipo', $_GET)
    )
) {
    header('Location: listagem-manutencao.php?' . http_build_query($filtrosUrl));
    exit;
}

function vincularParametros(mysqli_stmt $stmt, string $tipos, array $parametros): bool
{
    $referencias = [];
    foreach ($parametros as $indice => $valor) {
        $referencias[$indice] = &$parametros[$indice];
    }

    return mysqli_stmt_bind_param($stmt, $tipos, ...$referencias);
}

function formatarDataManutencao(?string $data): string
{
    if (empty($data) || $data === '0000-00-00' || $data === '0000-00-00 00:00:00') {
        return '';
    }

    $dataFormatada = date_create($data);

    return $dataFormatada ? date_format($dataFormatada, 'd/m/Y') : $data;
}

function normalizarDataFiltro(?string $valor, string $padrao): string
{
    $valor = trim((string) ($valor ?? ''));
    if ($valor === '') {
        return $padrao;
    }

    $data = date_create_from_format('Y-m-d', $valor);
    if ($data instanceof DateTimeInterface) {
        return $data->format('Y-m-d');
    }

    $dataAlternativa = date_create($valor);
    return $dataAlternativa ? $dataAlternativa->format('Y-m-d') : $padrao;
}

function descreverTipoManutencao(?string $tipo): string
{
    $tipos = [
        'MP' => 'Manutenção Preventiva',
        'MC' => 'Manutenção Corretiva',
        'OS' => 'Ordem de Serviço',
        'SS' => 'Sinistro',
    ];

    return $tipos[$tipo] ?? (string) $tipo;
}

function descreverStatusManutencao($status): string
{
    $statusTexto = (string) $status;
    if ($statusTexto === '1') {
        return 'ABERTO';
    }
    if ($statusTexto === '2') {
        return 'CONCLUIDO';
    }

    return $statusTexto;
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

function montarLinhaManutencao(array $manutencao, bool $podeEditar): array
{
    $idManutencao = (string) ($manutencao['idtbmanprev'] ?? '');
    $placa = (string) ($manutencao['placa'] ?? '');
    $info = '';
    $editar = '';

    if ($podeEditar) {
        $info = montarBotaoPost('historicoplaca-manutencao.php', ['placa' => $placa], 'info', 'Histórico Manutenções');
        $editar = montarBotaoPost('editar-manutencao.php', ['num' => $idManutencao], 'edit_note', 'Editar Manutenção');
    }

    $ordemServico = montarBotaoPost('../pdf/fpdf/ordemdeservico.php', ['num' => $idManutencao], 'visibility', 'Ordem de Serviço');

    return [
        'placa' => esc($placa) . $info,
        'unidade' => esc($manutencao['unidade'] ?? ''),
        'data_agendamento' => esc(formatarDataManutencao($manutencao['dataagendamento'] ?? '')),
        'data_cadastro' => esc(formatarDataManutencao($manutencao['data'] ?? '')),
        'tipo' => esc(descreverTipoManutencao($manutencao['tipo'] ?? '')),
        'status' => esc(descreverStatusManutencao($manutencao['status'] ?? '')),
        'oficina' => esc($manutencao['oficina'] ?? ''),
        'observacao' => esc($manutencao['observacao'] ?? ''),
        'editar' => $editar,
        'ordem_servico' => $ordemServico,
    ];
}

$podeEditar = $perfilLogado === '4' && !in_array($matriculaLogada, $matriculasSemPermissaoEdicao, true);

if (isset($conn) && $conn instanceof mysqli && $databaseName !== '') {
    $where = [
        "man.tipo <> 'Desativada'",
        "man.placa <> 'ABC1234'",
        "man.placa <> ''",
    ];
    $tiposBind = '';
    $parametros = [];

    $where[] = '(man.dataagendamento >= ? OR man.dataagendamento IS NULL OR TRIM(COALESCE(man.dataagendamento, "")) = "")';
    $where[] = '(man.dataagendamento <= ? OR man.dataagendamento IS NULL OR TRIM(COALESCE(man.dataagendamento, "")) = "")';
    $tiposBind .= 'ss';
    $parametros[] = $dataInicial . ' 00:00:00';
    $parametros[] = $dataFinal . ' 23:59:59';

    if ($tipoSelecionado !== '') {
        $where[] = 'man.tipo = ?';
        $tiposBind .= 's';
        $parametros[] = $tipoSelecionado;
    }

    $sqlManutencoes = "
        SELECT
            man.idtbmanprev,
            man.placa,
            man.data,
            man.tipo,
            man.dataentrada,
            man.dataagendamento,
            man.status,
            man.oficina,
            man.observacao,
            vei.unidade
        FROM `{$databaseName}`.`tbmanprev` man
        LEFT JOIN `{$databaseName}`.`tbveiculo` vei
            ON vei.placa = man.placa
        WHERE " . implode(' AND ', $where) . "
        ORDER BY man.dataagendamento DESC, man.idtbmanprev DESC
    ";

    $stmtManutencoes = mysqli_prepare($conn, $sqlManutencoes);
    if ($stmtManutencoes) {
        vincularParametros($stmtManutencoes, $tiposBind, $parametros);
        mysqli_stmt_execute($stmtManutencoes);
        $resultadoManutencoes = mysqli_stmt_get_result($stmtManutencoes);

        if ($resultadoManutencoes) {
            while ($manutencao = mysqli_fetch_assoc($resultadoManutencoes)) {
                $manutencoes[] = montarLinhaManutencao($manutencao, $podeEditar);
            }
            mysqli_free_result($resultadoManutencoes);
        } else {
            $erroConsulta = mysqli_error($conn);
        }

        mysqli_stmt_close($stmtManutencoes);
    } else {
        $erroConsulta = mysqli_error($conn);
    }
} else {
    $erroConsulta = 'Não foi possível conectar ao banco configurado.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="AutoFrota" />
    <meta name="author" content="FFA" />
    <title>AutoFrota - Registros de Manutenção</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../src/autofrota-botoes.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body { background: #ffffff; color: #000000; font-size: 12px; }
        .page-title { font-size: 24px; font-weight: 600; margin: 0 0 10px; }
        .notice { font-size: 12px; margin-bottom: 14px; }
        .filter-area { border-bottom: 1px solid #212529; padding-bottom: 14px; margin-bottom: 4px; }
        .form-label { margin-bottom: 6px; }
        .form-select, .form-control { font-size: 12px; border-radius: 2px; }
        .btn { font-size: 12px; border-radius: 3px; padding: 6px 10px; }
        .actions { gap: 6px; margin: 4px 0 16px; }
        table.dataTable thead th { color: #000000; font-size: 12px; vertical-align: bottom; }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate { font-size: 12px; }
        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select { font-size: 12px; border: 1px solid #aaa; border-radius: 0; padding: 4px; }
        .filter-action-buttons { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
    </style>
</head>
<body class="sb-nav-fixed">
    <?php autofrotaMenu(); ?>

        <div id="layoutSidenav_content">
        <main class="page-wrapper py-3">
            <h1 class="page-title">Registros de Manutenção</h1>

            <?php if ($erroConsulta !== ''): ?>
                <div class="alert alert-warning" role="alert"><?= esc($erroConsulta) ?></div>
            <?php endif; ?>

            <p class="notice">A tela pré carrega com as manutenções cadastradas nos últimos sete dias. Para carregar mais registros, utilize os filtros de período de data de agendamento.</p>

            <section class="filter-area">
                <form action="listagem-manutencao.php" method="get">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label" for="datai">Data inicial:<span style="color: red;">*</span></label>
                            <input class="form-control" type="date" id="datai" name="datai" value="<?= esc($dataInicial) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="dataf">Data final:<span style="color: red;">*</span></label>
                            <input class="form-control" type="date" id="dataf" name="dataf" value="<?= esc($dataFinal) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="tipo">Tipo de Manutenção:</label>
                            <select class="form-select" name="tipo" id="tipo">
                                <?php foreach ($tiposManutencao as $valor => $rotulo): ?>
                                    <option value="<?= esc($valor) ?>" <?= $tipoSelecionado === $valor ? 'selected' : '' ?>><?= esc($rotulo) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-filter-blue mt-2">Filtrar</button>
                    <p class="mt-2 mb-0"><span style="color: red;">*</span> Campos obrigatórios</p>
                </form>
                <div class="filter-action-buttons">
                    <a class="btn btn-secondary" href="../veiculos/listagem-veiculo.php">Veículos Cadastrados</a>
                    <form method="post" action="importar-manutencao.php" target="_blank">
                        <input type="hidden" name="perfil" value="<?= esc($perfilLogado) ?>">
                        <input type="hidden" name="mat_autor" value="<?= esc($matriculaLogada) ?>">
                        <button class="btn btn-primary" type="submit">Importar Manutenções em Lote</button>
                    </form>
                    <form method="post" action="solicitar-manutencao-preventiva.php">
                        <input type="hidden" name="perfil" value="<?= esc($perfilLogado) ?>">
                        <input type="hidden" name="mat_autor" value="<?= esc($matriculaLogada) ?>">
                        <button class="btn btn-info text-dark" type="submit">Nova</button>
                    </form>
                    <form action="control/exportar-manutencao.php" method="post" target="_blank">
                        <input type="hidden" name="matricula" value="<?= esc($matriculaLogada) ?>">
                        <input type="hidden" name="datain" value="<?= esc($dataInicial) ?>">
                        <input type="hidden" name="datafi" value="<?= esc($dataFinal) ?>">
                        <input type="hidden" name="tipofim" value="<?= esc($tipoSelecionado) ?>">
                        <button type="submit" class="btn btn-excel-green">Exportar Registros em Excel</button>
                    </form>
                </div>
            </section>

            <section style="width: 100%;">
                <table id="tabelaManutencoes" class="table table-striped" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Placa</th>
                            <th>Unidade</th>
                            <th>Data de Agendamento</th>
                            <th>Data de Cadastro</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Oficina</th>
                            <th>Observação</th>
                            <th>Editar manutenção</th>
                            <th>Ordem de Serviço</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($manutencoes as $manutencao): ?>
                            <tr>
                                <td><?= $manutencao['placa'] ?></td>
                                <td><?= $manutencao['unidade'] ?></td>
                                <td><?= $manutencao['data_agendamento'] ?></td>
                                <td><?= $manutencao['data_cadastro'] ?></td>
                                <td><?= $manutencao['tipo'] ?></td>
                                <td><?= $manutencao['status'] ?></td>
                                <td><?= $manutencao['oficina'] ?></td>
                                <td><?= $manutencao['observacao'] ?></td>
                                <td class="text-center"><?= $manutencao['editar'] ?></td>
                                <td class="text-center"><?= $manutencao['ordem_servico'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

        </main>
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
    </script>
    <script>
        $(document).ready(function() {
            $('#tabelaManutencoes').DataTable({
                language: {
                    decimal: '',
                    emptyTable: 'Nada para exibir',
                    info: 'Mostrando de _START_ até _END_ de _TOTAL_ registros',
                    infoEmpty: 'Exibindo página 0 de 0 de 0 registros',
                    infoFiltered: '(filtrado do total de _MAX_ registros)',
                    lengthMenu: 'Exibir _MENU_ registros',
                    loadingRecords: 'Carregando...',
                    processing: 'Processando...',
                    search: 'Buscar:',
                    zeroRecords: 'Nenhum resultado encontrado',
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

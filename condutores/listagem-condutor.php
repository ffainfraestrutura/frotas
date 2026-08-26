<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$matriculaLogada = $autofrotaSessao['matricula'];
$perfilLogado = $autofrotaSessao['perfil'];
$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');

$unidades = ['RJ', 'PR', 'SP', 'MG', 'ES', 'TODOS'];
$statusColaboradorOpcoes = ['TODOS', 'Ativo', 'Afastado', 'Demitido'];
$condutores = [];
$matriculasBloqueadas = ['160030', '410109', '501285', '410039', '411425', '003931'];
$bloquearEdicao = in_array($matriculaLogada, $matriculasBloqueadas, true);

function esc(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function formatarDataCondutor(?string $data): string
{
    if (empty($data) || $data === '0000-00-00' || $data === '0000-00-00 00:00:00') {
        return '';
    }

    $dataFormatada = date_create($data);

    return $dataFormatada ? date_format($dataFormatada, 'd/m/Y') : $data;
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
          AND status <> 'Demitido'
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

function statusFuncionario(?string $status): string
{
    if ($status === '4') {
        return 'Afastado';
    }

    return (string) $status;
}

function statusCondutor($ativo): string
{
    return (string) $ativo === '1' ? 'Ativo' : 'Não ativo';
}

function montarBotaoPost(string $action, array $campos, string $icone, string $titulo): string
{
    $inputs = '';
    foreach ($campos as $nome => $valor) {
        $inputs .= '<input type="hidden" name="' . esc($nome) . '" value="' . esc((string) $valor) . '">';
    }

    return '<form method="post" action="' . esc($action) . '" class="d-inline" target="_blank">'
        . $inputs
        . '<button class="btn-icon" type="submit" title="' . esc($titulo) . '">'
        . '<span class="material-symbols-outlined">' . esc($icone) . '</span>'
        . '</button></form>';
}

function montarAcoesCondutor(array $condutor, string $matriculaLogada, string $perfilLogado, bool $bloquearEdicao): array
{
    $matricula = (string) ($condutor['matricula'] ?? '');
    $temCnh = !empty($condutor['matricula_cnh']);
    $actionCnh = $temCnh
        ? 'editarcnh.php'
        : 'cadastrocnh.php';
    $tituloCnh = $temCnh ? 'Editar CNH' : 'Cadastrar CNH';

    return [
        'editar_cnh' => montarBotaoPost($actionCnh, [
            'matricula' => $matricula,
            'matr_autor' => $matriculaLogada,
            'perfil' => $perfilLogado,
        ], 'edit_note', $tituloCnh),
        'anexar_documentos' => montarBotaoPost('enviardocscondutor.php', [
            'matcondutor' => $matricula,
            'matr_autor' => $matriculaLogada,
            'perf_autor' => $perfilLogado,
        ], 'attach_file', 'Anexar documentos'),
        // NOVO BOTÃO: Associar Veículo
        'associar_veiculo' => montarBotaoPost('cadastro-veiculo-condutor.php', [
            'matricula' => $matricula,
            'matr_autor' => $matriculaLogada,
            'perfil' => $perfilLogado,
        ], 'directions_car', 'Associar Veículo'),
    ];
}

$estadoFuncionario = '';
if (isset($conn) && $conn instanceof mysqli) {
    $estadoFuncionario = buscarEstadoFuncionario($conn, $databaseName, $matriculaLogada);
}

$unidadeRecebida = (string) ($_POST['unidade'] ?? $_GET['unidade'] ?? '');
$unidadeSelecionada = in_array($unidadeRecebida, $unidades, true) ? $unidadeRecebida : ($estadoFuncionario ?: 'TODOS');
$statusColaboradorPostado = (string) ($_POST['status_colaborador'] ?? $_GET['status_colaborador'] ?? 'TODOS');
$statusColaboradorSelecionado = in_array($statusColaboradorPostado, $statusColaboradorOpcoes, true)
    ? $statusColaboradorPostado
    : 'TODOS';

if (!in_array($unidadeSelecionada, $unidades, true)) {
    $unidadeSelecionada = 'TODOS';
}

$sqlCondutores = "
    SELECT
        c.idtbcondutor,
        c.matricula,
        COALESCE(NULLIF(c.nome, ''), f.nome, '') AS nome,
        c.ativo,
        c.placaassoc AS placa,
        c.dataassoc AS associado_em,
        c.datadissoc AS dissociado_em,
        cn.matricula AS matricula_cnh,
        cn.validade AS venc_cnh,
        f.ccusto AS centro_de_custo,
        f.status AS status_funcionario,
        v.unidade AS unidade,
        l.acao AS ultima_atualizacao,
        l.data_e_hora AS atualizado_em,
        CASE
            WHEN l.mat_autor IN ('003535', '003427') THEN 'EQUIPE TI'
            ELSE COALESCE(autor.nome, l.mat_autor, '')
        END AS realizada_por
    FROM `{$databaseName}`.`tbcondutor` c
    INNER JOIN (
        SELECT matricula, MAX(idtbcondutor) AS idtbcondutor
        FROM `{$databaseName}`.`tbcondutor`
        WHERE matricula NOT IN ('003535', '004115')
        GROUP BY matricula
    ) ultimo_condutor
        ON ultimo_condutor.idtbcondutor = c.idtbcondutor
    LEFT JOIN `{$databaseName}`.`tbcnh` cn
        ON cn.matricula = c.matricula
    LEFT JOIN `{$databaseName}`.`tbfuncionario` f
        ON f.matricula = c.matricula
    LEFT JOIN `{$databaseName}`.`tbveiculo` v
        ON v.placa = c.placaassoc
    LEFT JOIN (
        SELECT log_atual.*
        FROM `{$databaseName}`.`tblog` log_atual
        INNER JOIN (
            SELECT matricula, MAX(idtblog) AS idtblog
            FROM `{$databaseName}`.`tblog`
            WHERE tipo IN ('checklist', 'cadastro', 'edição')
            GROUP BY matricula
        ) ultimo_log
            ON ultimo_log.idtblog = log_atual.idtblog
    ) l
        ON l.matricula = c.matricula
    LEFT JOIN `{$databaseName}`.`tbfuncionario` autor
        ON autor.matricula = l.mat_autor
";

$whereCondutores = "
    WHERE c.matricula NOT IN ('003535', '004115')
";

if ($unidadeSelecionada !== 'TODOS') {
    $whereCondutores .= "
      AND EXISTS (
          SELECT 1
          FROM `{$databaseName}`.`tbcondutor` condutor_unidade
          WHERE condutor_unidade.matricula = c.matricula
            AND condutor_unidade.placaassoc NOT IN ('', 'ABC1234', 'ABC1245')
            AND condutor_unidade.placaassoc IN (
                SELECT placa
                FROM `{$databaseName}`.`tbveiculo`
                WHERE unidade = ?
            )
      )
    ";
}

if ($statusColaboradorSelecionado === 'Afastado') {
    $whereCondutores .= "\n      AND (f.status = '4' OR f.status = 'Afastado')\n";
} elseif ($statusColaboradorSelecionado !== 'TODOS') {
    $whereCondutores .= "\n      AND f.status = ?\n";
}

$sqlCondutores .= $whereCondutores . ' ORDER BY nome ASC';

if (isset($conn) && $conn instanceof mysqli) {
    $stmtCondutores = mysqli_prepare($conn, $sqlCondutores);

    if ($stmtCondutores) {
        if ($unidadeSelecionada !== 'TODOS' && $statusColaboradorSelecionado !== 'TODOS' && $statusColaboradorSelecionado !== 'Afastado') {
            mysqli_stmt_bind_param($stmtCondutores, 'ss', $unidadeSelecionada, $statusColaboradorSelecionado);
        } elseif ($unidadeSelecionada !== 'TODOS') {
            mysqli_stmt_bind_param($stmtCondutores, 's', $unidadeSelecionada);
        } elseif ($statusColaboradorSelecionado !== 'TODOS' && $statusColaboradorSelecionado !== 'Afastado') {
            mysqli_stmt_bind_param($stmtCondutores, 's', $statusColaboradorSelecionado);
        }

        mysqli_stmt_execute($stmtCondutores);
        $resultadoCondutores = mysqli_stmt_get_result($stmtCondutores);

        if ($resultadoCondutores) {
            while ($condutor = mysqli_fetch_assoc($resultadoCondutores)) {
                $acoes = montarAcoesCondutor($condutor, $matriculaLogada, $perfilLogado, $bloquearEdicao);
                $matricula = (string) ($condutor['matricula'] ?? '');
                $placa = (string) ($condutor['placa'] ?? '');
                $ultimaAtualizacao = limparDescricaoAtualizacao($condutor['ultima_atualizacao'] ?? '');
                $realizadaPor = (string) ($condutor['realizada_por'] ?? '');

                $infoCondutor = montarBotaoPost('dados-condutor.php', [
                    'matcond' => $matricula,
                ], 'info', 'Histórico Condutor');

                $infoPlaca = $placa !== '' ? montarBotaoPost('/autofrota/veiculos/dadosplaca.php', [
                    'placa' => $placa,
                    'matcond' => $matricula,
                ], 'info', 'Histórico Placa') : '';

                // CNH: vencimento e status simplificado
                $vencCnh = $condutor['venc_cnh'] ?? '';
                $statusCnh = '';
                $cnhVencida = false;
                if (!empty($vencCnh) && $vencCnh !== '0000-00-00' && $vencCnh !== '0000-00-00 00:00:00') {
                    $hoje = new DateTime('today');
                    $validadeCnh = date_create($vencCnh);
                    $cnhVencida = $validadeCnh ? $hoje >= $validadeCnh : false;
                    $statusCnh = $cnhVencida ? 'Carteira vencida' : 'Carteira válida';
                    $vencCnh = $validadeCnh ? date_format($validadeCnh, 'd/m/Y') : $vencCnh;
                } else {
                    $vencCnh = '';
                }

                $condutores[] = [
                    'matricula' => $matricula,
                    'nome' => (string) ($condutor['nome'] ?? ''),
                    'info_condutor' => $infoCondutor,
                    'status_funcionario' => statusFuncionario($condutor['status_funcionario'] ?? ''),
                    'centro_de_custo' => (string) ($condutor['centro_de_custo'] ?? ''),
                    'status_condutor' => statusCondutor($condutor['ativo'] ?? ''),
                    'venc_cnh' => $vencCnh,
                    'status_cnh' => $statusCnh,
                    'cnh_vencida' => $cnhVencida,
                    'unidade' => (string) ($condutor['unidade'] ?? ''),
                    'placa' => $placa,
                    'info_placa' => $infoPlaca,
                    'associado_em' => formatarDataCondutor($condutor['associado_em'] ?? ''),
                    'dissociado_em' => formatarDataCondutor($condutor['dissociado_em'] ?? ''),
                    'editar_cnh' => $acoes['editar_cnh'],
                    'anexar_documentos' => $acoes['anexar_documentos'],
                    'associar_veiculo' => $acoes['associar_veiculo'], // NOVO
                    'atualizado_em' => formatarDataCondutor($condutor['atualizado_em'] ?? ''),
                    'ultima_atualizacao' => $ultimaAtualizacao,
                    'realizada_por' => $realizadaPor,
                ];
            }

            mysqli_free_result($resultadoCondutores);
        }

        mysqli_stmt_close($stmtCondutores);
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
    <title>AutoFrota - Listagem de Condutores (Ativos e Inativos)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../src/autofrota-botoes.css">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body { background: #ffffff; color: #000000; font-size: 12px; }
        .page-title { font-size: 24px; font-weight: 600; margin: 0 0 10px; }
        .notice { font-style: italic; font-size: 12px; margin-bottom: 14px; }
        .filter-area { border-bottom: 1px solid #212529; padding-bottom: 18px; margin-bottom: 16px; }
        .filter-controls { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px 18px; }
        .filter-item { display: flex; flex-direction: column; }
        .form-label { margin-bottom: 6px; }
        .form-select { min-width: 92px; font-size: 12px; border-radius: 2px; }
        .btn { font-size: 12px; border-radius: 3px; padding: 6px 10px; }
        .actions { gap: 10px; margin-bottom: 16px; }
        .btn-icon { border: none; background-color: transparent; padding: 0; color: #0d6efd; line-height: 1; }
        .btn-icon:hover { color: #0a58ca; }
        table.dataTable thead th { color: #000000; font-size: 12px; vertical-align: bottom; }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate { font-size: 12px; }
        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select { font-size: 12px; border: 1px solid #aaa; border-radius: 0; padding: 4px; }
        .table-placeholder { min-height: 24px; }
        #tabelaCondutores tbody tr.cnh-vencida > * { background-color: #d98a85; }
    </style>
</head>
<body class="sb-nav-fixed">
    <?php autofrotaMenu(); ?>

    <div id="layoutSidenav_content">
        <main class="page-wrapper py-3">
            <h1 class="page-title">Condutores Ativos e Inativos</h1>

            <section class="filter-area">
                <form action="" method="post">
                    <div class="filter-controls">
                        <div class="filter-item">
                            <label class="form-label fw-bold" for="unidade">Selecione unidade:</label>
                            <select class="form-select" name="unidade" id="unidade">
                                <?php foreach ($unidades as $unidade): ?>
                                    <option value="<?= esc($unidade) ?>" <?= $unidadeSelecionada === $unidade ? 'selected' : '' ?>><?= esc($unidade) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label fw-bold" for="status_colaborador">Selecione status dos colaboradores:</label>
                            <select class="form-select" name="status_colaborador" id="status_colaborador">
                                <?php foreach ($statusColaboradorOpcoes as $statusOpcao): ?>
                                    <option value="<?= esc($statusOpcao) ?>" <?= $statusColaboradorSelecionado === $statusOpcao ? 'selected' : '' ?>><?= esc($statusOpcao) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-item">
                            <button type="submit" class="btn btn-filter-blue">Filtrar</button>
                        </div>
                    </div>
                </form>

                <div class="mt-3 alert alert-info" role="alert">
                    <strong>Filtro Aplicado:</strong>
                    Unidade: <strong><?= esc($unidadeSelecionada === 'TODOS' ? 'TODAS AS UNIDADES' : $unidadeSelecionada) ?></strong>
                    | Status colaborador: <strong><?= esc($statusColaboradorSelecionado) ?></strong>
                </div>
            </section>

            <section class="d-flex justify-content-start actions flex-wrap">
                <a class="btn btn-secondary" href="cadastrar_condutorespj.php">Cadastrar Funcionário</a>
                <a class="btn btn-success" href="funcionarios-semcnh.php">Cadastrar CNH de Colaborador</a>
                <a class="btn btn-secondary" href="listagemcnh.php">Listagens de CNHs Cadastradas</a>
                <a class="btn btn-primary" href="importarcnh.php">Importar CNHs em Lote</a>
                <form action="control/gerarexcelcondutores.php" method="post" class="d-inline">
                    <input type="hidden" name="unidadef" value="<?= esc($unidadeSelecionada) ?>">
                    <input type="hidden" name="status_colaborador" value="<?= esc($statusColaboradorSelecionado) ?>">
                    <button type="submit" class="btn btn-excel-green">Exportar Condutores em Excel</button>
                </form>
            </section>

            <section style="width: 100%;">
                <table id="tabelaCondutores" class="table table-striped table-placeholder" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Matrícula</th>
                            <th>Nome</th>
                            <th>Centro de Custo</th>
                            <th>Status Colaborador</th>
                            <th>Status como condutor</th>
                            <th>Venc. CNH</th>
                            <th>Status CNH</th>
                            <th>Unidade</th>
                            <th>Placa</th>
                            <th>Data de associação</th>
                            <th>Data dissociação</th>
                            <th>Cadastrar/Editar CNH</th>
                            <th>Anexar documentos</th>
                            <th>Associar Veículo</th> <!-- NOVO -->
                            <th>Detalhes</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </section>
        </main>
    </div>
</div>

<div class="modal fade" id="modalDetalhesCondutor" tabindex="-1" aria-labelledby="modalDetalhesCondutorLabel" aria-hidden="true">
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('a[href]').forEach(function(link) {
            link.setAttribute('target', '_blank');

            const relAtual = (link.getAttribute('rel') || '').trim();
            const relTokens = relAtual === '' ? [] : relAtual.split(/\s+/);

            if (!relTokens.includes('noopener')) {
                relTokens.push('noopener');
            }

            if (!relTokens.includes('noreferrer')) {
                relTokens.push('noreferrer');
            }

            link.setAttribute('rel', relTokens.join(' ').trim());
        });
    });
</script>
<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', function(event) {
        event.preventDefault();
        document.body.classList.toggle('sb-sidenav-toggled');
    });
</script>
<script>
    $(document).ready(function() {
        $('#tabelaCondutores').DataTable({
            data: <?= json_encode($condutores, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            columns: [
                { data: 'matricula', defaultContent: '' },
                {
                    data: null,
                    defaultContent: '',
                    render: function(data, type, row) {
                        return (row.nome || '') + (type === 'display' ? ' ' + (row.info_condutor || '') : '');
                    }
                },
                { data: 'centro_de_custo', defaultContent: '' },
                { data: 'status_funcionario', defaultContent: '' },
                { data: 'status_condutor', defaultContent: '' },
                {
                    data: 'venc_cnh',
                    defaultContent: '',
                    render: function(data, type, row) {
                        if (type !== 'display' || !data) {
                            return data || '';
                        }

                        const title = row.cnh_vencida ? ' title="Carteira vencida."' : '';
                        const color = row.cnh_vencida ? 'red' : 'blue';

                        return '<span' + title + ' style="color: ' + color + ';">' + data + '</span>';
                    }
                },
                { data: 'status_cnh', defaultContent: '' },
                { data: 'unidade', defaultContent: '' },
                {
                    data: null,
                    defaultContent: '',
                    render: function(data, type, row) {
                        return (row.placa || '') + (type === 'display' ? ' ' + (row.info_placa || '') : '');
                    }
                },
                { data: 'associado_em', defaultContent: '' },
                { data: 'dissociado_em', defaultContent: '' },
                { data: 'editar_cnh', defaultContent: '', orderable: false, searchable: false },
                { data: 'anexar_documentos', defaultContent: '', orderable: false, searchable: false },
                { data: 'associar_veiculo', defaultContent: '', orderable: false, searchable: false }, // NOVO
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    defaultContent: '',
                    render: function(data, type) {
                        if (type !== 'display') {
                            return '';
                        }

                        return '<button type="button" class="btn btn-info btn-sm btn-detalhes-condutor">Visualizar</button>';
                    }
                }
            ],
            order: [[1, 'asc']],
            createdRow: function(row, data) {
                if (data.cnh_vencida) {
                    $(row).addClass('cnh-vencida');
                }
            },
            language: {
                emptyTable: 'Nenhum condutor encontrado',
                zeroRecords: 'Nenhum condutor encontrado para o filtro informado',
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
    });

        $('#tabelaCondutores tbody').on('click', '.btn-detalhes-condutor', function() {
            const tabela = $('#tabelaCondutores').DataTable();
            const dadosCondutor = tabela.row($(this).closest('tr')).data() || {};
            const preencherCampo = function(seletor, valor) {
                $(seletor).text(valor || '-');
            };

            preencherCampo('#modalAtualizadoEm', dadosCondutor.atualizado_em);
            preencherCampo('#modalUltimaAtualizacao', dadosCondutor.ultima_atualizacao);
            preencherCampo('#modalRealizadaPor', dadosCondutor.realizada_por);

            const nomeCondutor = dadosCondutor.nome ? ' - ' + dadosCondutor.nome : '';
            $('#modalDetalhesCondutorLabel').text('Detalhes da atualização' + nomeCondutor);

            const modalDetalhes = new bootstrap.Modal(document.getElementById('modalDetalhesCondutor'));
            modalDetalhes.show();
        });
</script>
</body>
</html>

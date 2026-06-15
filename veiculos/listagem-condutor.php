<?php
require_once __DIR__ . '/../auth.php';
// lista só condutores ativos

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

require_once __DIR__ . '/../control/conecta.php';
exigirLogin();

$perfil = (string) ($_SESSION['perfil'] ?? '0');
if ($perfil === '0' || $perfil === '') {
    http_response_code(403);
    exit('Sem permissão.');
}

$usuarioLogado = $_SESSION['usuario'] ?? 'Usuário';
$matriculaLogada = $_SESSION['matricula'] ?? '';
$unidades = ['RJ', 'PR', 'SP', 'MG', 'ES', 'TODOS'];
$condutores = [];
$hoje = new DateTime('today');

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

function obterStatusCnh(?string $validade, DateTime $hoje): array
{
    if (empty($validade) || $validade === '0000-00-00' || $validade === '0000-00-00 00:00:00') {
        return [
            'validade' => '',
            'status' => '',
            'cor' => '',
            'fundo' => '',
        ];
    }

    $validadeCnh = date_create($validade);

    if (!$validadeCnh) {
        return [
            'validade' => $validade,
            'status' => '',
            'cor' => '',
            'fundo' => '',
        ];
    }

    $limiteAviso = (clone $validadeCnh)->modify('-30 days');
    $validadeComparacao = DateTime::createFromFormat('Y-m-d', $validadeCnh->format('Y-m-d')) ?: $validadeCnh;

    if ($hoje >= $validadeComparacao) {
        return [
            'validade' => $validadeCnh->format('d/m/Y'),
            'status' => 'Carteira vencida.',
            'cor' => 'red',
            'fundo' => '#d98a85',
        ];
    }

    if ($hoje >= $limiteAviso) {
        return [
            'validade' => $validadeCnh->format('d/m/Y'),
            'status' => 'Data de vencimento em menos de 30 dias.',
            'cor' => '#000000',
            'fundo' => '#f6ffc7',
        ];
    }

    return [
        'validade' => $validadeCnh->format('d/m/Y'),
        'status' => 'Carteira dentro do período de validade.',
        'cor' => 'blue',
        'fundo' => '#ffffff',
    ];
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

$estadoFuncionario = '';
if (isset($conn) && $conn instanceof mysqli) {
    $estadoFuncionario = buscarEstadoFuncionario($conn, $databaseName, (string) $matriculaLogada);
}

$unidadePostada = $_POST['unidade'] ?? '';
$unidadeSelecionada = in_array($unidadePostada, $unidades, true) ? $unidadePostada : ($estadoFuncionario ?: 'TODOS');

if (!in_array($unidadeSelecionada, $unidades, true)) {
    $unidadeSelecionada = 'TODOS';
}

$sqlCondutoresBase = "
    SELECT
        c.idtbcondutor,
        c.matricula,
        COALESCE(NULLIF(c.nome, ''), f.nome, '') AS nome,
        c.placaassoc AS placa,
        c.dataassoc AS associado_em,
        cn.validade AS venc_cnh,
        f.ccusto AS centro_de_custo,
        f.status AS status_aniel,
        v.unidade AS unidade,
        l.acao AS ultima_atualizacao,
        l.data_e_hora AS atualizado_em,
        CASE
            WHEN l.mat_autor IN ('003535', '003427') THEN 'EQUIPE TI'
            ELSE COALESCE(autor.nome, l.mat_autor, '')
        END AS realizada_por
    FROM `{$databaseName}`.`tbcondutor` c
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
    WHERE c.ativo = 1
      AND c.matricula <> '003535'
";

if ($unidadeSelecionada !== 'TODOS') {
    $whereCondutores .= "
      AND c.placaassoc IN (
          SELECT placa
          FROM `{$databaseName}`.`tbveiculo`
          WHERE unidade = ?
      )
    ";
}

$sqlCondutores = $sqlCondutoresBase . $whereCondutores . ' ORDER BY nome ASC';

if (isset($conn) && $conn instanceof mysqli) {
    $stmtCondutores = mysqli_prepare($conn, $sqlCondutores);

    if ($stmtCondutores) {
        if ($unidadeSelecionada !== 'TODOS') {
            mysqli_stmt_bind_param($stmtCondutores, 's', $unidadeSelecionada);
        }

        mysqli_stmt_execute($stmtCondutores);
        $resultadoCondutores = mysqli_stmt_get_result($stmtCondutores);

        if ($resultadoCondutores) {
            while ($condutor = mysqli_fetch_assoc($resultadoCondutores)) {
                $statusCnh = obterStatusCnh($condutor['venc_cnh'] ?? '', $hoje);

                $condutores[] = [
                    'matricula' => $condutor['matricula'] ?? '',
                    'nome' => $condutor['nome'] ?? '',
                    'centro_de_custo' => $condutor['centro_de_custo'] ?? '',
                    'status_aniel' => $condutor['status_aniel'] ?? '',
                    'unidade' => $condutor['unidade'] ?? '',
                    'placa' => $condutor['placa'] ?? '',
                    'associado_em' => formatarDataCondutor($condutor['associado_em'] ?? ''),
                    'venc_cnh' => $statusCnh['validade'],
                    'status_cnh' => $statusCnh['status'],
                    'atualizado_em' => formatarDataCondutor($condutor['atualizado_em'] ?? ''),
                    'ultima_atualizacao' => limparDescricaoAtualizacao($condutor['ultima_atualizacao'] ?? ''),
                    'realizada_por' => $condutor['realizada_por'] ?? '',
                    'cor_cnh' => $statusCnh['cor'],
                    'fundo_linha' => $statusCnh['fundo'],
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
    <title>AutoFrota - Condutores Ativos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../src/autofrota-botoes.css">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body { background: #ffffff; color: #000000; font-size: 12px; }
        .page-title { font-size: 24px; font-weight: 600; margin: 0 0 10px; }
        .notice { font-style: italic; font-size: 12px; margin-bottom: 14px; }
        .filter-area { border-bottom: 1px solid #212529; padding-bottom: 18px; margin-bottom: 16px; }
        .form-label { margin-bottom: 6px; }
        .form-select { min-width: 92px; font-size: 12px; border-radius: 2px; }
        .btn { font-size: 12px; border-radius: 3px; padding: 6px 10px; }
        .actions { gap: 10px; margin-bottom: 16px; }
        table.dataTable thead th { color: #000000; font-size: 12px; vertical-align: bottom; }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate { font-size: 12px; }
        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select { font-size: 12px; border: 1px solid #aaa; border-radius: 0; padding: 4px; }
        .table-placeholder { min-height: 24px; }
    </style>
</head>
<body class="sb-nav-fixed">
    <?php include __DIR__ . '/../includes/menu_superior_simples.php'; ?>

        <div id="layoutSidenav_content">
        <main class="page-wrapper py-3">
        <h1 class="page-title">Condutores Ativos</h1>

        <p class="notice">Aviso: Para otimizar o carregamento, nesta página estarão listados apenas os condutores com veículos com unidade em seu estado. Para visualizar todos os condutores ativos, filtre por 'Todos'.</p>
        <p class="notice">Visando a unificação dos processos, a associação/desassociação do veículo a/de condutor deve ser realizada através um checklist (vistoria).</p>

        <section class="filter-area">
            <form action="listagem-condutores.php" method="post">
                <div class="d-flex align-items-end">
                    <div>
                        <label class="form-label fw-bold" for="unidade">Selecione unidade do veículo:</label>
                        <select class="form-select" name="unidade" id="unidade">
                            <?php foreach ($unidades as $unidade): ?>
                                <option value="<?= htmlspecialchars($unidade) ?>" <?= $unidadeSelecionada === $unidade ? 'selected' : '' ?>><?= htmlspecialchars($unidade) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ms-2">
                        <button type="submit" class="btn btn-filter-blue">Filtrar</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="d-flex justify-content-start actions flex-wrap">
            <a class="btn btn-primary" href="../importcnh.php">Importar CNHs em Lote</a>
            <a class="btn btn-success" href="../funcionarios.php">Cadastrar Nova CNH</a>
            <a class="btn btn-secondary" href="../listagemcnh.php">Listagens de CNHs Cadastradas</a>
            <a class="btn btn-secondary" href="../cadastrar_condutorespj.php">Cadastrar condutores</a>
            <a class="btn btn-secondary" href="../listagemgeralcondutores.php">Listar Condutores Ativos e Inativos</a>
            <form action="listagem-condutores.php" method="post" class="d-inline">
                <input type="hidden" name="unidadef" value="<?= htmlspecialchars($unidadeSelecionada) ?>">
                <button type="submit" class="btn btn-excel-green">Excel Condutores Ativos</button>
            </form>
        </section>

        <section style="width: 100%;">
            <table id="tabelaCondutores" class="table table-striped table-placeholder" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Matrícula</th>
                        <th>Nome</th>
                        <th>Centro de Custo</th>
                        <th>Status<br>Colaborador</th>
                        <th>Unidade</th>
                        <th>Placa</th>
                        <th>Associado<br>em</th>
                        <th>Venc.<br>CNH</th>
                        <th>Status CNH</th>
                        <th>Atualizado<br>em</th>
                        <th>Última atualização</th>
                        <th>Realizada por</th>
                    </tr>
                </thead>
                <tbody></tbody>
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
            $('#tabelaCondutores').DataTable({
                data: <?= json_encode($condutores, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                columns: [
                    { data: 'matricula', defaultContent: '' },
                    { data: 'nome', defaultContent: '' },
                    { data: 'centro_de_custo', defaultContent: '' },
                    { data: 'status_aniel', defaultContent: '' },
                    { data: 'unidade', defaultContent: '' },
                    { data: 'placa', defaultContent: '' },
                    { data: 'associado_em', defaultContent: '' },
                    {
                        data: 'venc_cnh',
                        defaultContent: '',
                        render: function(data, type, row) {
                            if (type !== 'display' || !row.cor_cnh) {
                                return data || '';
                            }

                            return '<span style="color: ' + row.cor_cnh + ';">' + (data || '') + '</span>';
                        }
                    },
                    { data: 'status_cnh', defaultContent: '' },
                    { data: 'atualizado_em', defaultContent: '' },
                    { data: 'ultima_atualizacao', defaultContent: '' },
                    { data: 'realizada_por', defaultContent: '' }
                ],
                createdRow: function(row, data) {
                    if (data.fundo_linha) {
                        $(row).css('background-color', data.fundo_linha);
                    }
                },
                language: {
                    emptyTable: '',
                    zeroRecords: '',
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
    </script>
</body>
</html>
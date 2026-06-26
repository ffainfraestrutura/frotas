<?php
require_once __DIR__ . '/../includes/autofrota_common.php';
session_start();

$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');

$dataInicial = trim((string) ($_POST['data_inicial'] ?? ''));
$dataFinal = trim((string) ($_POST['data_final'] ?? ''));
$incluirFinalizadas = trim((string) ($_POST['incluir_finalizadas'] ?? 'nao'));
$hoje = new DateTimeImmutable('now');

$linhasMultas = [];

function esc(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function formatarData(?string $valor): string
{
    if (empty($valor) || $valor === '0000-00-00' || $valor === '0000-00-00 00:00:00') {
        return '-';
    }

    $data = date_create($valor);
    return $data ? date_format($data, 'd/m/Y') : (string) $valor;
}

function formatarDataHora(?string $valor): string
{
    if (empty($valor) || $valor === '0000-00-00 00:00:00') {
        return '-';
    }

    $data = date_create($valor);
    return $data ? date_format($data, 'd/m/Y H:i:s') : (string) $valor;
}

function calcularDiasVencimento(string $dataVencimento, DateTimeImmutable $hoje): array
{
    if (empty($dataVencimento) || $dataVencimento === '0000-00-00' || $dataVencimento === '0000-00-00 00:00:00') {
        return ['texto' => 'Indeterminado', 'classe' => ''];
    }

    $dtVenc = date_create($dataVencimento);
    if (!$dtVenc) {
        return ['texto' => 'Indeterminado', 'classe' => ''];
    }

    $dtVencImmutable = DateTimeImmutable::createFromMutable($dtVenc);
    
    if ($hoje > $dtVencImmutable) {
        $intervalo = $hoje->diff($dtVencImmutable);
        return ['texto' => 'Venceu há ' . $intervalo->format('%a') . ' dia(s)', 'classe' => 'situacao-vencido'];
    } else {
        $intervalo = $hoje->diff($dtVencImmutable);
        return ['texto' => 'Vence em ' . $intervalo->format('%a') . ' dia(s)', 'classe' => 'situacao-aberto'];
    }
}

function buscarStatusFuncionario(?mysqli $conn, string $matricula): string
{
    if (!$conn || $matricula === '') {
        return '';
    }

    $sql = 'SELECT status FROM bdaniel.tbfuncionario WHERE matricula = ? LIMIT 1';
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

function buscarDataEnvioRecibo(?mysqli $conn, string $placa, string $autoinfra): string
{
    if (!$conn || $placa === '' || $autoinfra === '') {
        return '-';
    }

    $sql = "SELECT data_e_hora FROM tblog WHERE placa = ? AND tipo = 'multa' AND acao LIKE ? ORDER BY data_e_hora DESC LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return '-';
    }

    $likePattern = "%{$autoinfra}-Imprimir Recibo DP%";
    mysqli_stmt_bind_param($stmt, 'ss', $placa, $likePattern);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return $row ? formatarData((string) ($row['data_e_hora'] ?? '')) : '-';
}

if ($conn instanceof mysqli) {
    mysqli_set_charset($conn, 'utf8mb4');

    // Definir a condição do filtro DP Finalizado
    if ($incluirFinalizadas === 'sim') {
        $filtroDp = "1=1"; // Mostra todas as multas (inclusive DP Finalizado)
    } else {
        $filtroDp = "tdp <> 'DP Finalizado'"; // Exclui DP Finalizado
    }

    if (!empty($dataInicial) && !empty($dataFinal)) {
        $sql = "
            SELECT 
                mt.idtbmovidatramite,
                mt.idmovida,
                mt.placa,
                mt.autoinfra,
                mt.dtinfra,
                mt.dtvenc,
                mt.valor,
                mt.tdp,
                mt.gravidade,
                mt.recibo,
                mt.datahoracadastro,
                f.nome,
                f.matricula
            FROM
                tbmovidatramite mt
            JOIN
                tbmulta m ON m.idtbmulta = mt.idmulta
            JOIN
                tbfuncionario f ON f.matricula = mt.matricula
            WHERE
                {$filtroDp}
                AND mt.placa <> ''
                AND mt.placa <> 'ABC1234'
                AND mt.placa <> 'ABC1245'
                AND mt.recibo <> ''
                AND f.status <> 'demitido'
                AND YEAR(datahoracadastro) = YEAR(CURDATE())
                AND datahoracadastro BETWEEN ? AND ?
            ORDER BY mt.datahoracadastro DESC
        ";

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            $dataInicioDt = $dataInicial . ' 00:00:00';
            $dataFinalDt = $dataFinal . ' 23:59:59';
            mysqli_stmt_bind_param($stmt, 'ss', $dataInicioDt, $dataFinalDt);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
        }
    } else {
        $sql = "
            SELECT 
                mt.idtbmovidatramite,
                mt.idmovida,
                mt.placa,
                mt.autoinfra,
                mt.dtinfra,
                mt.dtvenc,
                mt.valor,
                mt.tdp,
                mt.gravidade,
                mt.recibo,
                f.nome,
                f.matricula
            FROM
                tbmovidatramite mt
            JOIN
                tbmulta m ON m.idtbmulta = mt.idmulta
            JOIN
                tbfuncionario f ON f.matricula = mt.matricula
            WHERE
                {$filtroDp}
                AND mt.placa <> ''
                AND mt.placa <> 'ABC1234'
                AND mt.placa <> 'ABC1245'
                AND mt.recibo <> ''
                AND f.status <> 'demitido'
        ";

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
        }
    }

    if (isset($res) && $res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $id = (string) ($row['idtbmovidatramite'] ?? '');
            $placa = (string) ($row['placa'] ?? '');
            $gravidade = (string) ($row['gravidade'] ?? '');
            $autoinfra = (string) ($row['autoinfra'] ?? '');
            $datainfra = (string) ($row['dtinfra'] ?? '');
            $dtvenc = (string) ($row['dtvenc'] ?? '');
            $valor = (string) ($row['valor'] ?? '');
            $tdp = (string) ($row['tdp'] ?? '');
            $nomecond = (string) ($row['nome'] ?? '');
            $matcond = (string) ($row['matricula'] ?? '');
            $recibo = (string) ($row['recibo'] ?? '');

            // Tratamento do link baseado no tdp
            if ($tdp === 'Validar recibo') {
                $tdp = 'Validar Recibo';
            }

            // Tratamento do valor
            $valorFormatado = 'R$ ' . number_format((float) str_replace(',', '.', $valor), 2, ',', '.');

            // Formatar datas
            $datainfraFormatada = formatarData($datainfra);
            $dtvencFormatada = formatarData($dtvenc);
            
            // Calcular situação do vencimento
            $situacaoVencimento = calcularDiasVencimento($dtvenc, $hoje);
            
            // Buscar status do condutor
            $statusCondutor = buscarStatusFuncionario($conn, $matcond);
            
            // Buscar data de envio do recibo
            $dataRecibo = buscarDataEnvioRecibo($conn, $placa, $autoinfra);

            // Gerar botão de trâmite
            $botaoTramite = '';
            if ($id !== '') {
                $classeBotao = $tdp === 'Validar Recibo' ? 'btn-success' : 'btn-secondary';
                $botaoTramite = sprintf(
                    '<form method="post" action="./validar_recibo.php" class="d-inline">
                        <input type="hidden" name="id" value="%s">
                        <button type="submit" class="btn %s btn-sm w-100">%s</button>
                    </form>',
                    esc($id),
                    $classeBotao,
                    esc($tdp)
                );
            }

            $linhasMultas[] = [
                'matricula' => $matcond,
                'nome_condutor' => $nomecond,
                'status_condutor' => $statusCondutor,
                'placa' => $placa,
                'gravidade' => $gravidade,
                'auto_infracao' => $autoinfra,
                'data_infracao' => $datainfraFormatada,
                'data_vencimento' => $dtvencFormatada,
                'situacao_vencimento' => $situacaoVencimento['texto'],
                'classe_vencimento' => $situacaoVencimento['classe'],
                'data_recibo' => $dataRecibo,
                'valor' => $valorFormatado,
                'tramite' => $botaoTramite,
            ];
        }
        mysqli_free_result($res);
        if (isset($stmt)) mysqli_stmt_close($stmt);
    }
}

// Definir o departamento com base nos dados da sessão
$perfil = $_SESSION['perfil'] ?? '';
$tipo = $_SESSION['tipo'] ?? '';

if ($perfil == '5') {
    $depart = 'Administrador';
} elseif ($tipo == '1') {
    $depart = 'Supervisor';
} elseif ($tipo == '4') {
    $depart = 'Frotas';
} elseif ($tipo == '6') {
    $depart = 'DP';
} elseif ($tipo == '7') {
    $depart = 'Financeiro';
} elseif ($tipo == '8') {
    $depart = 'Controladoria';
} else {
    $depart = '';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="FFA" />
    <meta name="author" content="FFA" />
    <link rel="icon" type="image/png" href="src/images/favicon.png" />
    <title>Listar Multas DP</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link href="src/css/styles.css" rel="stylesheet" />

    <style>
        body {
            background: #ffffff;
            color: #000000;
            font-size: 12px;
        }

        .page-wrapper {
            max-width: 100%;
            overflow-x: auto;
            padding: 20px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 5px;
        }

        .notice {
            font-style: italic;
            font-size: 11px;
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
            font-size: 12px;
        }

        .form-control, .form-select, .form-check-input {
            font-size: 12px;
        }

        .btn {
            font-size: 12px;
            border-radius: 3px;
            padding: 6px 12px;
        }

        .btn-excel {
            background-color: #1F724C;
            color: white;
            margin-left: 10px;
        }

        .btn-excel:hover {
            background-color: #145a3c;
            color: white;
        }

        .table-responsive-wrapper {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 20px;
        }

        #tabelaMultas {
            width: 100%;
            min-width: 1400px;
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

        .situacao-vencido {
            color: #fff;
            background-color: #733030;
            display: inline-block;
            padding: 3px 6px;
            border-radius: 3px;
        }

        .situacao-aberto {
            color: #fff;
            background-color: #255573;
            display: inline-block;
            padding: 3px 6px;
            border-radius: 3px;
        }

        .alert-info {
            font-size: 12px;
            padding: 10px;
        }
    </style>
</head>

<body class="sb-nav-fixed">
    <?php autofrotaMenu(); ?>

    <div id="layoutSidenav_content">
        <main class="page-wrapper py-3">
            <h1 class="page-title">Multas</h1>
            <?php if ($depart !== ''): ?>
                <h6 class="h6 pt-0 pb-2 mb-3"><?= esc($depart) ?></h6>
            <?php endif; ?>

            <section class="filter-area">
                <form method="post" action="#" class="col-12">
                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <label class="form-label" for="data_inicial">Data Inicial:</label>
                            <input class="form-control" type="date" name="data_inicial" id="data_inicial" value="<?= esc($dataInicial) ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="data_final">Data Final:</label>
                            <input class="form-control" type="date" name="data_final" id="data_final" value="<?= esc($dataFinal) ?>">
                        </div>

                        <div class="col-md-4">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="incluir_finalizadas" id="incluir_finalizadas" value="sim" <?= $incluirFinalizadas === 'sim' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="incluir_finalizadas">
                                    Incluir multas finalizadas
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button class="btn btn-success" type="submit">Filtrar</button>
                        <button class="btn btn-danger" type="button" onclick="limparFiltro()">Limpar Filtro</button>
                        <button class="btn btn-excel" type="button" onclick="exportarExcel()">
                            <i class="fas fa-file-excel"></i> Exportar para Excel
                        </button>
                    </div>
                </form>
            </section>

            <?php if (!empty($dataInicial) && !empty($dataFinal)): 
                $dataInicialFmt = DateTime::createFromFormat('Y-m-d', $dataInicial);
                $dataFinalFmt = DateTime::createFromFormat('Y-m-d', $dataFinal);
                $msgFinalizadas = ($incluirFinalizadas === 'sim') ? ' | Incluindo multas DP Finalizado' : '';
            ?>
                <div class="alert alert-info mt-3" role="alert">
                    <strong>✓ Filtro Aplicado:</strong> Período de <strong><?= $dataInicialFmt ? $dataInicialFmt->format('d/m/Y') : esc($dataInicial) ?></strong> a <strong><?= $dataFinalFmt ? $dataFinalFmt->format('d/m/Y') : esc($dataFinal) ?></strong><?= $msgFinalizadas ?>
                </div>
            <?php endif; ?>

            <div class="table-responsive-wrapper">
                <table id="tabelaMultas" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Matrícula condutor</th>
                            <th>Nome condutor</th>
                            <th>Situação condutor</th>
                            <th>Placa</th>
                            <th>Gravidade</th>
                            <th>Nº do Auto</th>
                            <th>Data Infração</th>
                            <th>Data Vencimento</th>
                            <th>Situação</th>
                            <th>Recibo Enviado em</th>
                            <th>Valor</th>
                            <th>Trâmite</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Os dados serão carregados via JavaScript do DataTable -->
                    </tbody>
                </table>
            </div>
        </main>

        <footer class="py-4 bg-light mt-auto">
            <div class="container-fluid px-4">
                <div class="d-flex align-items-center justify-content-between small">
                    <div class="text-muted">Copyright &copy; FFA Infraestrutura</div>
                </div>
            </div>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="src/js/scripts.js"></script>

    <script>
        <?php if (!empty($linhasMultas)): ?>
        const dadosMultas = <?= json_encode($linhasMultas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        <?php else: ?>
        const dadosMultas = [];
        <?php endif; ?>

        function limparFiltro() {
            $("#data_inicial").val("");
            $("#data_final").val("");
            $("#incluir_finalizadas").prop("checked", false);
            window.location.href = window.location.pathname;
        }

        function exportarExcel() {
            try {
                if (typeof XLSX === 'undefined') {
                    alert('Biblioteca XLSX não carregada. Tente novamente.');
                    return;
                }

                if (dadosMultas.length === 0) {
                    alert('Nenhum dado para exportar!');
                    return;
                }

                // Cabeçalhos (excluindo a coluna de trâmite que contém HTML)
                const cabecalhos = [
                    'Matrícula condutor', 'Nome condutor', 'Situação condutor', 'Placa',
                    'Gravidade', 'Nº do Auto', 'Data Infração', 'Data Vencimento',
                    'Situação', 'Recibo Enviado em', 'Valor'
                ];

                // Prepara os dados
                const dadosExportacao = dadosMultas.map(item => [
                    item.matricula || '',
                    item.nome_condutor || '',
                    item.status_condutor || '',
                    item.placa || '',
                    item.gravidade || '',
                    item.auto_infracao || '',
                    item.data_infracao || '',
                    item.data_vencimento || '',
                    item.situacao_vencimento || '',
                    item.data_recibo || '',
                    item.valor || ''
                ]);

                const ws_data = [cabecalhos, ...dadosExportacao];
                const ws = XLSX.utils.aoa_to_sheet(ws_data);

                // Ajusta largura das colunas
                ws['!cols'] = cabecalhos.map(() => ({ wch: 20 }));

                // Formata cabeçalho
                for (let i = 0; i < cabecalhos.length; i++) {
                    const cellAddress = XLSX.utils.encode_col(i) + '1';
                    if (!ws[cellAddress]) ws[cellAddress] = {};
                    ws[cellAddress].s = {
                        font: { bold: true, color: { rgb: "FFFFFF" } },
                        fill: { fgColor: { rgb: "4472C4" } },
                        alignment: { horizontal: "center", vertical: "center" }
                    };
                }

                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "Multas DP");

                const dataAtual = new Date();
                const nomeArquivo = `multas_dp_${dataAtual.getFullYear()}-${String(dataAtual.getMonth() + 1).padStart(2, '0')}-${String(dataAtual.getDate()).padStart(2, '0')}.xlsx`;
                
                XLSX.writeFile(wb, nomeArquivo);
                alert(`Arquivo exportado com sucesso: ${nomeArquivo}\n(${dadosMultas.length} linhas)`);

            } catch (error) {
                console.error('Erro na exportação:', error);
                alert('Erro ao exportar: ' + error.message);
            }
        }

        $(document).ready(function () {
            $('#tabelaMultas').DataTable({
                data: dadosMultas,
                autoWidth: false,
                columns: [
                    { data: 'matricula', defaultContent: '' },
                    { data: 'nome_condutor', defaultContent: '' },
                    { data: 'status_condutor', defaultContent: '' },
                    { data: 'placa', defaultContent: '' },
                    { data: 'gravidade', defaultContent: '' },
                    { data: 'auto_infracao', defaultContent: '' },
                    { data: 'data_infracao', defaultContent: '' },
                    { data: 'data_vencimento', defaultContent: '' },
                    { 
                        data: 'situacao_vencimento',
                        defaultContent: '',
                        render: function(data, type, row) {
                            if (type !== 'display') return data;
                            const classe = row.classe_vencimento || '';
                            return `<span class="${classe}">${escaped(data)}</span>`;
                        }
                    },
                    { data: 'data_recibo', defaultContent: '' },
                    { data: 'valor', defaultContent: '' },
                    { data: 'tramite', defaultContent: '', orderable: false }
                ],
                order: [[7, 'asc']],
                language: {
                    decimal: "",
                    emptyTable: "Nada para exibir",
                    info: "Mostrando de _START_ até _END_ de _TOTAL_ registros",
                    infoEmpty: "Exibindo página 0 de 0 de 0 registros",
                    infoFiltered: "(filtrado do total de _MAX_ registros)",
                    lengthMenu: "Exibir _MENU_ registros",
                    loadingRecords: "Carregando...",
                    processing: "Processando...",
                    search: "Buscar:",
                    zeroRecords: "Nenhum resultado encontrado",
                    paginate: {
                        first: "Primeira",
                        last: "Última",
                        next: "Próxima",
                        previous: "Anterior"
                    }
                }
            });
        });

        function escaped(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
    </script>
</body>
</html>

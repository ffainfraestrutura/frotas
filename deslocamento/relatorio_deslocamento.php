<?php
// ==============================================
// CONFIGURAÇÕES INICIAIS
// ==============================================
setlocale(LC_ALL, 'pt_BR.utf8');
date_default_timezone_set('America/Sao_Paulo');
session_start();

$nome = $_SESSION['nome'];
$usuariof = $_SESSION['usuario'];
$matricula = $_SESSION['matricula'];
$perfil = $_SESSION['perfil'];

// Validação de sessão
if ($perfil == null) {
    echo "<script language='javascript' type='text/javascript'>alert('Você deve logar para ter acesso');window.location=\"index.php\"</script>";
    exit();
}

// Validação de perfil
if (!in_array($perfil, ['3', '4', '5', 3, 4, 5])) {
    echo "<script language='javascript' type='text/javascript'>alert('Você não tem permissão para acessar esta página');window.location=\"index.php\"</script>";
    exit();
}

$_SESSION['perfil'] = $perfil;
$_SESSION['matricula'] = $matricula;
$_SESSION['nome'] = $nome;
$_SESSION['usuario'] = $usuariof;

require_once '../control/conecta.php';
require_once '../includes/autofrota_common.php';

// ==============================================
// RECEBER PARÂMETROS DO POST
// ==============================================

$dataSelecionada = $_POST['diai'] ?? '';
$ccustoSelecionado = $_POST['ccusto'] ?? '';
$mostrarRelatorio = (!empty($dataSelecionada) && !empty($ccustoSelecionado));

// ==============================================
// CONSULTAS NO TOPO (SEMPRE EXECUTADAS)
// ==============================================

// 1. Buscar centros de custo
$sqlCcusto = "SELECT DISTINCT UPPER(TRIM(ccusto)) as ccusto_limpo
              FROM bdcorp.tbfuncionario 
              WHERE ccusto IS NOT NULL 
              AND ccusto != ''
              AND ccusto NOT REGEXP '^[0-9]'
              AND status = 'Ativo'
              ORDER BY ccusto_limpo";
$resultadoCcusto = mysqli_query($conn, $sqlCcusto) or die(mysqli_error($conn));
$centrosCusto = array();
while ($rowCcusto = mysqli_fetch_array($resultadoCcusto)) {
    $ccustoItem = $rowCcusto[0];
    if (!preg_match('/^[0-9]/', $ccustoItem) && !empty($ccustoItem)) {
        $centrosCusto[] = $ccustoItem;
    }
}

// 2. Buscar semanas disponíveis
$semanasDisponiveis = array();
$hoje_time = time();
$dia_da_semana = date('w', $hoje_time);
$segunda_atual = ($dia_da_semana == 1) ? $hoje_time : strtotime('last Monday', $hoje_time);
for($contador = 0; $contador < 4; $contador++) {
    $dataval = date('Y-m-d', $segunda_atual);
    $dataFormatada = date('d/m/Y', strtotime($dataval));
    $semanasDisponiveis[] = array(
        'valor' => $dataval,
        'label' => $dataFormatada
    );
    $segunda_atual = strtotime('-7 days', $segunda_atual);
}

// 3. Buscar funcionários (para contagem)
$sqlFuncionarios = "SELECT COUNT(DISTINCT f.matricula) as total
                    FROM bdcorp.tbfuncionario f
                    INNER JOIN bdcorp.tbusuario u ON f.matricula = u.matricula
                    WHERE f.status = 'Ativo'
                    AND f.matricula IS NOT NULL 
                    AND f.matricula != ''
                    AND u.nome IS NOT NULL 
                    AND u.nome != ''
                    AND u.nome NOT REGEXP '[0-9]'
                    AND u.nome NOT REGEXP '^adm'
                    AND LOWER(u.nome) NOT LIKE '%administrador%'
                    AND LOWER(u.nome) NOT LIKE '%admin%'
                    AND f.matricula NOT IN ('000000', '111111', '222222', '333333', '444444', '555555', '666666', '777777', '888888', '999999')
                    AND f.matricula NOT REGEXP '^(.)\\1{5}$'
                    AND f.cargo NOT LIKE 'GEREN%'
                    AND f.cargo NOT LIKE 'SUPERVI%'
                    AND f.cargo NOT LIKE 'COORDE%'";
$resultTotal = mysqli_query($conn, $sqlFuncionarios) or die(mysqli_error($conn));
$rowTotal = mysqli_fetch_array($resultTotal);
$totalFuncionarios = $rowTotal[0] ?? 0;

// ==============================================
// CONSULTAS DO RELATÓRIO (SÓ EXECUTADAS SE TIVER FILTRO)
// ==============================================

$totaisGerais = array();
$totalGeralSemanal = 0;
$funcionarios = array();
$nomesDiasDaSemana = array(
    1 => 'Segunda-feira',
    2 => 'Terça-feira',
    3 => 'Quarta-feira',
    4 => 'Quinta-feira',
    5 => 'Sexta-feira',
    6 => 'Sábado',
    7 => 'Domingo'
);
$usegunda = '';
$totalGeralFormatado = '0,0';
$mediaGeralFormatada = '0,0';
$mediaGeralDiaria = 0;

if ($mostrarRelatorio) {
    // Calcular a semana
    $data_referencia = new DateTime($dataSelecionada);
    if ($data_referencia->format('N') == 1) {
        $usegunda = $data_referencia->format('Y-m-d');
    } else {
        $data_referencia->modify('last monday');
        $usegunda = $data_referencia->format('Y-m-d');
    }
    
    // Buscar funcionários do centro de custo
    $sqlFuncs = "SELECT DISTINCT f.matricula, u.nome, f.ccusto
                 FROM bdcorp.tbfuncionario f
                 INNER JOIN bdcorp.tbusuario u ON f.matricula = u.matricula
                 WHERE UPPER(TRIM(f.ccusto)) = UPPER('$ccustoSelecionado')
                 AND f.status = 'Ativo'
                 AND f.matricula IS NOT NULL 
                 AND f.matricula != ''
                 AND u.nome IS NOT NULL 
                 AND u.nome != ''
                 AND u.nome NOT REGEXP '[0-9]'
                 AND u.nome NOT REGEXP '^adm'
                 AND LOWER(u.nome) NOT LIKE '%administrador%'
                 AND LOWER(u.nome) NOT LIKE '%admin%'
                 AND f.matricula NOT IN ('000000', '111111', '222222', '333333', '444444', '555555', '666666', '777777', '888888', '999999')
                 AND f.matricula NOT REGEXP '^(.)\\1{5}$'
                 AND f.cargo NOT LIKE 'GEREN%'
                 AND f.cargo NOT LIKE 'SUPERVI%'
                 AND f.cargo NOT LIKE 'COORDE%'
                 ORDER BY u.nome";
    $resultFuncs = mysqli_query($conn, $sqlFuncs) or die(mysqli_error($conn));
    while ($rowFunc = mysqli_fetch_array($resultFuncs)) {
        $funcionarios[] = array(
            'matricula' => $rowFunc[0],
            'nome' => $rowFunc[1],
            'ccusto' => $rowFunc[2]
        );
    }
    
    // Calcular totais para cada funcionário
    foreach ($funcionarios as $func) {
        $mattec = $func['matricula'];
        $nomeFunc = $func['nome'];
        $ccustoFunc = $func['ccusto'];
        
        $totaisDiarios = array();
        $dataInicial = new DateTime($usegunda);
        $dataInicial->modify('next sunday');
        $pdomingo = $dataInicial->format('Y-m-d');
        $dataFinal = new DateTime($pdomingo);
        $dataInicial = new DateTime($usegunda);
        
        while ($dataInicial <= $dataFinal) {
            $dataDia = $dataInicial->format('Y-m-d');
            
            // Buscar KM rodados
            $sqlKm = "SELECT ROUND(kmsrodados,1) FROM tbkmrodadosdia WHERE matricula = '$mattec' AND dia = '$dataDia'";
            $resultKm = mysqli_query($conn, $sqlKm) or die(mysqli_error($conn));
            $rowKm = mysqli_fetch_array($resultKm);
            $kmsrodados = $rowKm[0] ?? 0;
            
            // Buscar edições
            $sqlEdit = "SELECT kmsedit, posneg FROM tbkmdiarioedit WHERE matricula = '$mattec' AND dataedit = '$dataDia'";
            $resultEdit = mysqli_query($conn, $sqlEdit) or die(mysqli_error($conn));
            if (mysqli_num_rows($resultEdit) >= 1) {
                $rowEdit = mysqli_fetch_array($resultEdit);
                $kmsedit = $rowEdit[0];
                $posneg = $rowEdit[1];
                if ($posneg == 1) {
                    $kmsrodados = $kmsrodados - $kmsedit;
                } else {
                    $kmsrodados = $kmsrodados + $kmsedit;
                }
            }
            
            $totaisDiarios[] = floatval($kmsrodados);
            $dataInicial->add(new DateInterval('P1D'));
        }
        
        $totalSemanal = array_sum($totaisDiarios);
        $mediaDiaria = count($totaisDiarios) > 0 ? $totalSemanal / 6 : 0;
        
        $totalGeralSemanal += $totalSemanal;
        $totaisGerais[] = array(
            'matricula' => $mattec,
            'nome' => $nomeFunc,
            'total' => $totalSemanal,
            'media' => $mediaDiaria
        );
    }
    
    // Ordenar do maior para o menor
    usort($totaisGerais, function($a, $b) {
        return $b['total'] <=> $a['total'];
    });
    
    $mediaGeralDiaria = count($totaisGerais) > 0 ? $totalGeralSemanal / 6 : 0;
    $totalGeralFormatado = number_format($totalGeralSemanal, 1, ',', '.');
    $mediaGeralFormatada = number_format($mediaGeralDiaria, 1, ',', '.');
}

header("Content-type: text/html; charset=utf-8");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Relatório Deslocamento - Portal FFA</title>
    <link href="../src/css/styles.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>

        html{
            zoom: 0.8;
        }
        .select2-container .select2-selection--single {
            height: 38px !important;
            display: flex;
            align-items: center;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
        }
        .card-filtro {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-bottom: 20px;
        }
        .btn-filtrar {
            padding: 10px 40px;
            font-weight: 600;
        }
        .filtro-info {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            border-left: 4px solid #0d6efd;
            margin-bottom: 20px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .table-hover tbody tr:hover {
            background-color: #e7f3ff;
        }
        .btn-ver-rotas {
            white-space: nowrap;
        }
        .dt-buttons {
            margin-bottom: 10px;
        }
        .total-row {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        /* Estilo para cabeçalho da tabela em cinza */
        .table-header-gray thead th {
            background-color: #6c757d !important;
            color: white !important;
            border-color: #5a6268 !important;
        }
        .table-header-gray thead th a {
            color: white !important;
        }
        .table-header-gray tfoot {
            background-color: #6c757d !important;
            color: white !important;
        }
        .table-header-gray tfoot td {
            background-color: #6c757d !important;
            color: white !important;
            border-color: #5a6268 !important;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .card-filtro {
                display: none !important;
            }
            .dataTables_wrapper .dataTables_filter,
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_paginate,
            .dataTables_wrapper .dataTables_info {
                display: none !important;
            }
        }
    </style>
</head>
<body class="sb-nav-fixed">
    <?php autofrotaMenu(); ?>

    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid px-4">
                <h1 class="h1 mt-2">
                    <i class="fas fa-route me-2"></i>Relatório Deslocamento
                </h1>
                <p class="text-muted mb-4">Selecione o período e o centro de custo para visualizar o relatório detalhado</p>

                <!-- BOTÃO EXPORTAR EXCEL -->
                <div class="d-flex justify-content-end mb-3 gap-2 no-print">
                    <button type="button" class="btn btn-success" id="btnExportarExcel" title="Exportar para Excel">
                        <i class="fa-solid fa-file-excel"></i>
                        Exportar Excel
                    </button>
                </div>

                <!-- FORMULÁRIO DE FILTRO -->
                <div class="card-filtro no-print">
                    <form method="post" action="" id="formFiltro">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label for="diai" class="form-label fw-bold">
                                    <i class="fas fa-calendar-alt me-1"></i> Semana
                                </label>
                                <select class="form-select" name="diai" id="diai" required style="height: 45px;">
                                    <option value="">Selecione a semana</option>
                                    <?php foreach ($semanasDisponiveis as $semana): 
                                        $selected = ($dataSelecionada == $semana['valor']) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $semana['valor']; ?>" <?php echo $selected; ?>>
                                            <?php echo $semana['label']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-5">
                                <label for="ccusto" class="form-label fw-bold">
                                    <i class="fas fa-building me-1"></i> Centro de Custo
                                </label>
                                <select class="select2-ccusto form-select" name="ccusto" id="ccusto" style="width: 100%; height: 45px;" required>
                                    <option value="">Selecione um Centro de Custo</option>
                                    <?php foreach ($centrosCusto as $ccustoItem): 
                                        $selected = ($ccustoSelecionado == $ccustoItem) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $ccustoItem; ?>" <?php echo $selected; ?>>
                                            <?php echo $ccustoItem; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2 d-flex align-items-end">
                                <div class="w-100">
                                    <button type="submit" class="btn btn-success w-100" id="btnFiltrar">
                                        <i class="fas fa-search me-2"></i> Filtrar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <?php if ($mostrarRelatorio && !empty($totaisGerais)): ?>
                    
                    <!-- ALERTA DE FILTRO APLICADO -->
                    <div class="alert alert-info alert-dismissible fade show mt-3" role="alert">
                        <i class="fa-solid fa-filter me-2"></i>
                        <strong>Filtro Aplicado:</strong> 
                        Centro de Custo: <strong><?php echo htmlspecialchars($ccustoSelecionado); ?></strong> | 
                        Semana: <strong><?php echo date('d/m/Y', strtotime($usegunda)); ?> a <?php echo date('d/m/Y', strtotime($usegunda . ' +6 days')); ?></strong>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>

                    <!-- Cards de Totais -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                    <h6 class="text-muted">Funcionários</h6>
                                    <h3><?php echo count($totaisGerais); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fas fa-road fa-2x text-success mb-2"></i>
                                    <h6 class="text-muted">Total Geral</h6>
                                    <h3><?php echo $totalGeralFormatado; ?> km</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-line fa-2x text-info mb-2"></i>
                                    <h6 class="text-muted">Média Geral</h6>
                                    <h3><?php echo $mediaGeralFormatada; ?> km</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela de Resumo com DataTables -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-table me-2"></i>
                                <strong>Resumo por Funcionário</strong>
                            </span>
                            <span class="badge bg-light text-dark">
                                <?php echo count($totaisGerais); ?> funcionários
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover table-header-gray" id="tabelaRelatorio">
                                    <thead>
                                        <tr>
                                            <th class="text-center">#</th>
                                            <th>Matrícula</th>
                                            <th>Nome do Funcionário</th>
                                            <th class="text-center">Total (km)</th>
                                            <th class="text-center">Média* (km)</th>
                                            <th class="text-center no-print">Detalhes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $posicao = 1;
                                        foreach ($totaisGerais as $func): 
                                            $totalFunc = number_format($func['total'], 1, ',', '.');
                                            $mediaFunc = number_format($func['media'], 1, ',', '.');
                                        ?>
                                            <tr>
                                                <td class="text-center"><strong><?php echo $posicao; ?></strong></td>
                                                <td><?php echo htmlspecialchars($func['matricula']); ?></td>
                                                <td><?php echo htmlspecialchars($func['nome']); ?></td>
                                                <td class="text-center">
                                                    <strong><?php echo $totalFunc; ?></strong>
                                                </td>
                                                <td class="text-center">
                                                    <strong><?php echo $mediaFunc; ?></strong>
                                                </td>
                                                <td class="text-center no-print">
                                                    <a href="view_deslocamento.php?mattec=<?php echo urlencode($func['matricula']); ?>&diai=<?php echo urlencode($dataSelecionada); ?>" 
                                                       target="_blank" class="btn btn-sm btn-primary" 
                                                       title="Ver rotas detalhadas do funcionário">
                                                        <i class="fas fa-external-link-alt me-1"></i> Ver Rotas
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php 
                                            $posicao++;
                                        endforeach; 
                                        ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-end fw-bold">RESUMO GERAL</td>
                                            <td class="text-center fw-bold">
                                                <?php echo $totalGeralFormatado; ?>
                                            </td>
                                            <td class="text-center fw-bold">
                                                <?php echo $mediaGeralFormatada; ?>
                                            </td>
                                            <td class="no-print"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                <?php elseif ($mostrarRelatorio && empty($totaisGerais)): ?>
                    
                    <div class="alert alert-warning mt-4">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Nenhum funcionário encontrado</strong> para o centro de custo <strong><?php echo htmlspecialchars($ccustoSelecionado); ?></strong> 
                        no período selecionado.
                    </div>

                <?php endif; ?>
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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="../src/js/scripts.js"></script>

    <script>
    $(document).ready(function () {
        // Inicializar Select2
        $('.select2-ccusto').select2({
            placeholder: 'Digite para buscar o Centro de Custo',
            allowClear: true,
            width: '100%'
        });

        // Validar formulário
        $('#formFiltro').on('submit', function(e) {
            var semana = $('#diai').val();
            var ccusto = $('#ccusto').val();
            
            if (!semana || semana === '') {
                e.preventDefault();
                alert('Por favor, selecione uma semana.');
                $('#diai').focus();
                return false;
            }
            
            if (!ccusto || ccusto === '') {
                e.preventDefault();
                alert('Por favor, selecione um Centro de Custo.');
                $('#ccusto').focus();
                return false;
            }
            
            var btn = $('#btnFiltrar');
            btn.html('<i class="fas fa-spinner fa-spin me-2"></i> Carregando...');
            btn.prop('disabled', true);
            
            return true;
        });

        // Função para exportar para Excel
        $('#btnExportarExcel').on('click', function() {
            var periodo = $('#diai').val();
            var ccusto = $('#ccusto').val();
            
            if (!periodo || periodo === '') {
                alert('Por favor, selecione uma semana antes de exportar.');
                $('#diai').focus();
                return;
            }
            
            if (!ccusto || ccusto === '') {
                alert('Por favor, selecione um Centro de Custo antes de exportar.');
                $('#ccusto').focus();
                return;
            }
            
            var btn = $(this);
            var textoOriginal = btn.html();
            btn.html('<i class="fa-solid fa-spinner fa-spin"></i> Gerando...');
            btn.prop('disabled', true);
            
            // Redirecionar para o script de exportação
            window.location.href = 'exportar_relatorio_excel.php?periodo=' + periodo + '&ccusto=' + encodeURIComponent(ccusto);
            
            setTimeout(function() {
                btn.html(textoOriginal);
                btn.prop('disabled', false);
            }, 3000);
        });

        <?php if ($mostrarRelatorio && !empty($totaisGerais)): ?>
        // Inicializar DataTables
        try {
            $('#tabelaRelatorio').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
                },
                pageLength: 15,
                lengthMenu: [[10, 15, 25, 50, -1], [10, 15, 25, 50, "Todos"]],
                order: [[0, 'asc']],
                columnDefs: [
                    { orderable: false, targets: [5] } // Desativa ordenação na coluna de ações
                ],
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn btn-sm btn-success'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn btn-sm btn-danger'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Imprimir',
                        className: 'btn btn-sm btn-info'
                    }
                ],
                dom: '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                autoWidth: false,
                destroy: true
            });
            console.log('DataTables inicializado com sucesso!');
        } catch (e) {
            console.error('Erro ao inicializar DataTables:', e);
            $('#tabelaRelatorio').addClass('table-striped');
        }
        <?php endif; ?>
    });
    </script>
</body>
</html>
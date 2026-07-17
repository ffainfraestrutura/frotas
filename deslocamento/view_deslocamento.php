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
// RECEBER PARÂMETROS VIA GET
// ==============================================
$mattec = isset($_GET["mattec"]) ? $_GET["mattec"] : "";
$dataSelecionada = isset($_GET["diai"]) ? $_GET["diai"] : "";

// ==============================================
// CONSULTAS DO RELATÓRIO
// ==============================================

$dadosRelatorio = array();
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
$nomeFuncionario = '';
$ccustoFuncionario = '';
$totalGeralKM = 0;

if (!empty($mattec) && !empty($dataSelecionada)) {
    // Calcular a semana
    $data_referencia = new DateTime($dataSelecionada);
    if ($data_referencia->format('N') == 1) {
        $usegunda = $data_referencia->format('Y-m-d');
    } else {
        $data_referencia->modify('last monday');
        $usegunda = $data_referencia->format('Y-m-d');
    }

    // Buscar dados do funcionário
    $sqlFuncionario = "SELECT nome, ccusto FROM bdcorp.tbfuncionario WHERE matricula = '$mattec'";
    $resultadoFuncionario = mysqli_query($conn, $sqlFuncionario) or die(mysqli_error($conn));
    $rowFuncionario = mysqli_fetch_array($resultadoFuncionario);

    if ($rowFuncionario) {
        $nomeFuncionario = $rowFuncionario[0];
        $ccustoFuncionario = $rowFuncionario[1];

        // Determinar tabela baseada no centro de custo
        if (strpos($ccustoFuncionario, 'TIM') !== false) {
            $tabela = "tbosrotalatlong";
            $id = 'idtbosrotalatlong';
        } elseif (strpos($ccustoFuncionario, 'CLARO') !== false) {
            $tabela = "tbosrotalatlong";
            $id = 'idtbosrotalatlong';
        } elseif (strpos($ccustoFuncionario, 'LIGGA') !== false) {
            $tabela = "tbosrotalatlong";
            $id = 'idtbosrotalatlong';
        } elseif (strpos($ccustoFuncionario, 'IHS') !== false) {
            $tabela = "tbosrotalatlong";
            $id = 'idtbosrotalatlong';
        } else {
            $tabela = "tbosrotalatlong";
            $id = 'idtbosrotalatlong';
        }

        // Data inicial e final
        $dataInicial = new DateTime($usegunda);
        $dataInicial->modify('next sunday');
        $pdomingo = $dataInicial->format('Y-m-d');
        $dataInicial = new DateTime($usegunda);
        $dataFinal = new DateTime($pdomingo);

        // Processar cada dia da semana
        while ($dataInicial <= $dataFinal) {
            $dataDia = $dataInicial->format('Y-m-d');
            $timestamp = strtotime($dataDia);
            $diaSemanaNumero = date('N', $timestamp);
            $nomeDiaSemana = $nomesDiasDaSemana[$diaSemanaNumero];

            // Buscar rotas do dia
            $sqlRotas = "SELECT endereco, bairro, cidade, data, eta, numero_de_ordem, 
                                ROUND(kmrota,1), motivo, foratoa, latitude, longitude, $id 
                         FROM $tabela 
                         WHERE matricula = '$mattec' 
                         AND data = '$dataDia' 
                         ORDER BY data, eta";
            $resultadoRotas = mysqli_query($conn, $sqlRotas) or die(mysqli_error($conn));

            $rotasDia = array();
            while ($rowRota = mysqli_fetch_array($resultadoRotas)) {
                $endereco = $rowRota[0];
                $bairro = $rowRota[1];
                $cidade = $rowRota[2];
                $data = $rowRota[3];
                $datac = explode(" ", $data);
                $datat = $datac[0];
                $eta = $rowRota[4];
                $os = $rowRota[5];
                if ($os == '') {
                    $os = $rowRota[7];
                }
                $kmrota = $rowRota[6];
                if ($kmrota == '') {
                    $kmrota = 0;
                }
                $foratoa = $rowRota[8];
                if ($foratoa == 1) {
                    $origem = 'MANUAL';
                } elseif ($os == 'CASA') {
                    $origem = 'ANIEL';
                } else {
                    $origem = 'TOA';
                }
                $latitude = $rowRota[9];
                $longitude = $rowRota[10];
                $idtbos = $rowRota[11];

                $endcomp = "$endereco $bairro $cidade";
                if (!mb_detect_encoding($endcomp, 'UTF-8', true)) {
                    $endcomp = utf8_encode($endcomp);
                }

                $rotasDia[] = array(
                    'endereco' => $endcomp,
                    'data' => $datat,
                    'eta' => $eta,
                    'os' => $os,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'origem' => $origem,
                    'km' => floatval($kmrota)
                );
            }

            // Buscar KM rodados do dia
            $sqlKm = "SELECT ROUND(kmsrodados,1) FROM tbkmrodadosdia WHERE matricula = '$mattec' AND dia = '$dataDia'";
            $resultadoKm = mysqli_query($conn, $sqlKm) or die(mysqli_error($conn));
            $rowKm = mysqli_fetch_array($resultadoKm);
            $kmsrodados = $rowKm[0] ?? 0;

            // Buscar edições de KM
            $sqlEdit = "SELECT kmsedit, posneg, matop, motivo FROM tbkmdiarioedit WHERE matricula = '$mattec' AND dataedit = '$dataDia'";
            $resultadoEdit = mysqli_query($conn, $sqlEdit) or die(mysqli_error($conn));
            $editados = array();
            if (mysqli_num_rows($resultadoEdit) >= 1) {
                while ($rowEdit = mysqli_fetch_array($resultadoEdit)) {
                    $kmsedit = $rowEdit[0];
                    $posneg = $rowEdit[1];
                    if ($posneg == 1) {
                        $sinal = '-';
                        $kmsrodados = $kmsrodados - $kmsedit;
                    } else {
                        $kmsrodados = $kmsrodados + $kmsedit;
                        $sinal = '+';
                    }
                    $editados[] = array(
                        'matop' => $rowEdit[2],
                        'motivo' => $rowEdit[3],
                        'sinal' => $sinal,
                        'kmsedit' => floatval($kmsedit)
                    );
                }
            }

            // Calcular total de KM do dia (soma das rotas)
            $totalKmDia = array_sum(array_column($rotasDia, 'km'));

            $dadosRelatorio[] = array(
                'data' => $dataDia,
                'data_formatada' => date('d/m/Y', strtotime($dataDia)),
                'nome_dia' => $nomeDiaSemana,
                'rotas' => $rotasDia,
                'kmsrodados' => floatval($kmsrodados),
                'total_km_rotas' => $totalKmDia,
                'editados' => $editados
            );

            $totalGeralKM += floatval($kmsrodados);
            $dataInicial->add(new DateInterval('P1D'));
        }
    }
}

// Calcular totais gerais
$totalRotas = 0;
foreach ($dadosRelatorio as $dia) {
    $totalRotas += count($dia['rotas']);
}

header("Content-type: text/html; charset=utf-8");
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="FFA" />
    <meta name="author" content="FFA" />
    <link rel="icon" type="image/png" href="src/images/favicon.png" />
    <title>Portal FFA - Detalhamento de Rotas</title>
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
    <link href="src/css/styles.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        /* ESTILO DO CABEÇALHO COM BACKGROUND SECONDARY */
        html {
            zoom: 0.8;
        }

        .header-secondary {
            background-color: #6c757d !important;
            border-radius: 10px;
            padding: 25px 30px;
            margin-bottom: 25px;
            margin-top: 25px;
            color: white;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }

        .header-secondary h1 {
            color: white;
            font-weight: 700;
        }

        .header-secondary .btn-voltar {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 8px 20px;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
        }

        .header-secondary .btn-voltar:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: scale(1.05);
        }

        .header-secondary .info-text {
            opacity: 0.9;
        }

        .header-secondary .info-text strong {
            color: white;
        }

        .card-header {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .badge-km {
            font-size: 14px;
            padding: 8px 15px;
        }

        .edit-row {
            background-color: #fff3cd;
        }

        .origem-manual {
            color: #dc3545;
            font-weight: 600;
        }

        .origem-toa {
            color: #0d6efd;
            font-weight: 600;
        }

        .origem-aniel {
            color: #198754;
            font-weight: 600;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            .header-secondary {
                background-color: #6c757d !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>

<body class="sb-nav-fixed">
    <!-- Menu -->
    <?php autofrotaMenu(); ?>

    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid px-4">
                <!-- Cabeçalho com background secondary -->
                <div class="header-secondary">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="h2 mb-2">
                                <i class="fas fa-route me-2"></i>Relatório de Deslocamento
                            </h1>
                            <p class="mb-0 info-text">
                                <i class="fas fa-user me-2"></i>
                                <?php if (!empty($nomeFuncionario)): ?>
                                    <strong><?php echo htmlspecialchars($nomeFuncionario); ?></strong>
                                    <span class="mx-2">|</span>
                                    <i class="fas fa-id-card me-1"></i>
                                    Matrícula: <?php echo htmlspecialchars($mattec); ?>
                                    <span class="mx-2">|</span>
                                    <i class="fas fa-building me-1"></i>
                                    <?php echo htmlspecialchars($ccustoFuncionario); ?>
                                <?php else: ?>
                                    <span class="text-warning">Funcionário não encontrado</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <a href="relatorio_deslocamento.php" class="btn-voltar">
                                <i class="fas fa-arrow-left me-2"></i>Voltar ao Relatório
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (!empty($mattec) && !empty($dataSelecionada) && !empty($dadosRelatorio)): ?>

                    <!-- Cards de Resumo -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="card shadow-sm h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-week fa-2x text-primary mb-2"></i>
                                    <h6 class="text-muted">Período</h6>
                                    <h5>
                                        <?php echo date('d/m/Y', strtotime($usegunda)); ?> a
                                        <?php echo date('d/m/Y', strtotime($usegunda . ' +6 days')); ?>
                                    </h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card shadow-sm h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-road fa-2x text-success mb-2"></i>
                                    <h6 class="text-muted">Total KM</h6>
                                    <h5 class="text-success"><?php echo number_format($totalGeralKM, 1, ',', '.'); ?> km
                                    </h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card shadow-sm h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-route fa-2x text-info mb-2"></i>
                                    <h6 class="text-muted">Total Rotas</h6>
                                    <h5 class="text-info"><?php echo $totalRotas; ?></h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card shadow-sm h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-day fa-2x text-warning mb-2"></i>
                                    <h6 class="text-muted">Dias com Rotas</h6>
                                    <h5 class="text-warning">
                                        <?php
                                        $diasComRotas = 0;
                                        foreach ($dadosRelatorio as $dia) {
                                            if (!empty($dia['rotas'])) {
                                                $diasComRotas++;
                                            }
                                        }
                                        echo $diasComRotas;
                                        ?>
                                    </h5>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detalhamento por Dia -->
                    <?php foreach ($dadosRelatorio as $dia): ?>
                        <div class="card shadow-sm mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-calendar-day me-2"></i>
                                    <strong><?php echo $dia['data_formatada']; ?></strong>
                                    <span class="badge bg-secondary ms-2"><?php echo $dia['nome_dia']; ?></span>
                                </div>
                                <div>
                                    <span class="badge bg-success badge-km">
                                        <i class="fas fa-road me-1"></i>
                                        Total: <?php echo number_format($dia['kmsrodados'], 1, ',', '.'); ?> km
                                    </span>
                                    <?php if (!empty($dia['rotas'])): ?>
                                        <span class="badge bg-info badge-km ms-2">
                                            <i class="fas fa-route me-1"></i>
                                            <?php echo count($dia['rotas']); ?> rotas
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($dia['rotas'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead>
                                                <tr>
                                                    <th style="width: 30%;">Endereço</th>
                                                    <th style="width: 10%;">Data</th>
                                                    <th style="width: 10%;">Hora</th>
                                                    <th style="width: 12%;">OS</th>
                                                    <th style="width: 10%;">Latitude</th>
                                                    <th style="width: 10%;">Longitude</th>
                                                    <th style="width: 10%;">Origem</th>
                                                    <th style="width: 8%;" class="text-end">KM</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dia['rotas'] as $rota):
                                                    $classeOrigem = '';
                                                    if ($rota['origem'] == 'MANUAL') {
                                                        $classeOrigem = 'origem-manual';
                                                    } elseif ($rota['origem'] == 'TOA') {
                                                        $classeOrigem = 'origem-toa';
                                                    } elseif ($rota['origem'] == 'ANIEL') {
                                                        $classeOrigem = 'origem-aniel';
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($rota['endereco']); ?></td>
                                                        <td><?php echo $rota['data']; ?></td>
                                                        <td><?php echo $rota['eta']; ?></td>
                                                        <td>
                                                            <?php if (!empty($rota['os']) && $rota['os'] != 'CASA'): ?>
                                                                <span
                                                                    class="badge bg-secondary"><?php echo htmlspecialchars($rota['os']); ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo $rota['latitude']; ?></td>
                                                        <td><?php echo $rota['longitude']; ?></td>
                                                        <td class="<?php echo $classeOrigem; ?>">
                                                            <?php echo $rota['origem']; ?>
                                                        </td>
                                                        <td class="text-end">
                                                            <strong><?php echo number_format($rota['km'], 1, ',', '.'); ?></strong>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr class="table-secondary">
                                                    <td colspan="7" class="text-end fw-bold">Total do Dia:</td>
                                                    <td class="text-end fw-bold">
                                                        <?php echo number_format($dia['total_km_rotas'], 1, ',', '.'); ?>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-secondary mb-0">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Nenhuma rota registrada para este dia.
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($dia['editados'])): ?>
                                    <div class="mt-3">
                                        <h6><i class="fas fa-edit me-2 text-warning"></i>Edições de KM</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered edit-row">
                                                <thead>
                                                    <tr>
                                                        <th>Operador</th>
                                                        <th>Motivo</th>
                                                        <th class="text-end">Ajuste</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($dia['editados'] as $edit): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($edit['matop']); ?></td>
                                                            <td><?php echo htmlspecialchars($edit['motivo']); ?></td>
                                                            <td class="text-end">
                                                                <strong><?php echo $edit['sinal']; ?>
                                                                    <?php echo number_format($edit['kmsedit'], 1, ',', '.'); ?></strong>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                <?php elseif (!empty($mattec) && !empty($dataSelecionada) && empty($dadosRelatorio)): ?>

                    <div class="alert alert-warning mt-4">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Nenhum dado encontrado</strong> para o funcionário
                        <strong><?php echo htmlspecialchars($mattec); ?></strong>
                        no período selecionado.
                    </div>

                <?php else: ?>

                    <div class="alert alert-danger mt-4">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Parâmetros inválidos.</strong> Por favor, selecione um funcionário e uma data válida.
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

    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>
    <script src="src/js/scripts.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js" crossorigin="anonymous"></script>
    <script src="src/js/simple-datatable.js" crossorigin="anonymous"></script>
    <script src="src/js/datatables-simple-demo.js"></script>
</body>

</html>
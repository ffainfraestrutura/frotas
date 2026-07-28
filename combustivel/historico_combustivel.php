<?php
date_default_timezone_set('America/Sao_Paulo');
session_start();
$nome = $_SESSION['nome'];
$usuariof = $_SESSION['usuario'];
$matricula = $_SESSION['matricula'];
$perfil = $_SESSION['perfil'];
$_SESSION['perfil'] = $perfil;
$_SESSION['matricula'] = $matricula;
$_SESSION['nome'] = $nome;
$_SESSION['usuario'] = $usuariof;
require_once '../control/conecta.php';
require_once '../includes/autofrota_common.php';
error_reporting(1);
ini_set('display_errors', 1);

// Pega o filtro de colaborador (se tiver)
$filtro_matricula = isset($_GET['matricula']) ? $_GET['matricula'] : '';

// Busca lista de colaboradores que já fizeram remanejamento
$sql_colaboradores = "SELECT DISTINCT 
                        h.matricula,
                        u.nome
                      FROM 
                        historico_combustivel h
                      LEFT JOIN tbusuario u ON h.matricula = u.matricula
                      WHERE 
                        h.acao IN ('remanejamento', 'cota_extra')
                      ORDER BY 
                        u.nome ASC";

if ($perfil == 4) {
    // Perfil 4 - Visualiza todos os colaboradores
    $sql_colaboradores = "SELECT DISTINCT 
                            f.matricula,
                            f.nome
                        FROM 
                            bdcorp.tbusuario u
                            JOIN bdcorp.tbfuncionario f ON u.matricula = f.matricula
                        WHERE 
                            u.perfil = 0
                            AND f.status != 'demitido'
                        ORDER BY 
                            f.nome ASC";

    $result_colaboradores = mysqli_query($conn, $sql_colaboradores);
    $colaboradores = mysqli_fetch_all($result_colaboradores, MYSQLI_ASSOC);
} elseif ($perfil == 2) {
    // Perfil 2 - Coordenador - Busca apenas técnicos vinculados a ele
    $sql_colaboradores = "SELECT DISTINCT
                            u.matricula, u.nome
                        FROM
                            bdcorp.tbcoord coord
                                JOIN
                            bdcorp.tbsupervisor sup ON sup.idtbcoordenador = coord.idtbcoordenador
                                JOIN
                            bdcorp.tbusuario u ON sup.idtbsupervisor = u.idtbsupervisor
                                JOIN
                            tbsaldo sal ON sal.matricula = u.matricula
                                JOIN
                            bdcorp.tbfuncionario tec ON u.matricula = tec.matricula
                        WHERE
                            coord.matricula = ?
                                AND tec.status != 'demitido'
                                AND u.perfil = 0 
                        UNION SELECT DISTINCT
                            u.matricula, u.nome
                        FROM
                            bdcorp.tbusuario u
                        WHERE
                            u.matricula = ?
                        ORDER BY nome ASC";

    $stmt = mysqli_prepare($conn, $sql_colaboradores);
    mysqli_stmt_bind_param($stmt, 'ss', $_SESSION['matricula'], $_SESSION['matricula']);
    mysqli_stmt_execute($stmt);
    $result_colaboradores = mysqli_stmt_get_result($stmt);
    $colaboradores = mysqli_fetch_all($result_colaboradores, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} elseif ($perfil == 3) {
    // Perfil 3 - Gerente - Busca técnicos de todos os coordenadores abaixo dele
    $sql_colaboradores = "SELECT DISTINCT 
                            u.matricula,
                            u.nome,
                            'Técnico' AS tipo
                        FROM 
                            bdcorp.tbgerente ger
                            JOIN bdcorp.tbcoord coord ON coord.idtbgerente = ger.idtbgerente
                            JOIN bdcorp.tbsupervisor sup ON sup.idtbcoordenador = coord.idtbcoordenador
                            JOIN bdcorp.tbusuario u ON sup.idtbsupervisor = u.idtbsupervisor
                            JOIN tbsaldo sal ON sal.matricula = u.matricula
                            JOIN bdcorp.tbfuncionario tec ON u.matricula = tec.matricula
                        WHERE 
                            CAST(ger.matricula AS CHAR) = ?
                            AND tec.status != 'demitido'
                            AND u.perfil = 0

                        UNION

                        -- Coordenadores do gerente
                        SELECT DISTINCT 
                            u.matricula,
                            u.nome,
                            'Coordenador' AS tipo
                        FROM 
                            bdcorp.tbgerente ger
                            JOIN bdcorp.tbcoord coord ON coord.idtbgerente = ger.idtbgerente
                            JOIN bdcorp.tbusuario u ON coord.matricula = u.matricula
                        WHERE 
                            CAST(ger.matricula AS CHAR) = ?
                            AND u.perfil = 2

                        UNION

                        -- O próprio gerente (garantindo que seja APENAS o 4901)
                        SELECT DISTINCT 
                            u.matricula,
                            u.nome,
                            'Gerente' AS tipo
                        FROM 
                            bdcorp.tbusuario u
                        WHERE 
                            CAST(u.matricula AS CHAR) = ?

                        ORDER BY 
                            tipo DESC, nome ASC;";

    $stmt = mysqli_prepare($conn, $sql_colaboradores);
    mysqli_stmt_bind_param($stmt, 'sss', $_SESSION['matricula'], $_SESSION['matricula'], $_SESSION['matricula']);
    mysqli_stmt_execute($stmt);
    $result_colaboradores = mysqli_stmt_get_result($stmt);
    $colaboradores = mysqli_fetch_all($result_colaboradores, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} elseif ($perfil == 10) {
    // Perfil 10 - Diretor - Busca todos os técnicos (visão completa)
    $sql_colaboradores = "SELECT DISTINCT 
                            u.matricula,
                            u.nome
                        FROM 
                            bdcorp.tbdiretor dir
                            JOIN bdcorp.tbgerente ger ON ger.idtbdiretor = dir.id
                            JOIN bdcorp.tbcoord coord ON coord.idtbgerente = ger.idtbgerente
                            JOIN bdcorp.tbsupervisor sup ON sup.idtbcoordenador = coord.idtbcoordenador
                            JOIN bdcorp.tbusuario u ON sup.idtbsupervisor = u.idtbsupervisor
                            JOIN tbsaldo sal ON sal.matricula = u.matricula
                            JOIN bdcorp.tbfuncionario tec ON u.matricula = tec.matricula
                        WHERE 
                            dir.matricula = ?
                            AND tec.status != 'demitido'
                            AND u.perfil = 0

                        UNION

                        -- Gerentes abaixo do diretor
                        SELECT DISTINCT 
                            u.matricula,
                            u.nome
                        FROM 
                            bdcorp.tbdiretor dir
                            JOIN bdcorp.tbgerente ger ON ger.idtbdiretor = dir.id
                            JOIN bdcorp.tbusuario u ON ger.matricula = u.matricula
                        WHERE 
                            dir.matricula = ?
                            AND u.perfil = 3

                        UNION

                        -- Coordenadores abaixo do diretor
                        SELECT DISTINCT 
                            u.matricula,
                            u.nome
                        FROM 
                            bdcorp.tbdiretor dir
                            JOIN bdcorp.tbgerente ger ON ger.idtbdiretor = dir.id
                            JOIN bdcorp.tbcoord coord ON coord.idtbgerente = ger.idtbgerente
                            JOIN bdcorp.tbusuario u ON coord.matricula = u.matricula
                        WHERE 
                            dir.matricula = ?
                            AND u.perfil = 2

                        UNION

                        -- O próprio diretor
                        SELECT DISTINCT 
                            u.matricula,
                            u.nome
                        FROM 
                            bdcorp.tbusuario u
                        WHERE 
                            u.matricula = ?

                        ORDER BY 
                            nome ASC";

    $stmt = mysqli_prepare($conn, $sql_colaboradores);
    mysqli_stmt_bind_param($stmt, 'ssss', $_SESSION['matricula'], $_SESSION['matricula'], $_SESSION['matricula'], $_SESSION['matricula']);
    mysqli_stmt_execute($stmt);
    $result_colaboradores = mysqli_stmt_get_result($stmt);
    $colaboradores = mysqli_fetch_all($result_colaboradores, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// ==============================================
// BUSCA DADOS DO COLABORADOR E SALDO INICIAL
// ==============================================
$dados_colaborador = null;
$saldo_inicial_data = null;
$historico = [];

if (!empty($filtro_matricula)) {

    $sql_colab = "SELECT 
                    u.matricula,
                    u.nome,
                    u.perfil,
                    s.saldo as saldo_atual,
                    s.kmorcsem as kmproj,
                    s.data as ultima_atualizacao
                  FROM 
                    bdcorp.tbusuario u
                  LEFT JOIN tbsaldo s ON u.matricula = s.matricula
                  WHERE 
                    u.matricula = ?
                  ORDER BY 
                    s.data DESC
                  LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql_colab);
    mysqli_stmt_bind_param($stmt, 's', $filtro_matricula);
    mysqli_stmt_execute($stmt);
    $result_colab = mysqli_stmt_get_result($stmt);
    $dados_colaborador = mysqli_fetch_assoc($result_colab);
    mysqli_stmt_close($stmt);

    // Busca o saldo inicial do colaborador (primeiro registro da tbsaldo)
    $sql_saldo_inicial = "SELECT 
                            s.valoraplicado as saldo_inicial,
                            s.data as data_saldo
                          FROM 
                            tbsaldo s
                          WHERE 
                            s.matricula = ?
                          ORDER BY 
                            s.data DESC
                          LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql_saldo_inicial);
    mysqli_stmt_bind_param($stmt, 's', $filtro_matricula);
    mysqli_stmt_execute($stmt);
    $result_inicial = mysqli_stmt_get_result($stmt);
    $saldo_inicial_data = mysqli_fetch_assoc($result_inicial);
    mysqli_stmt_close($stmt);

    // Busca o histórico do colaborador
    $sql_historico = "SELECT 
                        h.id,
                        h.valor,
                        h.matricula,
                        h.operacao,
                        h.matricula_autor,
                        h.valor_anterior,
                        h.valor_atual,
                        h.acao,
                        h.data,
                        u.nome as nome_colaborador,
                        ua.nome as nome_autor
                      FROM 
                        historico_combustivel h
                      LEFT JOIN bdcorp.tbusuario u ON h.matricula = u.matricula
                      LEFT JOIN bdcorp.tbusuario ua ON h.matricula_autor = ua.matricula
                      WHERE 
                        h.matricula = ?
                      ORDER BY 
                        h.data ASC, h.id ASC";

    $stmt = mysqli_prepare($conn, $sql_historico);
    mysqli_stmt_bind_param($stmt, 's', $filtro_matricula);
    mysqli_stmt_execute($stmt);
    $result_historico = mysqli_stmt_get_result($stmt);
    $historico = mysqli_fetch_all($result_historico, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// ==============================================
// CALCULA O SALDO ACUMULADO (USADO EM TODA A PÁGINA)
// ==============================================
$saldo_acumulado = 0;
if ($saldo_inicial_data) {

    $saldo_acumulado = floatval($saldo_inicial_data['saldo_inicial'] ?? 0);
} else {

    $dados_tabela = match ($dados_colaborador['perfil']) {
        2 => 'tbcoord',
        3 => 'tbgerente',
        10 => 'tbdiretor',
        default => 'tbusuario'
    };

    $sql = "SELECT * FROM bdcorp.$dados_tabela WHERE matricula = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $filtro_matricula);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $dados = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);


    $saldo_acumulado = floatval($dados['saldoinicial'] ?? 0);
    $saldo_inicial_data['saldo_inicial'] = $dados['saldoinicial'];
}


if (count($historico) > 0) {
    foreach ($historico as $h) {
        if ($h['operacao'] == 'adição') {
            $saldo_acumulado += floatval($h['valor']);
        } else {
            $saldo_acumulado -= floatval($h['valor']);
        }

    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Histórico Financeiro por Colaborador - Portal FFA</title>
    <link rel="icon" type="image/png" href="../src/images/favicon.png" />
    <link href="../src/css/styles.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />

    <style>
        html {
            zoom: 0.8;
        }

        .badge-adicao {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-retirada {
            background-color: #f8d7da;
            color: #721c24;
        }

        .badge-remanejamento {
            background-color: #cce5ff;
            color: #004085;
        }

        .badge-outro {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .badge-inicial {
            background-color: #fff3cd;
            color: #856404;
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 20px;
            padding-left: 15px;
            border-left: 3px solid #dee2e6;
            padding-top: 5px;
            padding-bottom: 5px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -10px;
            top: 8px;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background: #007bff;
            border: 2px solid white;
            box-shadow: 0 0 0 2px #007bff;
        }

        .timeline-item.adicao::before {
            background: #28a745;
            box-shadow: 0 0 0 2px #28a745;
        }

        .timeline-item.retirada::before {
            background: #dc3545;
            box-shadow: 0 0 0 2px #dc3545;
        }

        .timeline-item.remanejamento::before {
            background: #ffc107;
            box-shadow: 0 0 0 2px #ffc107;
        }

        .timeline-item.inicial::before {
            background: #6f42c1;
            box-shadow: 0 0 0 2px #6f42c1;
        }

        .timeline-item.inicial {
            border-left-color: #6f42c1;
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 10px 15px;
        }

        .card-resumo {
            transition: transform 0.2s;
        }

        .card-resumo:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .filtros {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .valor-positivo {
            color: #28a745;
            font-weight: bold;
        }

        .valor-negativo {
            color: #dc3545;
            font-weight: bold;
        }

        .info-colaborador {
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .card-selecione {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            padding: 40px;
            text-align: center;
            border-radius: 10px;
        }

        .card-selecione i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 15px;
        }

        .card-selecione h4 {
            color: #495057;
        }

        .card-selecione p {
            color: #6c757d;
        }

        .sem-historico {
            padding: 20px;
            text-align: center;
        }

        .sem-historico i {
            font-size: 2rem;
            color: #6c757d;
        }

        .sem-historico h5 {
            color: #6c757d;
        }


        /* Estilos para melhor visualização no celular */
        @media (max-width: 576px) {
            .info-colaborador {
                padding: 15px !important;
            }

            .info-colaborador h4 {
                font-size: 1.1rem;
            }

            .info-colaborador h5 {
                font-size: 0.9rem;
            }

            .info-colaborador .h3 {
                font-size: 1.5rem;
            }

            .info-colaborador .small {
                font-size: 0.7rem;
            }

            .info-colaborador .row>[class*="col-"] {
                padding: 5px 0;
            }

            /* Divisórias visuais entre as colunas no mobile */
            .info-colaborador .row>.col-6:first-child {
                border-bottom: 1px solid rgba(255, 255, 255, 0.2);
                padding-bottom: 10px;
            }

            .info-colaborador .row>.col-6:last-child {
                padding-top: 10px;
            }
        }

        @media (min-width: 577px) and (max-width: 768px) {
            .info-colaborador .h3 {
                font-size: 1.8rem;
            }

            .info-colaborador h4 {
                font-size: 1.2rem;
            }
        }

        @media (min-width: 769px) {
            .info-colaborador .h3 {
                font-size: 2.2rem;
            }
        }
    </style>
</head>

<body class="sb-nav-fixed">
    <?php autofrotaMenu(); ?>

    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid px-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h1 class="mt-4 mb-1">
                            <i class="fas fa-history me-2"></i>Histórico Financeiro por Colaborador
                        </h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item active">Acompanhe todas as transferências de saldo</li>
                        </ol>
                    </div>
                    <a href="retirada/" class="btn btn-outline-secondary mt-4">
                        <i class="fas fa-arrow-left me-2"></i>Voltar
                    </a>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_GET['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (empty($filtro_matricula)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-filter me-1"></i>
                            Filtrar Histórico
                        </div>
                        <div class="card-body">
                            <form method="GET" action="">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Selecione um Colaborador</label>
                                        <input type="search" id="busca-colaborador" class="form-control"
                                            list="lista-colaboradores" placeholder="Digite o nome ou a matrícula" autocomplete="off">
                                        <input type="hidden" name="matricula" id="matricula-colaborador">
                                        <datalist id="lista-colaboradores">
                                            <?php foreach ($colaboradores as $colab): ?>
                                                <option data-matricula="<?= htmlspecialchars($colab['matricula']) ?>"
                                                    value="<?= htmlspecialchars($colab['nome'] ?? $colab['matricula']) ?> (<?= htmlspecialchars($colab['matricula']) ?>)">
                                            <?php endforeach; ?>
                                        </datalist>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ============================================== -->
                <!-- SÓ MOSTRA O CONTEÚDO SE UM COLABORADOR FOR SELECIONADO -->
                <!-- ============================================== -->
                <?php if (!empty($filtro_matricula)): ?>

                    <?php if ($dados_colaborador): ?>
                        <!-- Info do Colaborador -->
                        <div class="info-colaborador bg-secondary p-3 p-md-4 mb-4">
                            <!-- Desktop: Layout em 3 colunas -->
                            <div class="row d-none d-md-flex">
                                <div class="col-md-4">
                                    <h4><i
                                            class="fas fa-user me-2"></i><?= htmlspecialchars($dados_colaborador['nome'] ?? $filtro_matricula) ?>
                                    </h4>
                                    <p class="mb-0"><i class="fas fa-id-card me-2"></i>Matrícula:
                                        <?= $dados_colaborador['matricula'] ?>
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <h5><i class="fas fa-wallet me-2"></i>Saldo Atual</h5>
                                    <p class="h3">R$ <?= number_format($saldo_acumulado, 2, ',', '.') ?></p>
                                    <?php if ($dados_colaborador['kmproj']): ?>
                                        <small><i class="fas fa-road me-1"></i>KM Projetado:
                                            <?= number_format($dados_colaborador['kmproj'], 0, ',', '.') ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <h5><i class="fas fa-calendar me-2"></i>Última Atualização</h5>
                                    <p><?= $dados_colaborador['ultima_atualizacao'] ? date('d/m/Y H:i', strtotime($dados_colaborador['ultima_atualizacao'])) : '-' ?>
                                    </p>
                                    <?php if ($saldo_inicial_data): ?>
                                        <small><i class="fas fa-flag me-1"></i>Saldo Inicial: R$
                                            <?= number_format($saldo_inicial_data['saldo_inicial'] ?? 0, 2, ',', '.') ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Mobile: Layout empilhado -->
                            <div class="d-md-none">
                                <!-- Nome e Matrícula -->
                                <div class="text-center mb-2 pb-2 border-bottom border-light">
                                    <h5 class="mb-0">
                                        <i class="fas fa-user me-1"></i>
                                        <?= htmlspecialchars($dados_colaborador['nome'] ?? $filtro_matricula) ?>
                                    </h5>
                                    <small class="text-light">
                                        <i class="fas fa-id-card me-1"></i>Matrícula: <?= $dados_colaborador['matricula'] ?>
                                    </small>
                                </div>

                                <!-- Saldo Atual -->
                                <div class="text-center mb-2 pb-2 border-bottom border-light">
                                    <div class="d-flex justify-content-center align-items-center gap-2">
                                        <i class="fas fa-wallet"></i>
                                        <span class="small">Saldo Atual:</span>
                                        <strong class="h5 mb-0">R$ <?= number_format($saldo_acumulado, 2, ',', '.') ?></strong>
                                    </div>
                                    <?php if ($dados_colaborador['kmproj']): ?>
                                        <small class="d-block mt-1 text-light">
                                            <i class="fas fa-road me-1"></i>KM Projetado:
                                            <?= number_format($dados_colaborador['kmproj'], 0, ',', '.') ?>
                                        </small>
                                    <?php endif; ?>
                                </div>

                                <!-- Última Atualização e Saldo Inicial -->
                                <div class="text-center">
                                    <div class="d-flex justify-content-center align-items-center gap-2">
                                        <i class="fas fa-calendar"></i>
                                        <span class="small">Última Atualização:</span>
                                        <small><?= $dados_colaborador['ultima_atualizacao'] ? date('d/m/Y H:i', strtotime($dados_colaborador['ultima_atualizacao'])) : '-' ?></small>
                                    </div>
                                    <?php if ($saldo_inicial_data): ?>
                                        <small class="d-block mt-1 text-light">
                                            <i class="fas fa-flag me-1"></i>Saldo Inicial: <strong>R$
                                                <?= number_format($saldo_inicial_data['saldo_inicial'] ?? 0, 2, ',', '.') ?></strong>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Timeline / Linha do Tempo -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-clock me-1"></i>
                            Linha do Tempo
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php if ($saldo_inicial_data): ?>
                                    <div class="timeline-item inicial">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <strong><?= date('d/m/Y', strtotime($saldo_inicial_data['data_saldo'] ?? 'now')) ?></strong>
                                            </div>
                                            <div class="col-md-3">
                                                <span class="badge badge-inicial">
                                                    <i class="fas fa-flag"></i> SALDO INICIAL
                                                </span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="text-primary fw-bold">
                                                    R$
                                                    <?= number_format($saldo_inicial_data['saldo_inicial'] ?? 0, 2, ',', '.') ?>
                                                </span>
                                            </div>
                                            <div class="col-md-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle"></i> Primeiro registro
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (count($historico) > 0): ?>
                                    <?php
                                    // Reinicia o saldo acumulado para a timeline (começa com o saldo inicial)
                                    $saldo_timeline = 0;
                                    if ($saldo_inicial_data) {
                                        $saldo_timeline = floatval($saldo_inicial_data['saldo_inicial'] ?? 0);
                                    }
                                    ?>

                                    <!-- Itens do histórico -->
                                    <?php foreach ($historico as $h):
                                        if ($h['operacao'] == 'adição') {
                                            $saldo_timeline += floatval($h['valor']);
                                        } else {
                                            $saldo_timeline -= floatval($h['valor']);
                                        }

                                        $classe_operacao = $h['operacao'] == 'adição' ? 'adição' : 'retirada';
                                        $icone = $h['operacao'] == 'adição' ? 'fa-arrow-down' : 'fa-arrow-up';
                                        $classe_valor = $h['operacao'] == 'adição' ? 'valor-positivo' : 'valor-negativo';
                                        $sinal = $h['operacao'] == 'adição' ? '+' : '-';
                                        ?>
                                        <div class="timeline-item <?= $classe_operacao ?>">
                                            <div class="row align-items-center">
                                                <div class="col-md-2">
                                                    <strong><?= date('d/m/Y H:i', strtotime($h['data'])) ?></strong>
                                                </div>
                                                <div class="col-md-3">
                                                    <span
                                                        class="badge <?= $h['operacao'] == 'adição' ? 'badge-adicao' : 'badge-retirada' ?>">
                                                        <i class="fas <?= $icone ?>"></i>
                                                        <?= ucfirst($h['operacao']) ?>
                                                    </span>
                                                    <span
                                                        class="badge <?= $h['acao'] == 'remanejamento' ? 'badge-remanejamento' : 'badge-outro' ?> ms-1">
                                                        <?= ucfirst($h['acao']) ?>
                                                    </span>
                                                </div>
                                                <div class="col-md-2">
                                                    <span class="<?= $classe_valor ?>">
                                                        <?= $sinal ?> R$ <?= number_format($h['valor'], 2, ',', '.') ?>
                                                    </span>
                                                </div>
                                                <div class="col-md-3">
                                                    <small>
                                                        R$ <?= number_format($h['valor_anterior'], 2, ',', '.') ?>
                                                        <i class="fas fa-arrow-right mx-1"></i>
                                                        <span class="text-primary">R$
                                                            <?= number_format($h['valor_atual'], 2, ',', '.') ?></span>
                                                    </small>
                                                </div>
                                                <div class="col-md-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?= htmlspecialchars($h['nome_autor'] ?? $h['matricula_autor']) ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <!-- Item: Saldo Final -->
                                    <div class="timeline-item" style="border-left-color: #28a745;">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <strong>Atual</strong>
                                            </div>
                                            <div class="col-md-3">
                                                <span class="badge badge-adicao">
                                                    <i class="fas fa-check-circle"></i> SALDO ATUAL
                                                </span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="text-primary fw-bold h5">
                                                    R$ <?= number_format($saldo_timeline, 2, ',', '.') ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                <?php else: ?>

                                    <!-- Item: Saldo Atual (igual ao inicial) -->
                                    <div class="timeline-item" style="border-left-color: #28a745;">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <strong>Atual</strong>
                                            </div>
                                            <div class="col-md-3">
                                                <span class="badge badge-adicao">
                                                    <i class="fas fa-check-circle"></i> SALDO ATUAL
                                                </span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="text-primary fw-bold h5">
                                                    R$ <?= number_format($saldo_acumulado, 2, ',', '.') ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php else: ?>

                    <!-- ============================================== -->
                    <!-- MENSAGEM QUANDO NENHUM COLABORADOR FOI SELECIONADO -->
                    <!-- ============================================== -->
                    <div class="card mb-4">
                        <div class="card-body card-selecione">
                            <i class="fas fa-search"></i>
                            <h4>Selecione um colaborador para ver o histórico</h4>
                            <p class="text-muted">Use o filtro acima para escolher um colaborador e visualizar o histórico
                                de transferências.</p>
                            <?php if (count($colaboradores) == 0): ?>
                                <div class="alert alert-warning mt-3">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Nenhum colaborador encontrado para o seu perfil.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php endif; ?>
                <!-- ============================================== -->
                <!-- FIM DA CONDICIONAL -->
                <!-- ============================================== -->

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="../src/js/scripts.js"></script>
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js"></script>

    <script>
        $('#busca-colaborador').on('input', function () {
            const opcao = $('#lista-colaboradores option').filter((_, item) => item.value === this.value).first();
            if (opcao.length) {
                $('#matricula-colaborador').val(opcao.attr('data-matricula'));
                this.form.submit();
            }
        });
        $(document).ready(function () {
            // Scroll para o timeline quando carregar
            if (window.location.hash) {
                $('html, body').animate({
                    scrollTop: $(window.location.hash).offset().top - 100
                }, 500);
            }
        });
    </script>
</body>

</html>
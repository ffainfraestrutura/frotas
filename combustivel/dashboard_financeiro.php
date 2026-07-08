<?php
date_default_timezone_set('America/Sao_Paulo');
session_start();
$nome = $_SESSION['nome'];
$usuariof = $_SESSION['usuario'];
$matricula = $_SESSION['matricula'];
$perfil = $_SESSION['perfil'];

require_once '../control/conecta.php';
require_once '../includes/autofrota_common.php';
error_reporting(1);
ini_set('display_errors', 1);

// ==============================================
// BUSCA DADOS DO DIRETOR E SUA EQUIPE
// ==============================================

// 1. Dados do diretor logado (incluindo cota fixa)
$sql_diretor = "SELECT 
                    u.matricula,
                    u.nome,
                    u.perfil,
                    d.valor as saldo_atual,
                    d.orcrecebido as orc_recebido,
                    COALESCE(cf.valor, 0) as cota_fixa
                FROM 
                    bdcorp.tbusuario u
                LEFT JOIN bdcorp.tbdiretor d ON u.matricula = d.matricula 
                LEFT JOIN tbcotafixa cf ON u.matricula COLLATE utf8mb4_unicode_ci = cf.matricula COLLATE utf8mb4_unicode_ci
                WHERE
                    u.matricula = ?
                ORDER BY 
                    d.ultimaatualizacao DESC
                LIMIT 1";

$stmt = mysqli_prepare($conn, $sql_diretor);
mysqli_stmt_bind_param($stmt, 's', $matricula);
mysqli_stmt_execute($stmt);
$result_diretor = mysqli_stmt_get_result($stmt);
$dados_diretor = mysqli_fetch_assoc($result_diretor);
mysqli_stmt_close($stmt);

// 2. Busca todos os gerentes abaixo do diretor (incluindo cota fixa)
$sql_gerentes = "SELECT 
                    DISTINCT u.matricula,
                    u.nome,
                    u.perfil,
                    g.valor as saldo_atual,
                    g.orcrecebido as orc_recebido,
                    COALESCE(cf.valor, 0) as cota_fixa,
                    g.idtbgerente as id_gerente
                FROM 
                    bdcorp.tbdiretor dir
                JOIN bdcorp.tbgerente g ON g.idtbdiretor = dir.id
                JOIN bdcorp.tbusuario u ON g.matricula = u.matricula
                JOIN bdcorp.tbfuncionario f ON u.matricula = f.matricula
                LEFT JOIN tbcotafixa cf ON u.matricula COLLATE utf8mb4_unicode_ci = cf.matricula COLLATE utf8mb4_unicode_ci
                WHERE 
                    dir.matricula = ?
                    AND f.status != 'demitido'
                ORDER BY 
                    u.nome ASC";

$stmt = mysqli_prepare($conn, $sql_gerentes);
mysqli_stmt_bind_param($stmt, 's', $matricula);
mysqli_stmt_execute($stmt);
$result_gerentes = mysqli_stmt_get_result($stmt);
$gerentes = mysqli_fetch_all($result_gerentes, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// 3. Busca todos os coordenadores abaixo do diretor (incluindo cota fixa)
$sql_coordenadores = "SELECT 
                        DISTINCT u.matricula,
                        u.nome,
                        u.perfil,
                        c.valor as saldo_atual,
                        c.orcrecebido as orc_recebido,
                        COALESCE(cf.valor, 0) as cota_fixa,
                        c.idtbcoordenador as id_coordenador
                    FROM 
                        bdcorp.tbdiretor dir
                    JOIN bdcorp.tbgerente g ON g.idtbdiretor = dir.id
                    JOIN bdcorp.tbcoord c ON c.idtbgerente = g.idtbgerente
                    JOIN bdcorp.tbusuario u ON c.matricula = u.matricula
                    JOIN bdcorp.tbfuncionario f ON u.matricula = f.matricula
                    LEFT JOIN tbcotafixa cf ON u.matricula COLLATE utf8mb4_unicode_ci = cf.matricula COLLATE utf8mb4_unicode_ci
                    WHERE 
                        dir.matricula = ?
                        AND f.status != 'demitido'
                    ORDER BY 
                        u.nome ASC";

$stmt = mysqli_prepare($conn, $sql_coordenadores);
mysqli_stmt_bind_param($stmt, 's', $matricula);
mysqli_stmt_execute($stmt);
$result_coordenadores = mysqli_stmt_get_result($stmt);
$coordenadores = mysqli_fetch_all($result_coordenadores, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// 4. Busca todos os supervisores abaixo do diretor
$sql_supervisores = "SELECT 
                        DISTINCT u.matricula,
                        u.nome,
                        u.perfil,
                        COALESCE(s.saldo, 150) as saldo_atual,
                        COALESCE(s.saldo, 150) as orc_recebido,
                        0 as cota_fixa
                    FROM 
                        bdcorp.tbdiretor dir
                    JOIN bdcorp.tbgerente g ON g.idtbdiretor = dir.id
                    JOIN bdcorp.tbcoord c ON c.idtbgerente = g.idtbgerente
                    JOIN bdcorp.tbsupervisor sup ON sup.idtbcoordenador = c.idtbcoordenador
                    JOIN bdcorp.tbusuario u ON sup.matricula = u.matricula
                    LEFT JOIN tbsaldo s ON u.matricula = s.matricula
                    JOIN bdcorp.tbfuncionario f ON u.matricula = f.matricula
                    WHERE 
                        dir.matricula = ?
                        AND u.perfil = 1
                        AND f.status != 'demitido'
                    ORDER BY 
                        u.nome";

$stmt = mysqli_prepare($conn, $sql_supervisores);
mysqli_stmt_bind_param($stmt, 's', $matricula);
mysqli_stmt_execute($stmt);
$result_supervisores = mysqli_stmt_get_result($stmt);
$supervisores = mysqli_fetch_all($result_supervisores, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// 5. Busca todos os técnicos abaixo do diretor
$sql_tecnicos = "SELECT 
                    DISTINCT u.matricula,
                    u.nome,
                    u.perfil,
                    s.orcsemanal as saldo_atual,
                    s.kmorcsem as kmproj,
                    s.data as ultima_atualizacao,
                    0 as cota_fixa
                FROM 
                    bdcorp.tbdiretor dir
                JOIN bdcorp.tbgerente g ON g.idtbdiretor = dir.id
                JOIN bdcorp.tbcoord c ON c.idtbgerente = g.idtbgerente
                JOIN bdcorp.tbsupervisor sup ON sup.idtbcoordenador = c.idtbcoordenador
                JOIN bdcorp.tbusuario u ON sup.idtbsupervisor = u.idtbsupervisor
                LEFT JOIN tbsaldo s ON u.matricula = s.matricula
                JOIN bdcorp.tbfuncionario f ON u.matricula = f.matricula
                WHERE 
                    dir.matricula = ?
                    AND u.perfil = 0
                    AND f.status != 'demitido'
                ORDER BY 
                    u.nome ASC";

$stmt = mysqli_prepare($conn, $sql_tecnicos);
mysqli_stmt_bind_param($stmt, 's', $matricula);
mysqli_stmt_execute($stmt);
$result_tecnicos = mysqli_stmt_get_result($stmt);
$tecnicos = mysqli_fetch_all($result_tecnicos, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// 6. Função para calcular totais da equipe (incluindo cota fixa)
// 6. Função para calcular totais da equipe (incluindo cota fixa)
function calcularTotaisEquipe($gerentes, $supervisores, $coordenadores, $tecnicos)
{
    $total_atual = 0;
    $total_enviado = 0;

    // Soma saldos dos gerentes (incluindo cota fixa)
    foreach ($gerentes as $gerente) {
        $saldo_com_cota = floatval($gerente['saldo_atual'] ?? 0) + floatval($gerente['cota_fixa'] ?? 0);
        $orc_com_cota = floatval($gerente['orc_recebido'] ?? 0) + floatval($gerente['cota_fixa'] ?? 0);
        $total_atual += $saldo_com_cota;
        $total_enviado += $orc_com_cota;
    }

    // Soma saldos dos supervisores
    foreach ($supervisores as $supervisor) {
        $total_atual += floatval($supervisor['saldo_atual'] ?? 0);
        $total_enviado += floatval($supervisor['orc_recebido'] ?? 0);
    }

    // Soma saldos dos coordenadores (incluindo cota fixa)
    foreach ($coordenadores as $coordenador) {
        $saldo_com_cota = floatval($coordenador['saldo_atual'] ?? 0) + floatval($coordenador['cota_fixa'] ?? 0);
        $orc_com_cota = floatval($coordenador['orc_recebido'] ?? 0) + floatval($coordenador['cota_fixa'] ?? 0);
        $total_atual += $saldo_com_cota;
        $total_enviado += $orc_com_cota;
    }

    // Soma saldos dos técnicos (não têm orc_recebido)
    foreach ($tecnicos as $tecnico) {
        $total_atual += floatval($tecnico['saldo_atual'] ?? 0);
        $total_enviado += floatval($tecnico['saldo_atual'] ?? 0);
    }

    return [
        'total_atual' => $total_atual,
        'total_enviado' => $total_enviado
    ];
}
// 7. Calcula os totais da equipe
$totais_equipe = calcularTotaisEquipe($gerentes, $supervisores, $coordenadores, $tecnicos);
$saldo_total_equipe = $totais_equipe['total_atual'];
$total_enviado_equipe = $totais_equipe['total_enviado'];

// 8. Busca histórico recente da equipe (últimas 10 movimentações)
$sql_historico_recente = "SELECT 
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
                            h.matricula IN (
                                SELECT DISTINCT u.matricula
                                FROM bdcorp.tbdiretor dir
                                JOIN bdcorp.tbgerente g ON g.idtbdiretor = dir.id
                                JOIN bdcorp.tbcoord c ON c.idtbgerente = g.idtbgerente
                                JOIN bdcorp.tbsupervisor sup ON sup.idtbcoordenador = c.idtbcoordenador
                                JOIN bdcorp.tbusuario u ON sup.idtbsupervisor = u.idtbsupervisor
                                JOIN bdcorp.tbfuncionario f ON u.matricula = f.matricula
                                WHERE dir.matricula = ?
                                    AND f.status != 'demitido'
                                UNION
                                SELECT DISTINCT u.matricula
                                FROM bdcorp.tbdiretor dir
                                JOIN bdcorp.tbgerente g ON g.idtbdiretor = dir.id
                                JOIN bdcorp.tbcoord c ON c.idtbgerente = g.idtbgerente
                                JOIN bdcorp.tbusuario u ON c.matricula = u.matricula
                                JOIN bdcorp.tbfuncionario f ON u.matricula = f.matricula
                                WHERE dir.matricula = ?
                                    AND f.status != 'demitido'
                                UNION
                                SELECT DISTINCT u.matricula
                                FROM bdcorp.tbdiretor dir
                                JOIN bdcorp.tbgerente g ON g.idtbdiretor = dir.id
                                JOIN bdcorp.tbusuario u ON g.matricula = u.matricula
                                JOIN bdcorp.tbfuncionario f ON u.matricula = f.matricula
                                WHERE dir.matricula = ?
                                    AND f.status != 'demitido'
                                UNION
                                SELECT DISTINCT u.matricula
                                FROM bdcorp.tbdiretor dir
                                JOIN bdcorp.tbusuario u ON dir.matricula = u.matricula
                                JOIN bdcorp.tbfuncionario f ON u.matricula = f.matricula
                                WHERE dir.matricula = ?
                                    AND f.status != 'demitido'
                            )
                        ORDER BY 
                            h.data DESC
                        LIMIT 10";

$stmt = mysqli_prepare($conn, $sql_historico_recente);
mysqli_stmt_bind_param($stmt, 'ssss', $matricula, $matricula, $matricula, $matricula);
mysqli_stmt_execute($stmt);
$result_recente = mysqli_stmt_get_result($stmt);
$historico_recente = mysqli_fetch_all($result_recente, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Dashboard Diretor - Gestão de Combustível</title>
    <link rel="icon" type="image/png" href="../src/images/favicon.png" />
    <link href="../src/css/styles.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />

    <style>
        :root {
            --primary-color: #0d6efd;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #0dcaf0;
        }

        .dashboard-card {
            border-radius: 15px;
            padding: 20px;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 14px;
            color: #6c757d;
            font-weight: 500;
        }

        .stat-value-small {
            font-size: 20px;
            font-weight: 600;
        }

        .hierarchy-card {
            border-left: 4px solid var(--primary-color);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            background: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
            cursor: pointer;
            position: relative;
        }

        .hierarchy-card:hover {
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transform: translateX(5px);
        }

        .hierarchy-card .click-indicator {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.3;
            transition: opacity 0.2s;
        }

        .hierarchy-card:hover .click-indicator {
            opacity: 1;
        }

        .hierarchy-card.level-gerente {
            border-left-color: #0d6efd;
        }

        .hierarchy-card.level-coordenador {
            border-left-color: #6f42c1;
        }

        .hierarchy-card.level-supervisor {
            border-left-color: #fd7e14;
        }

        .hierarchy-card.level-tecnico {
            border-left-color: #20c997;
        }

        .badge-level {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 20px;
        }

        .badge-level.gerente {
            background: #cfe2ff;
            color: #084298;
        }

        .badge-level.coordenador {
            background: #e2d9f3;
            color: #3b1f6e;
        }

        .badge-level.supervisor {
            background: #ffe5d0;
            color: #9d4b00;
        }

        .badge-level.tecnico {
            background: #d1f7e5;
            color: #0a6840;
        }

        .table-clickable tbody tr {
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .table-clickable tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.1) !important;
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

        .valor-positivo {
            color: #28a745;
            font-weight: 600;
        }

        .valor-negativo {
            color: #dc3545;
            font-weight: 600;
        }

        .card-clickable {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .card-clickable:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .total-colaboradores {
            font-size: 14px;
            color: #6c757d;
            margin-top: 5px;
        }

        .card-orcamento {
            border-left: 4px solid #0d6efd;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }

        .card-orcamento .stat-value {
            font-size: 32px;
        }

        .orc-recebido-info {
            font-size: 12px;
            color: #6c757d;
            display: block;
            margin-top: 2px;
        }

        .total-enviado {
            font-size: 14px;
            color: #28a745;
            font-weight: 600;
        }

        .total-enviado i {
            color: #28a745;
        }

        .cota-fixa-badge {
            font-size: 11px;
            background: #e7f5ff;
            color: #0d6efd;
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
            margin-top: 2px;
        }

        @media (max-width: 576px) {
            .stat-value {
                font-size: 22px;
            }

            .dashboard-card {
                padding: 15px;
            }

            .hierarchy-card .click-indicator {
                display: none;
            }
        }
    </style>
</head>

<body class="sb-nav-fixed">
    <?php autofrotaMenu(); ?>

    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid px-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                    <div>
                        <h1 class="mt-4 mb-1">
                            <i class="fas fa-chart-pie me-2"></i>Dashboard Financeiro
                        </h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item active">Visão completa da equipe e gastos com combustível</li>
                        </ol>
                    </div>
                    <div class="mt-3 mt-md-0">
                        <span class="badge bg-primary p-2">
                            <i class="fas fa-calendar me-1"></i>
                            <?= date('d/m/Y H:i') ?>
                        </span>
                    </div>
                </div>

                <!-- Totalizador - Saldo Total da Equipe e Orçamento do Diretor -->
                <div class="row g-3 mb-4">
                    <!-- Saldo Total da Equipe -->
                    <div class="col-xl-6 col-md-6">
                        <div class="dashboard-card bg-white text-center card-clickable">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="card-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="stat-label">Saldo Total Atual</div>
                                    <div class="stat-value text-primary">R$
                                        <?= number_format($saldo_total_equipe, 2, ',', '.') ?>
                                    </div>
                                    <div class="total-colaboradores">
                                        <i class="fas fa-user-tie me-1"></i>
                                        <?= count($gerentes) ?> Gerentes &nbsp;|&nbsp;
                                        <i class="fas fa-user-cog me-1"></i>
                                        <?= count($coordenadores) ?> Coordenadores &nbsp;|&nbsp;
                                        <i class="fas fa-user-check me-1"></i>
                                        <?= count($supervisores) ?> Supervisores &nbsp;|&nbsp;
                                        <i class="fas fa-user-hard-hat me-1"></i>
                                        <?= count($tecnicos) ?> Técnicos
                                    </div>
                                    <div class="mt-2 total-enviado">
                                        <i class="fas fa-arrow-right me-1"></i>
                                        Saldo total inicial: R$
                                        <?= number_format($total_enviado_equipe, 2, ',', '.') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Orçamento do Diretor -->
                    <div class="col-xl-6 col-md-6">
                        <div class="dashboard-card bg-white text-center card-orcamento">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="card-icon bg-success bg-opacity-10 text-success">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="stat-label">Orçamento do Diretor</div>
                                    <div class="stat-value text-success">R$
                                        <?php
                                        $saldo_diretor_com_cota = ($dados_diretor['saldo_atual'] ?? 0) + ($dados_diretor['cota_fixa'] ?? 0);
                                        echo number_format($saldo_diretor_com_cota, 2, ',', '.');
                                        ?>
                                    </div>
                                    <div class="total-colaboradores">
                                        <i class="fas fa-id-card me-1"></i>
                                        <?= htmlspecialchars($dados_diretor['nome'] ?? 'Não informado') ?>
                                        <?php if ($dados_diretor['orc_recebido'] || $dados_diretor['cota_fixa']): ?>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-arrow-right me-1"></i>
                                                Orçamento Recebido: R$
                                                <?= number_format($dados_diretor['orc_recebido'] ?? 0, 2, ',', '.') ?>
                                                <?php if ($dados_diretor['cota_fixa'] > 0): ?>
                                                    <span class="cota-fixa-badge">
                                                        <i class="fas fa-plus-circle me-1"></i>
                                                        Cota Fixa: R$
                                                        <?= number_format($dados_diretor['cota_fixa'], 2, ',', '.') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hierarquia - Gerentes e Coordenadores Lado a Lado com Scroll -->
                <div class="row g-3 mb-4">
                    <!-- Coluna Gerentes -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-user-tie me-1"></i>Gerentes</span>
                                <span class="badge bg-primary rounded-pill"><?= count($gerentes) ?></span>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php if (count($gerentes) > 0): ?>
                                    <?php foreach ($gerentes as $gerente):
                                        $saldo_com_cota = ($gerente['saldo_atual'] ?? 0) + ($gerente['cota_fixa'] ?? 0);
                                        $saldo_sem_cota = ($gerente['saldo_atual'] ?? 0);
                                        $orc_com_cota = ($gerente['orc_recebido'] ?? 0) + ($gerente['cota_fixa'] ?? 0);
                                        $orc_sem_cota = $gerente['orc_recebido'] ?? 0;
                                        ?>
                                        <div class="hierarchy-card level-gerente"
                                            onclick="window.location.href='historico_combustivel.php?matricula=<?= $gerente['matricula'] ?>'"
                                            title="Clique para ver histórico de <?= htmlspecialchars($gerente['nome']) ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?= htmlspecialchars($gerente['nome']) ?></strong>
                                                    <br>
                                                    <small class="text-muted">Matrícula: <?= $gerente['matricula'] ?></small>
                                                    <br>
                                                    <?php if ($gerente['orc_recebido'] || $gerente['cota_fixa']): ?>
                                                        <span class="orc-recebido-info">
                                                            <i class="fas fa-flag-checkered me-1"></i>
                                                            Orçamento Recebido: R$
                                                            <?= number_format($orc_sem_cota, 2, ',', '.') ?>
                                                            <?php if ($gerente['cota_fixa'] > 0): ?>
                                                                <span class="cota-fixa-badge">
                                                                    + R$ <?= number_format($gerente['cota_fixa'], 2, ',', '.') ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-muted">Orçamento Atual</small>
                                                    <div class="fw-bold text-primary">R$
                                                        <?= number_format($saldo_sem_cota, 2, ',', '.') ?>
                                                    </div>
                                                    <?php if ($gerente['cota_fixa'] > 0): ?>
                                                        <small class="text-success d-block">
                                                            <i class="fas fa-plus-circle me-1"></i>
                                                            Cota: R$ <?= number_format($gerente['cota_fixa'], 2, ',', '.') ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="click-indicator">
                                                <i class="fas fa-chevron-right text-muted"></i>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center mb-0">Nenhum gerente encontrado</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Coluna Coordenadores -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-user-cog me-1"></i>Coordenadores</span>
                                <span class="badge bg-purple rounded-pill"
                                    style="background:#6f42c1;"><?= count($coordenadores) ?></span>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php if (count($coordenadores) > 0): ?>
                                    <?php foreach ($coordenadores as $coordenador):
                                        $saldo_com_cota = ($coordenador['saldo_atual'] ?? 0) + ($coordenador['cota_fixa'] ?? 0);
                                        $saldo_sem_cota = ($coordenador['saldo_atual'] ?? 0);
                                        $orc_com_cota = ($coordenador['orc_recebido'] ?? 0) + ($coordenador['cota_fixa'] ?? 0);
                                        $orc_sem_cota = $coordenador['orc_recebido'] ?? 0;
                                        ?>
                                        <div class="hierarchy-card level-coordenador"
                                            onclick="window.location.href='historico_combustivel.php?matricula=<?= $coordenador['matricula'] ?>'"
                                            title="Clique para ver histórico de <?= htmlspecialchars($coordenador['nome']) ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?= htmlspecialchars($coordenador['nome']) ?></strong>
                                                    <br>
                                                    <small class="text-muted">Matrícula:
                                                        <?= $coordenador['matricula'] ?></small>
                                                    <br>
                                                    <?php if ($coordenador['orc_recebido'] || $coordenador['cota_fixa']): ?>
                                                        <span class="orc-recebido-info">
                                                            <i class="fas fa-flag-checkered me-1"></i>
                                                            Orçamento Recebido: R$
                                                            <?= number_format($orc_sem_cota, 2, ',', '.') ?>
                                                            <?php if ($coordenador['cota_fixa'] > 0): ?>
                                                                <span class="cota-fixa-badge">
                                                                    + R$ <?= number_format($coordenador['cota_fixa'], 2, ',', '.') ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-muted">Orçamento Atual</small>
                                                    <div class="fw-bold text-purple" style="color:#6f42c1;">R$
                                                        <?= number_format($saldo_sem_cota, 2, ',', '.') ?>
                                                    </div>
                                                    <?php if ($coordenador['cota_fixa'] > 0): ?>
                                                        <small class="text-success d-block">
                                                            <i class="fas fa-plus-circle me-1"></i>
                                                            Cota: R$ <?= number_format($coordenador['cota_fixa'], 2, ',', '.') ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="click-indicator">
                                                <i class="fas fa-chevron-right text-muted"></i>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center mb-0">Nenhum coordenador encontrado</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resumo da Equipe -->
                <div class="row g-3 mt-2">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-chart-bar me-2"></i>
                                Resumo da Equipe
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <!-- Gerentes -->
                                    <div class="col-md-3">
                                        <div class="dashboard-card bg-white text-center">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="card-icon bg-primary bg-opacity-10 text-primary">
                                                    <i class="fas fa-user-tie"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="stat-label">Gerentes</div>
                                                    <div class="stat-value text-primary">
                                                        <?= count($gerentes) ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        Orçamento Atual: R$
                                                        <?php
                                                        $saldos_gerentes = 0;
                                                        $cotas_gerentes = 0;
                                                        foreach ($gerentes as $gerente) {
                                                            $saldos_gerentes += floatval($gerente['saldo_atual'] ?? 0);
                                                            $cotas_gerentes += floatval($gerente['cota_fixa'] ?? 0);
                                                        }
                                                        echo number_format($saldos_gerentes, 2, ',', '.');
                                                        ?>
                                                        <br>
                                                        <span class="text-success">
                                                            <i class="fas fa-plus-circle me-1"></i>
                                                            Cota: R$ <?= number_format($cotas_gerentes, 2, ',', '.') ?>
                                                        </span>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Coordenadores -->
                                    <div class="col-md-3">
                                        <div class="dashboard-card bg-white text-center">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="card-icon bg-purple bg-opacity-10" style="color:#6f42c1;">
                                                    <i class="fas fa-user-cog"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="stat-label">Coordenadores</div>
                                                    <div class="stat-value" style="color:#6f42c1;">
                                                        <?= count($coordenadores) ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        Orçamento Atual: R$
                                                        <?php
                                                        $saldos_coordenadores = 0;
                                                        $cotas_coordenadores = 0;
                                                        foreach ($coordenadores as $coordenador) {
                                                            $saldos_coordenadores += floatval($coordenador['saldo_atual'] ?? 0);
                                                            $cotas_coordenadores += floatval($coordenador['cota_fixa'] ?? 0);
                                                        }
                                                        echo number_format($saldos_coordenadores, 2, ',', '.');
                                                        ?>
                                                        <br>
                                                        <span class="text-success">
                                                            <i class="fas fa-plus-circle me-1"></i>
                                                            Cota: R$
                                                            <?= number_format($cotas_coordenadores, 2, ',', '.') ?>
                                                        </span>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Supervisores -->
                                    <div class="col-md-3">
                                        <div class="dashboard-card bg-white text-center">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="card-icon bg-warning bg-opacity-10 text-warning">
                                                    <i class="fas fa-user-check"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="stat-label">Supervisores</div>
                                                    <div class="stat-value text-warning">
                                                        <?= count($supervisores) ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        Cota: R$
                                                        <?php
                                                        $saldos_supervisores = 0;
                                                        foreach ($supervisores as $supervisor) {
                                                            $saldos_supervisores += floatval($supervisor['saldo_atual'] ?? 0);
                                                        }
                                                        echo number_format($saldos_supervisores, 2, ',', '.');
                                                        ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Técnicos -->
                                    <div class="col-md-3">
                                        <div class="dashboard-card bg-white text-center">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="card-icon bg-success bg-opacity-10 text-success">
                                                    <i class="fas fa-user-hard-hat"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="stat-label">Técnicos</div>
                                                    <div class="stat-value text-success">
                                                        <?= count($tecnicos) ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        Cota: R$
                                                        <?php
                                                        $saldos_tecnicos = 0;
                                                        foreach ($tecnicos as $tecnico) {
                                                            $saldos_tecnicos += floatval($tecnico['saldo_atual'] ?? 0);
                                                        }
                                                        echo number_format($saldos_tecnicos, 2, ',', '.');
                                                        ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="../src/js/scripts.js"></script>
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js"></script>

    <script>
        $(document).ready(function () {
            // Inicializa tooltips
            $('[data-bs-toggle="tooltip"]').tooltip();

            // Adiciona feedback visual ao clicar
            $('.hierarchy-card, .table-clickable tbody tr, .card-clickable').on('click', function (e) {
                if ($(e.target).closest('a').length) return;
                $(this).css('transform', 'scale(0.98)');
                setTimeout(() => {
                    $(this).css('transform', '');
                }, 150);
            });
        });
    </script>
</body>

</html>
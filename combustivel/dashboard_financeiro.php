<?php
date_default_timezone_set('America/Sao_Paulo');
session_start();
$nome = $_SESSION['nome'];
$usuariof = $_SESSION['usuario'];
$perfil = $_SESSION['perfil'];

// Pega a matrícula do diretor selecionado (via GET ou SESSION)
$matricula = isset($_GET['matricula']) ? $_GET['matricula'] : (isset($_SESSION['matricula_diretor']) ? $_SESSION['matricula_diretor'] : '');

// Se não tiver matrícula selecionada, mostra a seleção
if (empty($matricula)) {
    $mostrar_selecao = true;
} else {
    $mostrar_selecao = false;
    $_SESSION['matricula_diretor'] = $matricula;
}

require_once '../control/conecta.php';
require_once '../includes/autofrota_common.php';
error_reporting(1);
ini_set('display_errors', 1);

// ==============================================
// BUSCA TODOS OS DIRETORES (PERFIL 4)
// ==============================================

$sql_todos_diretores = "SELECT 
                            d.matricula,
                            f.nome,
                            d.valor as saldo,
                            d.orcrecebido as orc_recebido
                        FROM 
                            bdcorp.tbdiretor d
                        JOIN bdcorp.tbfuncionario f ON d.matricula = f.matricula
                        WHERE 
                            f.status != 'demitido'
                        ORDER BY 
                            f.nome ASC";

$result_todos_diretores = mysqli_query($conn, $sql_todos_diretores);
$todos_diretores = mysqli_fetch_all($result_todos_diretores, MYSQLI_ASSOC);

// ==============================================
// BUSCA DADOS DO DIRETOR SELECIONADO
// ==============================================

if (!$mostrar_selecao) {
    // 1. Dados do diretor logado (incluindo cota fixa)
    $sql_diretor = "SELECT 
                        u.matricula,
                        u.nome,
                        u.perfil,
                        d.valor as saldo_atual,
                        d.orcrecebido as orc_recebido,
                        COALESCE(cf.valor, 0) as cota_fixa,
                        d.orcrecebido as orc_inicial
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
                        g.idtbgerente as id_gerente,
                        g.orcrecebido as orc_inicial
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

    // 3. Busca todos os coordenadores abaixo do diretor
    $sql_coordenadores = "SELECT 
                            DISTINCT u.matricula,
                            u.nome,
                            u.perfil,
                            c.valor as saldo_atual,
                            c.orcrecebido as orc_recebido,
                            COALESCE(cf.valor, 0) as cota_fixa,
                            c.idtbcoordenador as id_coordenador,
                            c.orcrecebido as orc_inicial
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
                            0 as cota_fixa,
                            COALESCE(s.saldo, 150) as orc_inicial
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
                        s.saldo as saldo_atual,
                        s.valoraplicado as saldo_inicial,
                        s.kmorcsem as kmproj,
                        s.data as ultima_atualizacao,
                        0 as cota_fixa,
                        s.valoraplicado as orc_inicial
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

    // 6. Função para calcular totais da equipe
    function calcularTotaisEquipe($gerentes, $supervisores, $coordenadores, $tecnicos, $dados_diretor = null)
    {
        $total_atual = 0;
        $total_enviado = 0;

        if ($dados_diretor) {
            $saldo_diretor_com_cota = floatval($dados_diretor['saldo_atual'] ?? 0) + floatval($dados_diretor['cota_fixa'] ?? 0);
            $orc_diretor_com_cota = floatval($dados_diretor['orc_recebido'] ?? 0) + floatval($dados_diretor['cota_fixa'] ?? 0);
            $total_atual += $saldo_diretor_com_cota;
            $total_enviado += $orc_diretor_com_cota;
        }

        foreach ($gerentes as $gerente) {
            $saldo_com_cota = floatval($gerente['saldo_atual'] ?? 0) + floatval($gerente['cota_fixa'] ?? 0);
            $orc_com_cota = floatval($gerente['orc_recebido'] ?? 0) + floatval($gerente['cota_fixa'] ?? 0);
            $total_atual += $saldo_com_cota;
            $total_enviado += $orc_com_cota;
        }

        foreach ($supervisores as $supervisor) {
            $total_atual += floatval($supervisor['saldo_atual'] ?? 0);
            $total_enviado += floatval($supervisor['orc_recebido'] ?? 0);
        }

        foreach ($coordenadores as $coordenador) {
            $saldo_com_cota = floatval($coordenador['saldo_atual'] ?? 0) + floatval($coordenador['cota_fixa'] ?? 0);
            $orc_com_cota = floatval($coordenador['orc_recebido'] ?? 0) + floatval($coordenador['cota_fixa'] ?? 0);
            $total_atual += $saldo_com_cota;
            $total_enviado += $orc_com_cota;
        }

        foreach ($tecnicos as $tecnico) {
            $total_atual += floatval($tecnico['saldo_atual'] ?? 0);
            $total_enviado += floatval($tecnico['saldo_inicial'] ?? 0);
        }

        return [
            'total_atual' => $total_atual,
            'total_enviado' => $total_enviado
        ];
    }

    $totais_equipe = calcularTotaisEquipe($gerentes, $supervisores, $coordenadores, $tecnicos, $dados_diretor);
    $saldo_total_equipe = $totais_equipe['total_atual'];
    $total_enviado_equipe = $totais_equipe['total_enviado'];
}
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
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.1.0/css/all.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <style>
        /* ========================================== */
        /* ZOOM DA TELA PARA 80% */
        /* ========================================== */
        html {
            zoom: 0.8;
        }

        @media (max-width: 768px) {
            html {
                zoom: 1;
                -moz-transform: scale(1);
                -o-transform: scale(1);
                -webkit-transform: scale(1);
            }
        }

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

        /* ========================================== */
        /* ESTILOS DA BARRA LATERAL */
        /* ========================================== */
        .sidebar-diretores {
            top: 80px;
            height: calc(100vh - 120px);
        }

        .sidebar-lista {
            max-height: calc(100vh - 250px);
            overflow-y: auto;
        }

        .sidebar-lista .diretor-item {
            border-left: 3px solid #0d6efd;
            padding: 10px 12px;
            margin-bottom: 6px;
            border-radius: 8px;
            background: white;
            transition: all 0.2s;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .sidebar-lista .diretor-item:hover {
            background: #f0f7ff;
            border-left-color: #0a58ca;
            transform: translateX(3px);
        }

        .sidebar-lista .diretor-item.active {
            background: #0d6efd;
            color: white;
            border-left-color: #0a58ca;
        }

        .sidebar-lista .diretor-item.active .text-muted {
            color: rgba(255, 255, 255, 0.7) !important;
        }

        .sidebar-lista .diretor-item .nome {
            font-weight: 600;
            font-size: 14px;
        }

        .sidebar-lista .diretor-item .saldo {
            font-size: 13px;
        }

        .sidebar-lista .diretor-item .matricula {
            font-size: 11px;
        }

        .sidebar-lista::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar-lista::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .sidebar-lista::-webkit-scrollbar-thumb {
            background: #0d6efd;
            border-radius: 10px;
        }

        /* ========================================== */
        /* TELA DE SELEÇÃO */
        /* ========================================== */
        .selecao-container {
            min-height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .selecao-card {
            max-width: 600px;
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
        }

        .selecao-card .icon-big {
            font-size: 60px;
            color: #0d6efd;
            opacity: 0.3;
        }

        .selecao-card .diretor-item-select {
            border-left: 3px solid #0d6efd;
            padding: 15px 18px;
            margin-bottom: 8px;
            border-radius: 10px;
            background: white;
            transition: all 0.2s;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: block;
            text-decoration: none;
            color: inherit;
        }

        .selecao-card .diretor-item-select:hover {
            background: #f0f7ff;
            transform: translateX(5px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .selecao-card .diretor-item-select .nome {
            font-weight: 600;
            font-size: 16px;
        }

        .selecao-card .diretor-item-select .saldo {
            font-weight: 600;
            color: #0d6efd;
        }

        /* ========================================== */
        /* MENSAGEM DE NENHUM DIRETOR SELECIONADO */
        /* ========================================== */
        .nenhum-diretor-msg {
            min-height: 60vh;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .nenhum-diretor-msg .icon-msg {
            font-size: 80px;
            color: #6c757d;
            opacity: 0.3;
            margin-bottom: 20px;
        }

        .nenhum-diretor-msg h3 {
            color: #495057;
        }

        .nenhum-diretor-msg p {
            color: #6c757d;
            max-width: 400px;
        }

        /* ========================================== */
        /* RESPONSIVIDADE */
        /* ========================================== */
        @media (max-width: 992px) {
            .sidebar-diretores {
                position: relative;
                top: 0 !important;
                height: auto;
                margin-top: 20px;
            }

            .sidebar-lista {
                max-height: 300px;
            }
        }

        @media (max-width: 768px) {
            .selecao-card {
                padding: 20px;
                margin: 10px;
            }
        }

        /* ========================================== */
        /* ESTILO DO ORÇAMENTO INICIAL */
        /* ========================================== */
        .orc-inicial-info {
            font-size: 12px;
            color: #6c757d;
        }

        .orc-inicial-info .label {
            color: #495057;
            font-weight: 500;
        }

        /* ========================================== */
        /* ESTILOS DO MODAL */
        /* ========================================== */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .modal-header {
            border-bottom: 2px solid #f0f0f0;
            padding: 20px 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 15px 15px 0 0;
        }

        .modal-header .modal-title {
            font-weight: 700;
            color: #1a1a2e;
        }

        .modal-header .btn-close {
            background-color: #f0f0f0;
            border-radius: 50%;
            padding: 8px;
            opacity: 0.7;
            transition: all 0.2s;
        }

        .modal-header .btn-close:hover {
            opacity: 1;
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 20px 25px;
            max-height: 500px;
            overflow-y: auto;
        }

        .modal-footer {
            border-top: 2px solid #f0f0f0;
            padding: 15px 25px;
            background: #fafafa;
            border-radius: 0 0 15px 15px;
        }

        .modal-footer .btn {
            border-radius: 8px;
            padding: 8px 20px;
            font-weight: 500;
        }

        .modal-footer .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
        }

        .modal-footer .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .modal-footer .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.2);
        }

        /* Estilos para a tabela dentro do modal */
        .modal-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .modal-table thead th {
            background: #f8f9fa;
            color: #1a1a2e;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 15px;
            border-bottom: 2px solid #e0e0e0;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .modal-table tbody td {
            padding: 10px 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .modal-table tbody tr {
            transition: all 0.2s;
        }

        .modal-table tbody tr:hover {
            background: #f8f9fa;
            transform: scale(1.01);
        }

        .modal-table tbody tr:last-child td {
            border-bottom: none;
        }

        .modal-table .badge-level {
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 500;
        }

        .modal-table .badge-level.supervisor {
            background: #ffe5d0;
            color: #9d4b00;
        }

        .modal-table .badge-level.tecnico {
            background: #d1f7e5;
            color: #0a6840;
        }

        .modal-table .saldo-positivo {
            color: #28a745;
            font-weight: 600;
        }

        .modal-table .saldo-negativo {
            color: #dc3545;
            font-weight: 600;
        }

        /* Scrollbar do modal */
        .modal-body::-webkit-scrollbar {
            width: 6px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: #0d6efd;
            border-radius: 10px;
        }

        /* Botão de exportação */
        .btn-export {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(40, 167, 69, 0.2);
        }

        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(40, 167, 69, 0.3);
            color: white;
        }

        .btn-export i {
            margin-right: 8px;
        }

        /* Badge de contagem no botão */
        .btn-export .badge-count {
            background: rgba(255,255,255,0.3);
            color: white;
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 11px;
            margin-left: 6px;
        }

        /* Estilo para o card clicável com indicador */
        .card-clickable .click-hint {
            font-size: 10px;
            color: #6c757d;
            opacity: 0.6;
            transition: opacity 0.2s;
        }

        .card-clickable:hover .click-hint {
            opacity: 1;
        }
    </style>
</head>

<body class="sb-nav-fixed">
    <?php autofrotaMenu(); ?>

    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid px-4">

                <!-- ========================================== -->
                <!-- TELA DE SELEÇÃO (quando não tem diretor escolhido) -->
                <!-- ========================================== -->
                <?php if ($mostrar_selecao): ?>
                    <div class="selecao-container">
                        <div class="selecao-card bg-white text-center">
                            <div class="icon-big mb-3">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <h3 class="mb-2">Selecione um Diretor</h3>
                            <p class="text-muted mb-4">Clique em um diretor para visualizar o dashboard</p>

                            <?php if (count($todos_diretores) > 0): ?>
                                <div class="text-start">
                                    <?php foreach ($todos_diretores as $diretor): ?>
                                        <a href="?matricula=<?= $diretor['matricula'] ?>" class="diretor-item-select">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="nome">
                                                        <i class="fas fa-user-circle me-2 text-primary"></i>
                                                        <?= htmlspecialchars($diretor['nome']) ?>
                                                    </div>
                                                    <div class="matricula text-muted">
                                                        <i class="fas fa-id-card me-1"></i>
                                                        Matrícula: <?= $diretor['matricula'] ?>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <div class="saldo">
                                                        R$ <?= number_format($diretor['saldo'] ?? 0, 2, ',', '.') ?>
                                                    </div>
                                                    <div class="matricula text-muted">
                                                        <i class="fas fa-flag-checkered me-1"></i>
                                                        Orçamento: R$
                                                        <?= number_format($diretor['orc_recebido'] ?? 0, 2, ',', '.') ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Nenhum diretor encontrado no sistema.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>

                    <!-- ========================================== -->
                    <!-- DASHBOARD DO DIRETOR SELECIONADO -->
                    <!-- ========================================== -->

                    <div class="row">

                        <!-- COLUNA ESQUERDA - CONTEÚDO PRINCIPAL -->
                        <?php if ($perfil == 4): ?>
                            <div class="col-lg-9">
                            <?php else: ?>
                                <div class="col-lg-12"></div>
                            <?php endif; ?>
                            <!-- Header -->
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                                <div>
                                    <h1 class="mt-4 mb-1">
                                        <i class="fas fa-chart-pie me-2"></i>Dashboard Financeiro
                                    </h1>
                                    <ol class="breadcrumb mb-4">
                                        <li class="breadcrumb-item active">
                                            <i class="fas fa-user-tie me-1"></i>
                                            <?= htmlspecialchars($dados_diretor['nome'] ?? 'Diretor') ?>
                                            (<?= $matricula ?>)
                                        </li>
                                    </ol>
                                </div>
                                <div>
                                    <button class="btn-export" onclick="exportarDados()">
                                        <i class="fas fa-file-excel"></i>
                                        Exportar Dados
                                        <span class="badge-count">CSV</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Totalizadores -->
                            <div class="row g-3 mb-4">
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
                                                    Orçamento Inicial Total: R$
                                                    <?= number_format($total_enviado_equipe, 2, ',', '.') ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

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
                                                            Orçamento Inicial: R$
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

                            <!-- Hierarquia -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <span><i class="fas fa-user-tie me-1"></i>Gerentes</span>
                                            <span class="badge bg-primary rounded-pill"><?= count($gerentes) ?></span>
                                        </div>
                                        <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                            <?php if (count($gerentes) > 0): ?>
                                                <?php foreach ($gerentes as $gerente):
                                                    $saldo_sem_cota = ($gerente['saldo_atual'] ?? 0);
                                                    $orc_inicial = $gerente['orc_inicial'] ?? 0;
                                                    ?>
                                                    <div class="hierarchy-card level-gerente"
                                                        onclick="window.location.href='historico_combustivel.php?matricula=<?= $gerente['matricula'] ?>'">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong><?= htmlspecialchars($gerente['nome']) ?></strong>
                                                                <br>
                                                                <small class="text-muted">Matrícula:
                                                                    <?= $gerente['matricula'] ?></small>
                                                                <br>
                                                                <small class="orc-inicial-info">
                                                                    <span class="label">Orçamento Inicial:</span>
                                                                    R$
                                                                    <?= number_format($orc_inicial, 2, ',', '.') ?>
                                                                </small>
                                                            </div>
                                                            <div class="text-end">
                                                                <small class="text-muted">Saldo Atual</small>
                                                                <div class="fw-bold text-primary">R$
                                                                    <?= number_format($saldo_sem_cota, 2, ',', '.') ?>
                                                                </div>
                                                                <?php if ($gerente['cota_fixa'] > 0): ?>
                                                                    <span class="cota-fixa-badge">
                                                                        Cota Fixa: R$
                                                                        <?= number_format($gerente['cota_fixa'], 2, ',', '.') ?>
                                                                    </span>
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
                                                    $saldo_sem_cota = ($coordenador['saldo_atual'] ?? 0);
                                                    $orc_inicial = $coordenador['orc_inicial'] ?? 0;
                                                    ?>
                                                    <div class="hierarchy-card level-coordenador"
                                                        onclick="window.location.href='historico_combustivel.php?matricula=<?= $coordenador['matricula'] ?>'">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong><?= htmlspecialchars($coordenador['nome']) ?></strong>
                                                                <br>
                                                                <small class="text-muted">Matrícula:
                                                                    <?= $coordenador['matricula'] ?></small>
                                                                <br>
                                                                <small class="orc-inicial-info">
                                                                    <span class="label">Orçamento Inicial:</span>
                                                                    R$
                                                                    <?= number_format($orc_inicial, 2, ',', '.') ?>
                                                                </small>
                                                            </div>
                                                            <div class="text-end">
                                                                <small class="text-muted">Saldo Atual</small>
                                                                <div class="fw-bold text-purple" style="color:#6f42c1;">R$
                                                                    <?= number_format($saldo_sem_cota, 2, ',', '.') ?>
                                                                </div>
                                                                <?php if ($coordenador['cota_fixa'] > 0): ?>
                                                                    <span class="cota-fixa-badge">
                                                                        Cota Fixa: R$
                                                                        <?= number_format($coordenador['cota_fixa'], 2, ',', '.') ?>
                                                                    </span>
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

                            <!-- Mensagem de nenhum diretor selecionado (dentro do dashboard) -->
                            <?php if (empty($matricula) || !isset($dados_diretor['nome'])): ?>
                                <div class="nenhum-diretor-msg">
                                    <div class="icon-msg">
                                        <i class="fas fa-user-tie"></i>
                                    </div>
                                    <h3>Selecione um Diretor</h3>
                                    <p class="text-muted text-center">
                                        Para visualizar as informações do dashboard,
                                        selecione um diretor na lista ao lado.
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- COLUNA DIREITA - LISTA DE DIRETORES (FIXA) -->
                        <?php if ($perfil == 4): ?>
                            <div class="col-lg-3" style="margin-top:150px">
                                <div class="sidebar-diretores">
                                    <div class="card">
                                        <div class="card-header">
                                            <i class="fas fa-list me-1"></i>
                                            Diretores
                                            <span class="badge bg-secondary float-end"><?= count($todos_diretores) ?></span>
                                        </div>
                                        <div class="card-body p-2 sidebar-lista">
                                            <?php if (count($todos_diretores) > 0): ?>
                                                <?php foreach ($todos_diretores as $diretor):
                                                    $is_active = ($diretor['matricula'] == $matricula);
                                                    ?>
                                                    <div class="diretor-item <?= $is_active ? 'active' : '' ?>"
                                                        onclick="window.location.href='?matricula=<?= $diretor['matricula'] ?>'">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <div class="nome">
                                                                    <?= htmlspecialchars($diretor['nome']) ?>
                                                                    <?php if ($is_active): ?>
                                                                        <i class="fas fa-check-circle text-white ms-1"
                                                                            style="font-size:12px;"></i>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="matricula text-muted">
                                                                    <i class="fas fa-id-card me-1"></i>
                                                                    <?= $diretor['matricula'] ?>
                                                                </div>
                                                            </div>
                                                            <div class="text-end">
                                                                <div
                                                                    class="saldo fw-bold <?= $is_active ? 'text-white' : 'text-primary' ?>">
                                                                    R$ <?= number_format($diretor['saldo'] ?? 0, 2, ',', '.') ?>
                                                                </div>
                                                                <div class="matricula text-muted">
                                                                    <i class="fas fa-flag-checkered me-1"></i>
                                                                    R$ <?= number_format($diretor['orc_recebido'] ?? 0, 2, ',', '.') ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <p class="text-muted text-center my-3">Nenhum diretor encontrado</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer text-muted small">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Clique em um diretor para ver o dashboard
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

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
                                                                Saldo: R$
                                                                <?php
                                                                $saldos_gerentes = 0;
                                                                $orc_gerentes = 0;
                                                                foreach ($gerentes as $gerente) {
                                                                    $saldos_gerentes += floatval($gerente['saldo_atual'] ?? 0);
                                                                    $orc_gerentes += floatval($gerente['orc_inicial'] ?? 0) + floatval($gerente['cota_fixa'] ?? 0);
                                                                }
                                                                echo number_format($saldos_gerentes, 2, ',', '.');
                                                                ?>
                                                                <br>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Coordenadores -->
                                            <div class="col-md-3">
                                                <div class="dashboard-card bg-white text-center">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div class="card-icon bg-purple bg-opacity-10"
                                                            style="color:#6f42c1;">
                                                            <i class="fas fa-user-cog"></i>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="stat-label">Coordenadores</div>
                                                            <div class="stat-value" style="color:#6f42c1;">
                                                                <?= count($coordenadores) ?>
                                                            </div>
                                                            <small class="text-muted">
                                                                Saldo Atual: R$
                                                                <?php
                                                                $saldos_coordenadores = 0;
                                                                $orc_coordenadores = 0;
                                                                foreach ($coordenadores as $coordenador) {
                                                                    $saldos_coordenadores += floatval($coordenador['saldo_atual'] ?? 0);
                                                                    $orc_coordenadores += floatval($coordenador['orc_inicial'] ?? 0) + floatval($coordenador['cota_fixa'] ?? 0);
                                                                }
                                                                echo number_format($saldos_coordenadores, 2, ',', '.');
                                                                ?>
                                                                <br>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Supervisores - Agora clicável -->
                                            <div class="col-md-3">
                                                <div class="dashboard-card bg-white text-center card-clickable" 
                                                     onclick="abrirModalSupervisores()"
                                                     style="cursor: pointer;">
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
                                                                Saldo Atual: R$
                                                                <?php
                                                                $saldos_supervisores = 0;
                                                                $orc_supervisores = 0;
                                                                foreach ($supervisores as $supervisor) {
                                                                    $saldos_supervisores += floatval($supervisor['saldo_atual'] ?? 0);
                                                                    $orc_supervisores += floatval($supervisor['orc_inicial'] ?? 0);
                                                                }
                                                                echo number_format($saldos_supervisores, 2, ',', '.');
                                                                ?>
                                                                <br>
                                                                <span class="click-hint">
                                                                    <i class="fas fa-eye me-1"></i>Clique para ver lista
                                                                </span>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Técnicos - Agora clicável -->
                                            <div class="col-md-3">
                                                <div class="dashboard-card bg-white text-center card-clickable"
                                                     onclick="abrirModalTecnicos()"
                                                     style="cursor: pointer;">
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
                                                                Saldo: R$
                                                                <?php
                                                                $saldos_tecnicos = 0;
                                                                $orc_tecnicos = 0;
                                                                foreach ($tecnicos as $tecnico) {
                                                                    $saldos_tecnicos += floatval($tecnico['saldo_atual'] ?? 0);
                                                                    $orc_tecnicos += floatval($tecnico['orc_inicial'] ?? 0);
                                                                }
                                                                echo number_format($saldos_tecnicos, 2, ',', '.');
                                                                ?>
                                                                <br>
                                                                <span class="click-hint">
                                                                    <i class="fas fa-eye me-1"></i>Clique para ver lista
                                                                </span>
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

                <?php endif; ?>
                <!-- FIM DO DASHBOARD -->

            </div>
        </main>
    </div>

    <!-- ========================================== -->
    <!-- MODAL DE SUPERVISORES -->
    <!-- ========================================== -->
    <div class="modal fade" id="modalSupervisores" tabindex="-1" aria-labelledby="modalSupervisoresLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalSupervisoresLabel">
                        <i class="fas fa-user-check me-2 text-warning"></i>
                        Lista de Supervisores
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="modal-table" id="tabelaSupervisores">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nome</th>
                                    <th>Matrícula</th>
                                    <th>Perfil</th>
                                    <th>Saldo Atual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($supervisores) && count($supervisores) > 0): ?>
                                    <?php foreach ($supervisores as $index => $supervisor): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($supervisor['nome']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($supervisor['matricula']) ?></td>
                                            <td>
                                                <span class="badge-level supervisor">
                                                    <i class="fas fa-user-check me-1"></i>
                                                    Supervisor
                                                </span>
                                            </td>
                                            <td class="saldo-positivo">
                                                R$ <?= number_format($supervisor['saldo_atual'] ?? 0, 2, ',', '.') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Nenhum supervisor encontrado
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Fechar
                    </button>
                    <button type="button" class="btn btn-success" onclick="exportarTabela('tabelaSupervisores', 'Supervisores')">
                        <i class="fas fa-file-excel me-1"></i>
                        Exportar Lista
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL DE TÉCNICOS -->
    <!-- ========================================== -->
    <div class="modal fade" id="modalTecnicos" tabindex="-1" aria-labelledby="modalTecnicosLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTecnicosLabel">
                        <i class="fas fa-user-hard-hat me-2 text-success"></i>
                        Lista de Técnicos
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="modal-table" id="tabelaTecnicos">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nome</th>
                                    <th>Matrícula</th>
                                    <th>Perfil</th>
                                    <th>Saldo Atual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($tecnicos) && count($tecnicos) > 0): ?>
                                    <?php foreach ($tecnicos as $index => $tecnico): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($tecnico['nome']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($tecnico['matricula']) ?></td>
                                            <td>
                                                <span class="badge-level tecnico">
                                                    <i class="fas fa-user-hard-hat me-1"></i>
                                                    Técnico
                                                </span>
                                            </td>
                                            <td class="saldo-positivo">
                                                R$ <?= number_format($tecnico['saldo_atual'] ?? 0, 2, ',', '.') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Nenhum técnico encontrado
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Fechar
                    </button>
                    <button type="button" class="btn btn-success" onclick="exportarTabela('tabelaTecnicos', 'Tecnicos')">
                        <i class="fas fa-file-excel me-1"></i>
                        Exportar Lista
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="../src/js/scripts.js"></script>

    <script>
        $(document).ready(function () {
            // Inicializa tooltips
            $('[data-bs-toggle="tooltip"]').tooltip();

            // Feedback visual ao clicar
            $('.hierarchy-card, .card-clickable, .diretor-item, .diretor-item-select').on('click', function (e) {
                if ($(e.target).closest('a').length) return;
                $(this).css('transform', 'scale(0.98)');
                setTimeout(() => {
                    $(this).css('transform', '');
                }, 150);
            });

            // Ajusta o topo do sidebar
            function ajustarSidebar() {
                var headerHeight = $('.sb-nav-fixed .navbar').outerHeight() || 56;
                var topOffset = headerHeight + 20;
                $('.sidebar-diretores').css('top', topOffset + 'px');
            }

            ajustarSidebar();
            $(window).resize(ajustarSidebar);

            // Adiciona classes de hover nos cards clicáveis
            $('.card-clickable').on('mouseenter', function() {
                $(this).css('transform', 'translateY(-5px) scale(1.02)');
            }).on('mouseleave', function() {
                $(this).css('transform', '');
            });

            console.log('Dashboard carregado com sucesso!');
            console.log('Total de registros: <?= count($gerentes) + count($coordenadores) + count($supervisores) + count($tecnicos) ?>');
        });

        // ==========================================
        // FUNÇÕES PARA ABRIR MODAIS
        // ==========================================
        function abrirModalSupervisores() {
            const modal = new bootstrap.Modal(document.getElementById('modalSupervisores'));
            modal.show();
        }

        function abrirModalTecnicos() {
            const modal = new bootstrap.Modal(document.getElementById('modalTecnicos'));
            modal.show();
        }

        // ==========================================
        // FUNÇÃO PARA EXPORTAR TABELA PARA CSV
        // ==========================================
        function exportarTabela(tableId, nomeArquivo) {
            const table = document.getElementById(tableId);
            if (!table) return;

            let csv = [];
            const rows = table.querySelectorAll('tr');

            // Cabeçalho
            let header = [];
            const ths = rows[0].querySelectorAll('th');
            ths.forEach(th => {
                header.push(th.innerText.trim());
            });
            csv.push(header.join(';'));

            // Dados
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const cols = row.querySelectorAll('td');
                if (cols.length === 0) continue;

                let rowData = [];
                cols.forEach(col => {
                    // Remove tags HTML e espaços extras
                    let text = col.innerText.trim();
                    // Remove quebras de linha
                    text = text.replace(/\n/g, ' ');
                    // Remove múltiplos espaços
                    text = text.replace(/\s+/g, ' ');
                    rowData.push(text);
                });

                // Verifica se a linha não está vazia
                if (rowData.some(data => data.length > 0)) {
                    csv.push(rowData.join(';'));
                }
            }

            // Criar arquivo CSV com BOM para UTF-8
            const csvContent = '\uFEFF' + csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `${nomeArquivo}_${new Date().toLocaleDateString('pt-BR').replace(/\//g, '-')}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        // ==========================================
        // FUNÇÃO PARA EXPORTAR DADOS COMPLETOS
        // ==========================================
        function exportarDados() {
            if (!confirm('Deseja exportar todos os dados do dashboard para CSV?')) {
                return;
            }

            let csv = [];
            const now = new Date().toLocaleString('pt-BR');
            const diretorNome = '<?= addslashes($dados_diretor['nome'] ?? 'Diretor') ?>';
            const diretorMatricula = '<?= $matricula ?>';

            // Cabeçalho do relatório
            csv.push('=== RELATÓRIO DASHBOARD DIRETOR ===');
            csv.push(`Data Exportação: ${now}`);
            csv.push(`Diretor: ${diretorNome} (${diretorMatricula})`);
            csv.push('');
            csv.push('=== TOTAIS GERAIS ===');
            csv.push(`Saldo Total da Equipe: R$ <?= number_format($saldo_total_equipe ?? 0, 2, ',', '.') ?>`);
            csv.push(`Orçamento Inicial Total: R$ <?= number_format($total_enviado_equipe ?? 0, 2, ',', '.') ?>`);
            csv.push(`Saldo do Diretor: R$ <?= number_format(($dados_diretor['saldo_atual'] ?? 0) + ($dados_diretor['cota_fixa'] ?? 0), 2, ',', '.') ?>`);
            csv.push(`Orçamento Inicial Diretor: R$ <?= number_format($dados_diretor['orc_recebido'] ?? 0, 2, ',', '.') ?>`);
            csv.push(`Cota Fixa Diretor: R$ <?= number_format($dados_diretor['cota_fixa'] ?? 0, 2, ',', '.') ?>`);
            csv.push('');
            csv.push('=== QUANTITATIVO POR PERFIL ===');
            csv.push(`Gerentes: <?= count($gerentes) ?>`);
            csv.push(`Coordenadores: <?= count($coordenadores) ?>`);
            csv.push(`Supervisores: <?= count($supervisores) ?>`);
            csv.push(`Técnicos: <?= count($tecnicos) ?>`);
            csv.push('');

            // Gerentes
            csv.push('=== GERENTES ===');
            csv.push('Nome;Matrícula;Saldo Atual;Cota Fixa;Orçamento Inicial');
            <?php foreach ($gerentes as $gerente): ?>
                csv.push(
                    '<?= addslashes($gerente['nome']) ?>;' +
                    '<?= $gerente['matricula'] ?>;' +
                    'R$ <?= number_format($gerente['saldo_atual'] ?? 0, 2, ',', '.') ?>;' +
                    'R$ <?= number_format($gerente['cota_fixa'] ?? 0, 2, ',', '.') ?>;' +
                    'R$ <?= number_format($gerente['orc_inicial'] ?? 0, 2, ',', '.') ?>'
                );
            <?php endforeach; ?>
            csv.push('');

            // Coordenadores
            csv.push('=== COORDENADORES ===');
            csv.push('Nome;Matrícula;Saldo Atual;Cota Fixa;Orçamento Inicial');
            <?php foreach ($coordenadores as $coordenador): ?>
                csv.push(
                    '<?= addslashes($coordenador['nome']) ?>;' +
                    '<?= $coordenador['matricula'] ?>;' +
                    'R$ <?= number_format($coordenador['saldo_atual'] ?? 0, 2, ',', '.') ?>;' +
                    'R$ <?= number_format($coordenador['cota_fixa'] ?? 0, 2, ',', '.') ?>;' +
                    'R$ <?= number_format($coordenador['orc_inicial'] ?? 0, 2, ',', '.') ?>'
                );
            <?php endforeach; ?>
            csv.push('');

            // Supervisores
            csv.push('=== SUPERVISORES ===');
            csv.push('Nome;Matrícula;Saldo Atual;Orçamento Inicial');
            <?php foreach ($supervisores as $supervisor): ?>
                csv.push(
                    '<?= addslashes($supervisor['nome']) ?>;' +
                    '<?= $supervisor['matricula'] ?>;' +
                    'R$ <?= number_format($supervisor['saldo_atual'] ?? 0, 2, ',', '.') ?>;' +
                    'R$ <?= number_format($supervisor['orc_inicial'] ?? 0, 2, ',', '.') ?>'
                );
            <?php endforeach; ?>
            csv.push('');

            // Técnicos
            csv.push('=== TÉCNICOS ===');
            csv.push('Nome;Matrícula;Saldo Atual;Orçamento Inicial');
            <?php foreach ($tecnicos as $tecnico): ?>
                csv.push(
                    '<?= addslashes($tecnico['nome']) ?>;' +
                    '<?= $tecnico['matricula'] ?>;' +
                    'R$ <?= number_format($tecnico['saldo_atual'] ?? 0, 2, ',', '.') ?>;' +
                    'R$ <?= number_format($tecnico['orc_inicial'] ?? 0, 2, ',', '.') ?>'
                );
            <?php endforeach; ?>
            csv.push('');

            // Rodapé
            csv.push('=== FIM DO RELATÓRIO ===');
            csv.push(`Total de registros: <?= count($gerentes) + count($coordenadores) + count($supervisores) + count($tecnicos) ?>`);

            // Criar arquivo CSV
            const csvContent = '\uFEFF' + csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            const dataStr = new Date().toISOString().slice(0, 10);
            link.setAttribute('download', `Dashboard_Diretor_${dataStr}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }
    </script>

</body>

</html>
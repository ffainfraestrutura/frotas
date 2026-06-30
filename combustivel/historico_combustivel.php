<?php
// remanejamento/historico.php
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

$result_colaboradores = mysqli_query($conn, $sql_colaboradores);
$colaboradores = mysqli_fetch_all($result_colaboradores, MYSQLI_ASSOC);

// Busca o histórico completo (sem filtro de acao) - ORDENADO DO MAIS ANTIGO PARA O MAIS NOVO
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
                  LEFT JOIN tbusuario u ON h.matricula = u.matricula
                  LEFT JOIN tbusuario ua ON h.matricula_autor = ua.matricula";

// Adiciona filtro de colaborador se selecionado
if (!empty($filtro_matricula)) {
    $sql_historico .= " WHERE h.matricula = '$filtro_matricula' AND h.acao IN ('remanejamento', 'cota_extra')";
} else {
    $sql_historico .= " WHERE h.acao IN ('remanejamento', 'cota_extra')";
}

// ORDENA DO MAIS ANTIGO PARA O MAIS NOVO
$sql_historico .= " ORDER BY h.data ASC, h.id ASC";

$result_historico = mysqli_query($conn, $sql_historico);
$historico = mysqli_fetch_all($result_historico, MYSQLI_ASSOC);

// Busca dados do colaborador selecionado
$dados_colaborador = null;
$saldo_inicial_data = null;

if (!empty($filtro_matricula)) {
    // Busca dados do colaborador
    $sql_colab = "SELECT 
                    u.matricula,
                    u.nome,
                    s.saldo_real_calculado as saldo_atual,
                    s.kmproj,
                    s.totalextra,
                    s.data as ultima_atualizacao
                  FROM 
                    tbusuario u
                  LEFT JOIN tbsaldo s ON u.matricula = s.matricula
                  WHERE 
                    u.matricula = '$filtro_matricula'
                  ORDER BY 
                    s.data DESC
                  LIMIT 1";
    
    $result_colab = mysqli_query($conn, $sql_colab);
    $dados_colaborador = mysqli_fetch_assoc($result_colab);
    
    // Busca o saldo inicial do colaborador (primeiro registro da tbsaldo)
    $sql_saldo_inicial = "SELECT 
                            s.saldo_real_calculado as saldo_inicial,
                            s.data as data_saldo
                          FROM 
                            tbsaldo s
                          WHERE 
                            s.matricula = '$filtro_matricula'
                          ORDER BY 
                            s.data ASC
                          LIMIT 1";
    
    $result_inicial = mysqli_query($conn, $sql_saldo_inicial);
    $saldo_inicial_data = mysqli_fetch_assoc($result_inicial);
} else {
    // Busca saldo inicial de todos os colaboradores envolvidos
    $sql_saldo_inicial = "SELECT 
                            s.matricula,
                            MIN(s.data) as primeira_data,
                            (SELECT saldo_real_calculado 
                             FROM tbsaldo s2 
                             WHERE s2.matricula = s.matricula 
                             ORDER BY s2.data ASC 
                             LIMIT 1) as saldo_inicial
                          FROM 
                            tbsaldo s
                          GROUP BY 
                            s.matricula";
    
    $result_inicial = mysqli_query($conn, $sql_saldo_inicial);
    $saldos_iniciais = [];
    while ($row = mysqli_fetch_assoc($result_inicial)) {
        $saldos_iniciais[$row['matricula']] = $row['saldo_inicial'];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Histórico de Remanejamento - Portal FFA</title>
    <link rel="icon" type="image/png" href="../src/images/favicon.png" />
    <link href="../src/css/styles.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    
    <style>
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
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -10px;
            top: 5px;
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
            padding: 15px;
        }
        .card-resumo {
            transition: transform 0.2s;
        }
        .card-resumo:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .timeline-item .saldo-acumulado {
            font-size: 0.9em;
            color: #6c757d;
        }
        .timeline-item .saldo-acumulado strong {
            color: #007bff;
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
                            <i class="fas fa-history me-2"></i>Histórico de Combustível
                        </h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item active">Acompanhe todas as transferências de saldo</li>
                        </ol>
                    </div>
                    <a href="javascript:history.back()" class="btn btn-outline-secondary mt-4">
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

                <?php if ($perfil == 4): ?>
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
                                        <select name="matricula" class="form-select" onchange="this.form.submit()">
                                            <option value="">-- Todos os Colaboradores --</option>
                                            <?php foreach ($colaboradores as $colab): ?>
                                                <option value="<?= $colab['matricula'] ?>" <?= ($filtro_matricula == $colab['matricula']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($colab['nome'] ?? $colab['matricula']) ?> 
                                                    (<?= $colab['matricula'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($filtro_matricula) && $dados_colaborador): ?>
                    <!-- Info do Colaborador -->
                    <div class="info-colaborador">
                        <div class="row">
                            <div class="col-md-4">
                                <h4><i class="fas fa-user me-2"></i><?= htmlspecialchars($dados_colaborador['nome'] ?? $filtro_matricula) ?></h4>
                                <p class="mb-0"><i class="fas fa-id-card me-2"></i>Matrícula: <?= $dados_colaborador['matricula'] ?></p>
                            </div>
                            <div class="col-md-4">
                                <h5><i class="fas fa-wallet me-2"></i>Saldo Atual</h5>
                                <p class="h3">R$ <?= number_format($dados_colaborador['saldo_atual'] ?? 0, 2, ',', '.') ?></p>
                                <?php if ($dados_colaborador['kmproj']): ?>
                                    <small><i class="fas fa-road me-1"></i>KM Projetado: <?= number_format($dados_colaborador['kmproj'], 0, ',', '.') ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <h5><i class="fas fa-calendar me-2"></i>Última Atualização</h5>
                                <p><?= $dados_colaborador['ultima_atualizacao'] ? date('d/m/Y H:i', strtotime($dados_colaborador['ultima_atualizacao'])) : '-' ?></p>
                                <?php if ($saldo_inicial_data): ?>
                                    <small><i class="fas fa-flag me-1"></i>Saldo Inicial: R$ <?= number_format($saldo_inicial_data['saldo_inicial'] ?? 0, 2, ',', '.') ?></small>
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
                        <?php if (!empty($filtro_matricula)): ?>
                            <span class="badge bg-primary ms-2">Individual</span>
                        <?php else: ?>
                            <span class="badge bg-secondary ms-2">Geral</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (count($historico) > 0 || !empty($filtro_matricula)): ?>
                            <div class="timeline">
                                <?php 
                                $saldo_acumulado = 0;
                                $primeiro_item = true;
                                
                                // Se tem filtro de colaborador, mostra o saldo inicial
                                if (!empty($filtro_matricula) && $saldo_inicial_data):
                                    $saldo_acumulado = floatval($saldo_inicial_data['saldo_inicial'] ?? 0);
                                ?>
                                    <!-- Item: Saldo Inicial -->
                                    <div class="timeline-item inicial">
                                        <div class="row">
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
                                                    R$ <?= number_format($saldo_acumulado, 2, ',', '.') ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php 
                                endif;
                                
                                // Se não tem filtro, pega o saldo inicial de cada um
                                if (empty($filtro_matricula)):
                                    $saldos_por_matricula = [];
                                    foreach ($historico as $h):
                                        if (!isset($saldos_por_matricula[$h['matricula']])) {
                                            $saldos_por_matricula[$h['matricula']] = $saldos_iniciais[$h['matricula']] ?? 0;
                                        }
                                    endforeach;
                                endif;
                                
                                // Mostra os itens do histórico (do mais antigo para o mais novo)
                                foreach ($historico as $h): 
                                    // Se tem filtro, atualiza o saldo acumulado
                                    if (!empty($filtro_matricula)) {
                                        if ($h['operacao'] == 'adicao') {
                                            $saldo_acumulado += floatval($h['valor']);
                                        } else {
                                            $saldo_acumulado -= floatval($h['valor']);
                                        }
                                    }
                                    
                                    $classe_operacao = $h['operacao'] == 'adicao' ? 'adicao' : 'retirada';
                                    $icone = $h['operacao'] == 'adicao' ? 'fa-arrow-down' : 'fa-arrow-up';
                                    $classe_valor = $h['operacao'] == 'adicao' ? 'valor-positivo' : 'valor-negativo';
                                    $sinal = $h['operacao'] == 'adicao' ? '+' : '-';
                                ?>
                                    <div class="timeline-item <?= $classe_operacao ?>">
                                        <div class="row">
                                            <div class="col-md-2">
                                                <strong><?= date('d/m/Y H:i', strtotime($h['data'])) ?></strong>
                                            </div>
                                            <div class="col-md-3">
                                                <span class="badge <?= $h['operacao'] == 'adicao' ? 'badge-adicao' : 'badge-retirada' ?>">
                                                    <i class="fas <?= $icone ?>"></i> 
                                                    <?= ucfirst($h['operacao']) ?>
                                                </span>
                                                <span class="badge <?= $h['acao'] == 'remanejamento' ? 'badge-remanejamento' : 'badge-outro' ?> ms-1">
                                                    <?= $h['acao'] == 'cota_extra' ? 'Cota Extra' : ucfirst($h['acao']) ?>
                                                </span>
                                            </div>
                                            <div class="col-md-2">
                                                <span class="<?= $classe_valor ?>">
                                                    <?= $sinal ?> R$ <?= number_format($h['valor'], 2, ',', '.') ?>
                                                </span>
                                            </div>
                                            <div class="col-md-3">
                                                <small>
                                                    <span class="text-muted"></span> R$ <?= number_format($h['valor_anterior'], 2, ',', '.') ?>
                                                    <i class="fas fa-arrow-right mx-1"></i>
                                                    <span class="text-primary">R$ <?= number_format($h['valor_atual'], 2, ',', '.') ?></span>
                                                </small>
                                            </div>
                                            <div class="col-md-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?= htmlspecialchars($h['nome_autor'] ?? $h['matricula_autor']) ?>
                                                </small>
                                                <?php if (empty($filtro_matricula)): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?= htmlspecialchars($h['nome_colaborador'] ?? $h['matricula']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (!empty($filtro_matricula) && count($historico) > 0): ?>
                                    <!-- Item: Saldo Final -->
                                    <div class="timeline-item" style="border-left-color: #28a745;">
                                        <div class="row">
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
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Nenhum registro encontrado</p>
                                <a href="index.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Fazer primeira transferência
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
        <?php // include "footer.php"; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="../src/js/scripts.js"></script>
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js"></script>
    
    <script>
        $(document).ready(function() {
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
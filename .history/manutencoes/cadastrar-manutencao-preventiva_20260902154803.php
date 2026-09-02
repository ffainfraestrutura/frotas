<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();

date_default_timezone_set('America/Sao_Paulo');

$perfilLogado = (string) ($autofrotaSessao['perfil'] ?? '');
$matriculaLogada = (string) (($autofrotaSessao['matricula'] ?? '') !== '' ? $autofrotaSessao['matricula'] : ($_SESSION['usuario'] ?? ''));
$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');
$databaseCorp = (string) ($autofrotaSessao['databaseCorp'] ?? '');
$podeEditar = $perfilLogado === '4';
$nomeSolicitante = (string) ($autofrotaSessao['usuario'] ?? $_SESSION['nome'] ?? 'Usuário');
$ccustoSolicitante = '';
if ($conn instanceof mysqli && $databaseCorp !== '') {
    $nomeSolicitante = autofrotaNomeExibicaoPorMatricula($conn, $databaseCorp, $matriculaLogada, $nomeSolicitante);

    $stmtSolicitante = mysqli_prepare(
        $conn,
        "SELECT ccusto FROM `{$databaseCorp}`.`tbfuncionario` WHERE matricula = ? LIMIT 1"
    );
    if ($stmtSolicitante) {
        mysqli_stmt_bind_param($stmtSolicitante, 's', $matriculaLogada);
        mysqli_stmt_execute($stmtSolicitante);
        $resSolicitante = mysqli_stmt_get_result($stmtSolicitante);
        $dadosSolicitante = $resSolicitante ? mysqli_fetch_assoc($resSolicitante) : null;
        $ccustoSolicitante = trim((string) ($dadosSolicitante['ccusto'] ?? ''));
        mysqli_stmt_close($stmtSolicitante);
    }
}

$database = (isset($databaseName) && preg_match('/^[A-Za-z0-9_]+$/', (string) $databaseName))
    ? (string) $databaseName
    : 'bdautofrotas';

$id = (int) ($_GET['idtbmanprev'] ?? $_POST['idtbmanprev'] ?? 0);
$placaRecebida = strtoupper(trim((string) ($_GET['placa'] ?? $_POST['placa'] ?? '')));
$placaRecebida = str_replace(['-', ' '], '', $placaRecebida);
$oficinaRecebida = trim((string) ($_GET['oficina'] ?? ''));

$mensagem = (string) ($_GET['msg'] ?? '');

$man = null;

if ($id > 0) {
    $urlEditar = 'editar-manutencao.php?idtbmanprev=' . $id;
    if ($placaRecebida !== '') {
        $urlEditar .= '&placa=' . rawurlencode($placaRecebida);
    }

    header('Location: ' . $urlEditar);
    exit;
}

if ($placaRecebida !== '') {
    // Assim como no cadastro legado, os dados iniciais devem vir do veículo,
    // e não de uma manutenção anterior da mesma placa.
    $sqlVeiculo = "SELECT v.placa,
                          v.hodometro,
                          COALESCE(NULLIF(vm.modelo, ''), v.modelo) AS modelo,
                          v.oficina,
                          COALESCE(NULLIF(f.ccusto, ''), v.ccusto) AS ccusto
                     FROM `{$database}`.`tbveiculo` v
                LEFT JOIN `{$database}`.`tbveiculomodelo` vm
                       ON vm.idtbmodeloveic = v.modelo
                LEFT JOIN `{$database}`.`tbfuncionario` f
                       ON f.matricula = v.matcond
                    WHERE REPLACE(REPLACE(UPPER(TRIM(v.placa)), '-', ''), ' ', '') = ?
                    LIMIT 1";
    $stmt = mysqli_prepare($conn, $sqlVeiculo);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $placaRecebida);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $man = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
    }
}

if (!$man) {
    $man = ['placa' => $placaRecebida];
    if ($placaRecebida === '') {
        $mensagem = 'Selecione uma placa para cadastrar a manutenção preventiva.';
    } else {
        $mensagem = 'Não foi possível localizar os dados do veículo selecionado.';
    }
}

$man['atualizadoem'] = date('Y-m-d');
$man['solicitante'] = $nomeSolicitante;
$man['ccusto'] = $ccustoSolicitante;
if ($oficinaRecebida !== '') {
    $man['oficina'] = $oficinaRecebida;
}

$planosManutencao = [];
$sqlPlanos = "SELECT codigo, descricao FROM `{$database}`.`tbplanomanutencao` ORDER BY descricao";
$stmtPlanos = mysqli_prepare($conn, $sqlPlanos);
if ($stmtPlanos) {
    mysqli_stmt_execute($stmtPlanos);
    $resPlanos = mysqli_stmt_get_result($stmtPlanos);
    while ($resPlanos && ($rowPlano = mysqli_fetch_assoc($resPlanos))) {
        $planosManutencao[] = $rowPlano;
    }
    mysqli_stmt_close($stmtPlanos);
}

$statusManutencao = [];
$basesStatus = [$database, 'autofrotas'];
foreach (array_values(array_unique($basesStatus)) as $baseStatus) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $baseStatus)) {
        continue;
    }

    $sqlStatus = "SELECT descricao FROM `{$baseStatus}`.`tbmanutencaostatus` WHERE descricao IS NOT NULL AND descricao <> '' ORDER BY id";
    $resStatus = mysqli_query($conn, $sqlStatus);
    if (!$resStatus) {
        continue;
    }

    while ($rowStatus = mysqli_fetch_assoc($resStatus)) {
        $descricaoStatus = trim((string) ($rowStatus['descricao'] ?? ''));
        if ($descricaoStatus !== '') {
            $statusManutencao[$descricaoStatus] = $descricaoStatus;
        }
    }
    mysqli_free_result($resStatus);

    if ($statusManutencao !== []) {
        break;
    }
}

if ($statusManutencao === []) {
    $statusManutencao = [
        'ABERTO' => 'ABERTO',
        'CONCLUIDO' => 'CONCLUIDO',
        'CANCELADO' => 'CANCELADO',
    ];
}

$etapasManutencao = [
    'ABERTO' => 'ABERTO',
    'AGENDADO' => 'AGENDADO',
    'EM APROVAÇÃO' => 'EM APROVAÇÃO',
    'ORDEM DE SERVIÇO' => 'ORDEM DE SERVIÇO',
    'ENVIADO AO FORNECEDOR' => 'ENVIADO AO FORNECEDOR',
    'EM MANUTENÇÃO' => 'EM MANUTENÇÃO',
    'CONCLUÍDO' => 'CONCLUÍDO',
    'CANCELADO' => 'CANCELADO',
];

$fornecedoresManutencao = [
    'FFA INFRAESTRUTURA E SERVIÇOS' => 'FFA INFRAESTRUTURA E SERVIÇOS',
    'MOVIDA' => 'MOVIDA',
    'LOCALIZA' => 'LOCALIZA',
    'UNIDAS' => 'UNIDAS',
    'ELEVEN' => 'ELEVEN',
    'PRÓPRIO' => 'PRÓPRIO',
    'LOCADO' => 'LOCADO',
];

$formasPagamentoManutencao = [
    'BOLETO' => 'BOLETO',
    'FATURADO' => 'FATURADO',
    'PARCELADO' => 'PARCELADO',
    'A PRAZO - 15 DIAS' => 'A PRAZO - 15 DIAS',
    'A PRAZO - 15/30 DIAS' => 'A PRAZO - 15/30 DIAS',
    'A PRAZO - 15/30/45 DIAS' => 'A PRAZO - 15/30/45 DIAS',
];

$centrosCusto = [];
$basesCcusto = [$database, 'autofrotas'];
foreach (array_values(array_unique($basesCcusto)) as $baseCcusto) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $baseCcusto)) {
        continue;
    }

    $sqlCcusto = "SELECT descricao AS ccusto FROM `{$baseCcusto}`.`tbccusto` WHERE descricao IS NOT NULL AND descricao <> '' ORDER BY descricao";
    $resCcusto = mysqli_query($conn, $sqlCcusto);
    if (!$resCcusto) {
        continue;
    }

    while ($rowCcusto = mysqli_fetch_assoc($resCcusto)) {
        $ccustoValor = trim((string) ($rowCcusto['ccusto'] ?? ''));
        if ($ccustoValor !== '') {
            $centrosCusto[$ccustoValor] = $ccustoValor;
        }
    }
    mysqli_free_result($resCcusto);

    if ($centrosCusto !== []) {
        break;
    }
}

if ($ccustoSolicitante !== '') {
    $centrosCusto[$ccustoSolicitante] = $ccustoSolicitante;
    natcasesort($centrosCusto);
}

$oficinas = [];
$sqlOficinas = "SELECT nome FROM `{$database}`.`tboficina` WHERE nome IS NOT NULL AND TRIM(nome) <> '' ORDER BY nome";
$resOficinas = mysqli_query($conn, $sqlOficinas);
if ($resOficinas) {
    while ($rowOficina = mysqli_fetch_assoc($resOficinas)) {
        $nomeOficina = trim((string) ($rowOficina['nome'] ?? ''));
        if ($nomeOficina !== '') {
            $oficinas[$nomeOficina] = $nomeOficina;
        }
    }
    mysqli_free_result($resOficinas);
}

$oficinaAtual = trim((string) ($man['oficina'] ?? ''));
if ($oficinaAtual !== '') {
    $oficinas[$oficinaAtual] = $oficinaAtual;
    natcasesort($oficinas);
}
?><!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Cadastrar Manutenção Preventiva</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script><style>body{background:#fff;color:#000;font-size:12px}.section-title{font-size:1rem;font-weight:700;margin:0}.section-wrap{border-top:1px solid #dee2e6;padding-top:14px;margin-top:6px}.form-control[readonly],.form-select:disabled{background-color:#e9ecef;opacity:1}.form-control,.form-select{font-size:12px;border-radius:2px}.btn{font-size:12px;border-radius:3px;padding:6px 10px}.form-actions-floating{position:fixed;right:24px;bottom:24px;z-index:1030;padding:12px;background:rgba(255,255,255,.96);border:1px solid #dee2e6;border-radius:12px;box-shadow:0 4px 18px rgba(0,0,0,.18)}@media (max-width: 575.98px){.form-actions-floating{right:12px;bottom:12px}}</style></head>
<body class="sb-nav-fixed">
<?php autofrotaMenu(); ?>
<div id="layoutSidenav_content">
<main class="container py-4">
<h1 class="h1 pt-2 pb-2 m-auto text-center">Cadastrar uma nova Manutenção</h1>
<!-- <p class="text-center text-muted mb-3">Edição #<?= esc((string)$man['idtbmanprev']) ?></p> -->
<?php if ($mensagem !== ''): ?><div class="alert alert-info"><?= esc($mensagem) ?></div><?php endif; ?>
<form method="post" action="control/salvar-manutencao.php" class="card card-body m-auto" style="width: 80%;" enctype="multipart/form-data">
<input type="hidden" name="idtbmanprev" value="<?= esc((string)$id) ?>">
<?php if (!$podeEditar): ?>
<div class="alert alert-warning">Visualização habilitada para seu perfil. Edição disponível apenas para perfis autorizados.</div>
<?php endif; ?>

<div class="container-fluid px-4" style="width: 100%;">
    <div class="row g-3">
        <div class="col-12">
            <div class="p-2 rounded bg-light border">
                <p class="section-title">Identificação</p>
            </div>
        </div>
        <div class="col-md-2">
            <label class="form-label">Placa:<span style="color: red;">*</span></label>
            <input class="form-control" name="placa" value="<?= esc($man['placa'] ?? '') ?>" readonly aria-readonly="true" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Plano manutenção: <span style="color: red;">*</span></label>
            <select class="form-select" name="manutencao" required>
                <option value="">Selecione...</option>
                <?php foreach ($planosManutencao as $plano): ?>
                    <option value="<?= esc($plano['codigo'] ?? '') ?>" <?= (($man['tipo'] ?? '') === ($plano['codigo'] ?? '')) ? 'selected' : '' ?>><?= esc($plano['descricao'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Hodômetro:<span style="color: red;">*</span></label>
            <input type="number" class="form-control" name="hodometro" min="0" max="1000000" step="1" inputmode="numeric" value="<?= esc((string)($man['hodometro'] ?? '')) ?>" readonly aria-readonly="true" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Centro de custo: <span style="color: red;">*</span></label>
            <input type="hidden" name="ccusto" value="<?= esc($man['ccusto'] ?? '') ?>">
            <select class="form-select" disabled aria-disabled="true">
                <option value="">Selecione o centro de custo</option>
                <?php foreach ($centrosCusto as $ccustoOpcao): ?>
                    <option value="<?= esc($ccustoOpcao) ?>" <?= (($man['ccusto'] ?? '') === $ccustoOpcao) ? 'selected' : '' ?>><?= esc($ccustoOpcao) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Atualizado em</label>
            <input type="date" class="form-control" name="atualizadoem" value="<?= esc($man['atualizadoem']) ?>" readonly aria-readonly="true">
        </div>
        <div class="col-md-3">
            <label class="form-label">Solicitante</label>
            <input class="form-control" name="solicitante" value="<?= esc($man['solicitante'] ?? '') ?>" readonly aria-readonly="true">
        </div>

        <div class="col-12 section-wrap">
            <div class="p-2 rounded bg-light border">
                <p class="section-title">Andamento da manutenção</p>
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label">Status da manutenção:<span style="color: red;">*</span></label>
            <select class="form-select" name="status" required>
                <option value="">Selecione...</option>
                <?php foreach ($statusManutencao as $statusOpcao): ?>
                    <option value="<?= esc($statusOpcao) ?>" <?= (($man['status'] ?? '') === $statusOpcao) ? 'selected' : '' ?>><?= esc($statusOpcao) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Etapa da manutenção</label>
            <select class="form-select" name="etapa">
                <option value="">Selecione...</option>
                <?php foreach ($etapasManutencao as $etapaOpcao): ?>
                    <option value="<?= esc($etapaOpcao) ?>" <?= (($man['etapa'] ?? '') === $etapaOpcao) ? 'selected' : '' ?>><?= esc($etapaOpcao) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Data de ocorrência</label>
            <input type="date" class="form-control" name="dataocorrencia" value="<?= esc(substr((string)($man['dataocorrencia'] ?? ''),0,10)) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Modelo</label>
            <input class="form-control" name="modelo" value="<?= esc($man['modelo'] ?? '') ?>" readonly aria-readonly="true">
        </div>
        <div class="col-md-4">
            <label class="form-label">Fornecedor da manutenção</label>
            <select class="form-select" name="fornman">
                <option value="">Selecione...</option>
                <?php foreach ($fornecedoresManutencao as $fornecedorOpcao): ?>
                    <option value="<?= esc($fornecedorOpcao) ?>" <?= (($man['fornman'] ?? '') === $fornecedorOpcao) ? 'selected' : '' ?>><?= esc($fornecedorOpcao) ?></option>
                <?php endforeach; ?>
                <?php
                $fornecedorAtual = trim((string) ($man['fornman'] ?? ''));
                if ($fornecedorAtual !== '' && !isset($fornecedoresManutencao[$fornecedorAtual])):
                ?>
                    <option value="<?= esc($fornecedorAtual) ?>" selected><?= esc($fornecedorAtual) ?></option>
                <?php endif; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label">Oficina</label>
            <div class="input-group">
                <select class="form-select" name="oficina">
                    <option value="">Selecione uma oficina</option>
                    <?php foreach ($oficinas as $oficinaOpcao): ?>
                        <option value="<?= esc($oficinaOpcao) ?>" <?= $oficinaAtual === $oficinaOpcao ? 'selected' : '' ?>><?= esc($oficinaOpcao) ?></option>
                    <?php endforeach; ?>
                </select>
                <a class="btn btn-outline-success" href="adicionar-oficina.php?placa=<?= rawurlencode($placaRecebida) ?>" title="Adicionar oficina" aria-label="Adicionar oficina">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                </a>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Descrição</label>
            <textarea class="form-control" name="descricao" rows="3"><?= esc($man['descricao'] ?? '') ?></textarea>
        </div>

        <div class="col-12 section-wrap">
            <div class="p-2 rounded bg-light border">
                <p class="section-title">Agendamento e execução</p>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Data de agendamento:<span style="color: red;">*</span></label>
            <input type="date" class="form-control" name="dataagendamento" value="<?= esc(substr((string)($man['dataagendamento'] ?? ''),0,10)) ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Hora do agendamento</label>
            <input type="time" class="form-control" name="horaagendamento" value="<?= esc($man['horaagendamento'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Anexar arquivo</label>
            <input type="file" class="form-control" name="arquivo">
        </div>
        <div class="col-md-4">
            <label class="form-label">Previsão de saída: <span style="color: red;">*</span></label>
            <input type="date" class="form-control" name="prevsaida" value="<?= esc(substr((string)($man['prevsaida'] ?? ''),0,10)) ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Data de entrada:<span style="color: red;">*</span></label>
            <input type="date" class="form-control" name="dataentrada" value="<?= esc(substr((string)($man['dataentrada'] ?? ''),0,10)) ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Data de retirada</label>
            <input type="date" class="form-control" name="dataretirada" value="<?= esc(substr((string)($man['dataretirada'] ?? ''),0,10)) ?>">
        </div>

        <div class="col-12 section-wrap">
            <div class="p-2 rounded bg-light border">
                <p class="section-title">Financeiro</p>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Tipo de pagamento</label>
            <input class="form-control" name="tipopagamento" value="<?= esc($man['tipopagamento'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Reembolso aprovado</label>
            <select class="form-select" name="reembolsoaprov">
                <option value="0">Não</option>
                <option value="1">Sim</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Valor reembolso</label>
            <input type="number" class="form-control" name="valorreembolso" min="0" max="1000000" step="0.01" inputmode="decimal" value="<?= esc($man['valorreembolso'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Valor oficina</label>
            <input type="number" class="form-control" name="valoroficina" min="0" max="1000000" step="0.01" inputmode="decimal" value="<?= esc($man['valoroficina'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Valor do desconto</label>
            <input type="number" class="form-control" name="valordesconto" min="0" max="1000000" step="0.01" inputmode="decimal" value="<?= esc($man['valordesconto'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Valor mão de obra</label>
            <input type="number" class="form-control" name="valormaoobra" min="0" max="1000000" step="0.01" inputmode="decimal" value="<?= esc($man['valormaoobra'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Valor material</label>
            <input type="number" class="form-control" name="valormaterial" min="0" max="1000000" step="0.01" inputmode="decimal" value="<?= esc($man['valormaterial'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Valor transporte</label>
            <input type="number" class="form-control" name="valortransp" min="0" max="1000000" step="0.01" inputmode="decimal" value="<?= esc($man['valortransp'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Outros valores</label>
            <input type="number" class="form-control" name="outrosvalor" min="0" max="1000000" step="0.01" inputmode="decimal" value="<?= esc($man['outrosvalor'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Descontado do condutor</label>
            <select class="form-select" name="descontarcond">
                <option value="0">Não</option>
                <option value="1">Sim</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Data de vencimento</label>
            <input type="date" class="form-control" name="datavencimento" value="<?= esc(substr((string)($man['datavencimento'] ?? ''),0,10)) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Data de pagamento</label>
            <input type="date" class="form-control" name="datapagamento" value="<?= esc(substr((string)($man['datapagamento'] ?? ''),0,10)) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Forma de pagamento</label>
            <select class="form-select" name="formapagam">
                <option value="">Selecione...</option>
                <?php foreach ($formasPagamentoManutencao as $formaPagamentoOpcao): ?>
                    <option value="<?= esc($formaPagamentoOpcao) ?>" <?= (($man['formapagam'] ?? '') === $formaPagamentoOpcao) ? 'selected' : '' ?>><?= esc($formaPagamentoOpcao) ?></option>
                <?php endforeach; ?>
                <?php
                $formaPagamentoAtual = trim((string) ($man['formapagam'] ?? ''));
                if ($formaPagamentoAtual !== '' && !isset($formasPagamentoManutencao[$formaPagamentoAtual])):
                ?>
                    <option value="<?= esc($formaPagamentoAtual) ?>" selected><?= esc($formaPagamentoAtual) ?></option>
                <?php endif; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Condição de pagamento</label>
            <input class="form-control" name="condicaopag" value="<?= esc($man['condicaopag'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Número de parcelas</label>
            <input type="number" class="form-control" name="numparc" min="1" max="1000000" step="1" inputmode="numeric" value="<?= esc($man['numparc'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Valor de parcela</label>
            <input type="number" class="form-control" name="valorparcela" min="0" max="1000000" step="0.01" inputmode="decimal" value="<?= esc($man['valorparcela'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Data da primeira parcela</label>
            <input type="date" class="form-control" name="dataprimparc" value="<?= esc(substr((string)($man['dataprimparc'] ?? ''),0,10)) ?>">
        </div>

        <div class="col-12 section-wrap">
            <div class="p-2 rounded bg-light border">
                <p class="section-title">Encerramento</p>
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label">Protocolo</label>
            <input class="form-control" name="protocolo" value="<?= esc($man['protocolo'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Data de conclusão</label>
            <input type="date" class="form-control" name="dataconclusao" value="<?= esc(substr((string)($man['dataconclusao'] ?? ''),0,10)) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Placa anterior</label>
            <input class="form-control" name="placaanterior" value="<?= esc($man['placaanterior'] ?? '') ?>">
        </div>
        <div class="col-md-12">
            <label class="form-label">Observação</label>
            <textarea class="form-control" name="observacao" rows="2"><?= esc($man['observacao'] ?? '') ?></textarea>
        </div>
    </div>
    <div class="form-actions-floating d-flex gap-2" role="group" aria-label="Ações do cadastro">
        <?php if ($podeEditar): ?>
            <button class="btn btn-success" name="salvar" value="1" type="submit">Confirmar cadastro de manutenção</button>
        <?php endif; ?>
        <a class="btn btn-secondary" href="solicitar-manutencao-preventiva.php" onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href='solicitar-manutencao-preventiva.php'; } return false;"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Voltar</a>
        <a class="btn btn-danger" href="solicitar-manutencao-preventiva.php">Cancelar cadastro</a>
    </div>
</div>
</form>
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>document.getElementById('sidebarToggle')?.addEventListener('click', function(event){event.preventDefault();document.body.classList.toggle('sb-sidenav-toggled');});</script>
</body>
</html>
<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../control/conecta.php';
exigirLogin();

$perfilLogado = (string) ($_SESSION['perfil'] ?? '');
$matriculaLogada = (string) ($_SESSION['matricula'] ?? $_SESSION['usuario'] ?? '');
$podeEditar = $perfilLogado === '4';

function esc(?string $v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

$database = (isset($databaseName) && preg_match('/^[A-Za-z0-9_]+$/', (string) $databaseName))
    ? (string) $databaseName
    : 'bdautofrotas';

$id = (int) ($_POST['num'] ?? $_GET['num'] ?? $_POST['numero'] ?? $_GET['numero'] ?? $_POST['idtbmanprev'] ?? $_GET['idtbmanprev'] ?? 0);
$placaRecebida = strtoupper(trim((string) ($_POST['placa'] ?? $_GET['placa'] ?? '')));
$placaRecebida = str_replace(['-', ' '], '', $placaRecebida);

$mensagem = (string) ($_GET['msg'] ?? '');

$man = null;

if ($id > 0) {
    $sqlById = "SELECT * FROM `{$database}`.`tbmanprev` WHERE idtbmanprev = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sqlById);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $man = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
    }
}

if (!$man && $placaRecebida !== '') {
    $sqlByPlaca = "SELECT * FROM `{$database}`.`tbmanprev` WHERE placa = ? ORDER BY (status = 'ABERTO') DESC, idtbmanprev DESC LIMIT 1";
    $stmt = mysqli_prepare($conn, $sqlByPlaca);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $placaRecebida);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $man = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if ($man) {
            $id = (int) ($man['idtbmanprev'] ?? 0);
        }
    }
}

if (!$man) {
    // Nenhuma manutenção encontrada — inicializa array vazio
    // para permitir abrir o formulário em branco quando aplicável.
    $man = [];
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

$docAtual = trim((string) ($man['doc'] ?? ''));
$hrefDocAtual = '';
if ($docAtual !== '') {
    $docNormalizado = str_replace('\\', '/', $docAtual);
    if (preg_match('/^https?:\/\//i', $docNormalizado)) {
        $hrefDocAtual = $docNormalizado;
    } else {
        while (strpos($docNormalizado, '../') === 0) {
            $docNormalizado = substr($docNormalizado, 3);
        }
        $docNormalizado = ltrim($docNormalizado, '/');
        if (strpos($docNormalizado, './') !== 0) {
            $docNormalizado = './' . $docNormalizado;
        }
        $hrefDocAtual = $docNormalizado;
    }
}
?><!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Editar Manutenção Preventiva</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script><style>body{background:#fff;color:#000;font-size:12px}.section-title{font-size:1rem;font-weight:700;margin:0;display:flex;align-items:center;gap:8px}.section-wrap{border-top:1px solid #dee2e6;padding-top:14px;margin-top:6px}.section-title i{color:#0d6efd}.form-control[readonly],.form-select:disabled{background-color:#e9ecef;opacity:1}.form-control,.form-select{font-size:12px;border-radius:2px}.btn{font-size:12px;border-radius:3px;padding:6px 10px}.form-actions-floating{position:fixed;right:24px;bottom:24px;z-index:1030;padding:12px;background:rgba(255,255,255,.96);border:1px solid #dee2e6;border-radius:12px;box-shadow:0 4px 18px rgba(0,0,0,.18)}@media (max-width: 575.98px){.form-actions-floating{right:12px;bottom:12px}}</style></head>
<body class="sb-nav-fixed bg-light">
    <?php include __DIR__ . '/../includes/menu_superior_simples.php'; ?>
<div id="layoutSidenav_content">
<main class="container py-4">
<h1 class="h1 pt-2 pb-2 m-auto text-center">Editar Manutenção</h1>
<!-- <p class="text-center text-muted mb-3"><strong>Editar</strong> uma manutenção já cadastrada.</p> -->
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
                <p class="section-title"><i class="fa-solid fa-id-card" aria-hidden="true"></i><span>Identificação</span></p>
            </div>
        </div>
        <div class="col-md-2">
            <label class="form-label">Placa:<span style="color: red;">*</span></label>
            <input class="form-control" name="placa" value="<?= esc($man['placa'] ?? '') ?>" required>
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
            <input class="form-control" name="hodometro" value="<?= esc((string)($man['hodometro'] ?? '')) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Centro de custo: <span style="color: red;">*</span></label>
            <select class="form-select" name="ccusto" required>
                <option value="">Selecione o centro de custo</option>
                <?php foreach ($centrosCusto as $ccustoOpcao): ?>
                    <option value="<?= esc($ccustoOpcao) ?>" <?= (($man['ccusto'] ?? '') === $ccustoOpcao) ? 'selected' : '' ?>><?= esc($ccustoOpcao) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Atualizado em</label>
            <input type="date" class="form-control" name="atualizadoem" value="<?= esc(substr((string)($man['atualizadoem'] ?? ''),0,10)) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Solicitante</label>
            <input class="form-control" name="solicitante" value="<?= esc($man['solicitante'] ?? '') ?>">
        </div>

        <div class="col-12 section-wrap">
            <div class="p-2 rounded bg-light border">
                <p class="section-title"><i class="fa-solid fa-list-check" aria-hidden="true"></i><span>Andamento da manutenção</span></p>
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
            <input class="form-control" name="etapa" value="<?= esc($man['etapa'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Data de ocorrência</label>
            <input type="date" class="form-control" name="dataocorrencia" value="<?= esc(substr((string)($man['dataocorrencia'] ?? ''),0,10)) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Modelo</label>
            <input class="form-control" name="modelo" value="<?= esc($man['modelo'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Fornecedor da manutenção</label>
            <input class="form-control" name="fornman" value="<?= esc($man['fornman'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Oficina</label>
            <input class="form-control" name="oficina" value="<?= esc($man['oficina'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Descrição</label>
            <textarea class="form-control" name="descricao" rows="2"><?= esc($man['descricao'] ?? '') ?></textarea>
        </div>

        <div class="col-12 section-wrap">
            <div class="p-2 rounded bg-light border">
                <p class="section-title"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i><span>Agendamento e execução</span></p>
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
        <?php if ($hrefDocAtual !== ''): ?>
        <div class="col-md-4 d-flex align-items-end">
            <a href="<?= esc($hrefDocAtual) ?>" target="_blank" rel="noopener" class="btn btn-secondary">Último Documento Anexado</a>
        </div>
        <?php endif; ?>
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
                <p class="section-title"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i><span>Financeiro</span></p>
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
            <input class="form-control" name="valorreembolso" value="<?= esc($man['valorreembolso'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Valor oficina</label>
            <input class="form-control" name="valoroficina" value="<?= esc($man['valoroficina'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Valor do desconto</label>
            <input class="form-control" name="valordesconto" value="<?= esc($man['valordesconto'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Valor mão de obra</label>
            <input class="form-control" name="valormaoobra" value="<?= esc($man['valormaoobra'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Valor material</label>
            <input class="form-control" name="valormaterial" value="<?= esc($man['valormaterial'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Valor transporte</label>
            <input class="form-control" name="valortransp" value="<?= esc($man['valortransp'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Outros valores</label>
            <input class="form-control" name="outrosvalor" value="<?= esc($man['outrosvalor'] ?? '') ?>">
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
            <input class="form-control" name="formapagam" value="<?= esc($man['formapagam'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Condição de pagamento</label>
            <input class="form-control" name="condicaopag" value="<?= esc($man['condicaopag'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Número de parcelas</label>
            <input class="form-control" name="numparc" value="<?= esc($man['numparc'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Valor de parcela</label>
            <input class="form-control" name="valorparcela" value="<?= esc($man['valorparcela'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Data da primeira parcela</label>
            <input type="date" class="form-control" name="dataprimparc" value="<?= esc(substr((string)($man['dataprimparc'] ?? ''),0,10)) ?>">
        </div>

        <div class="col-12 section-wrap">
            <div class="p-2 rounded bg-light border">
                <p class="section-title"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Encerramento</span></p>
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
    <div class="form-actions-floating d-flex gap-2" role="group" aria-label="Ações da manutenção">
        <?php if ($podeEditar): ?>
            <button class="btn btn-success" name="salvar" value="1" type="submit">Confirmar</button>
        <?php endif; ?>
        <a class="btn btn-outline-primary" target="_blank" href="../pdf/fpdf/ordemdeservico.php?num=<?= esc((string)$id) ?>">Ordem de serviço</a>
        <a class="btn btn-danger" href="listagem-manutencao.php">Cancelar</a>
    </div>

    <div class="mt-3" style="color: #dc3545;">
        <ul class="mb-0 ps-3">
            <li>Campos obrigatórios.</li>
            <li>Para marcar a manutenção como concluída, não se esqueça de informar a data de entrada, a data de retirada e a previsão de saída.</li>
        </ul>
    </div>
</div>
</form>
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>document.getElementById('sidebarToggle')?.addEventListener('click', function(event){event.preventDefault();document.body.classList.toggle('sb-sidenav-toggled');});</script>
</body>
</html>
<!--  -->
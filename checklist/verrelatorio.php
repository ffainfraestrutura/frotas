<?php
require_once __DIR__ . '/../includes/autofrota_common.php';
$autofrota = autofrotaInit();
$con = $autofrota['conn'];
$databaseName = (string) ($autofrota['databaseName'] ?? '');
$id = (int) ($_GET['id'] ?? 0);
$vistoria = [];
$fotos = [];
$tipoVistoria = '';
$statusVeiculo = '';

if ($id > 0 && $con instanceof mysqli && preg_match('/^[A-Za-z0-9_]+$/', $databaseName) === 1) {
    $stmt = mysqli_prepare($con, "SELECT * FROM `{$databaseName}`.`tbvistoria` WHERE idtbvistoria = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $vistoria = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
    }
    if ($vistoria) {
        $stmtTipo = mysqli_prepare($con, "SELECT tipo FROM `{$databaseName}`.`tbatipovist` WHERE idtbatipovist = ? LIMIT 1");
        if ($stmtTipo) {
            mysqli_stmt_bind_param($stmtTipo, 's', $vistoria['tipo']);
            mysqli_stmt_execute($stmtTipo);
            $tipoVistoria = (string) ((mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTipo)) ?: [])['tipo'] ?? '');
        }
        $stmtStatus = mysqli_prepare($con, "SELECT status FROM `{$databaseName}`.`tbvelstatus` WHERE idtbastatusvel = ? LIMIT 1");
        if ($stmtStatus) {
            mysqli_stmt_bind_param($stmtStatus, 's', $vistoria['statusveic']);
            mysqli_stmt_execute($stmtStatus);
            $statusVeiculo = (string) ((mysqli_fetch_assoc(mysqli_stmt_get_result($stmtStatus)) ?: [])['status'] ?? '');
        }
    }
    $stmtFotos = mysqli_prepare($con, "SELECT * FROM `{$databaseName}`.`tbvistoriafotos` WHERE idtbvistoria = ? LIMIT 1");
    if ($stmtFotos) {
        mysqli_stmt_bind_param($stmtFotos, 'i', $id);
        mysqli_stmt_execute($stmtFotos);
        $fotos = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtFotos)) ?: [];
    }
}

$e = static fn(mixed $valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$exibir = static fn(mixed $valor): string => trim((string) $valor) !== '' ? htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8') : 'Não informado';
$formatarData = static function (mixed $valor): string {
    if (trim((string) $valor) === '') return 'Não informada';
    $data = strtotime((string) $valor);
    return $data ? date('d/m/Y H:i', $data) : htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};
$rotuloItem = static function (mixed $valor): string {
    return match (strtolower(trim((string) $valor))) {
        'ok' => 'OK', 'naook', 'não ok', 'nao ok' => 'NÃO OK', default => trim((string) $valor) !== '' ? strtoupper((string) $valor) : 'NÃO INFORMADO',
    };
};
$gruposItens = [
    'Estado geral dos itens' => [
        'teto'=>'Teto do veículo',
        'frente'=>'Frente do veículo',
        'latesq'=>'Lateral esquerda',
        'latdir'=>'Lateral direita',
        'traseira'=>'Traseira do veículo',
        'itinterno'=>'Itens internos',
        'pneus'=>'Pneus',
    ],
    'Pneus, acessórios e conservação' => [
        'step'=>'Step',
        'marcapneus'=>'Marca dos pneus',
        'kitstep'=>'Kit Step',
        'calotas'=>'Calotas',
        'bateria'=>'Marca da bateria',
        'safecar'=>'SafeCar',
        'limpint'=>'Limpeza interna',
        'limpext'=>'Limpeza externa',
    ],
];
$camposFotos = ['frontal'=>'Frontal','traseira'=>'Traseira','direita'=>'Lateral direita','esquerda'=>'Lateral esquerda','bateria'=>'Bateria','painel'=>'Painel','selfie'=>'Selfie','cnh'=>'CNH','extra1'=>'Extra 1','extra2'=>'Extra 2','extra3'=>'Extra 3','extra4'=>'Extra 4','extra5'=>'Extra 5'];
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Relatório de Vistoria</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script><style>.report{max-width:1100px}.report-card{border:0;box-shadow:0 .125rem .5rem #00000014}.item-ok{color:#198754}.item-bad{color:#dc3545}.photo{height:210px;object-fit:contain;width:100%;background:#f5f5f5;border-radius:.35rem}@media print{nav,.no-print,footer{display:none!important}body{background:#fff}.report{max-width:none}.report-card{box-shadow:none;border:1px solid #bbb}.photo{height:170px}.page-break{break-before:page}}</style></head><body><?php autofrotaMenu(); ?><main class="container-fluid report px-4 pb-5"><header class="text-center py-4"><i class="fas fa-clipboard-check fa-3x text-success mb-2"></i><h1 class="h2">Relatório de Vistoria</h1><p class="text-muted mb-0">Checklist do veículo<?= !empty($vistoria['placa']) ? ' · '.$e(strtoupper($vistoria['placa'])) : '' ?></p></header>
<?php if (!$vistoria): ?><div class="alert alert-warning">Vistoria não encontrada.</div><?php else: ?>
<div class="d-flex justify-content-end gap-2 mb-3 no-print"><a class="btn btn-outline-secondary" href="./checklistinicio.php"><i class="fas fa-arrow-left me-1"></i>Voltar</a><button class="btn btn-primary" type="button" onclick="window.print()"><i class="fas fa-print me-1"></i>Imprimir</button></div>
<section class="card report-card mb-4"><div class="card-header bg-white"><h2 class="h5 mb-0">Informações do condutor</h2></div><div class="card-body row g-3"><?php foreach (['nome'=>'Nome','matricula'=>'Matrícula','cpf'=>'CPF','cnh'=>'CNH','categoriacnh'=>'Categoria CNH','validadecnh'=>'Validade CNH','centrocusto'=>'Centro de custo'] as $campo=>$rotulo): ?><div class="col-md-4"><strong><?= $rotulo ?></strong><br><span class="text-muted"><?= $exibir($vistoria[$campo] ?? '') ?></span></div><?php endforeach ?></div></section>
<section class="card report-card mb-4"><div class="card-header bg-white"><h2 class="h5 mb-0">Informações do veículo e da vistoria</h2></div><div class="card-body row g-3"><?php foreach (['placa'=>'Placa','modelo'=>'Modelo','anofabricacao'=>'Ano','unidade'=>'Unidade','vistoriador'=>'Vistoriador','estado'=>'Estado geral','hodometro'=>'Hodômetro','niveltanque'=>'Nível do tanque','documentacao'=>'Documentação'] as $campo=>$rotulo): ?><div class="col-md-4"><strong><?= $rotulo ?></strong><br><span class="text-muted"><?= $exibir($vistoria[$campo] ?? '') ?></span></div><?php endforeach ?><div class="col-md-4"><strong>Tipo</strong><br><span class="text-muted"><?= $exibir($tipoVistoria ?: ($vistoria['tipo'] ?? '')) ?></span></div><div class="col-md-4"><strong>Status</strong><br><span class="text-muted"><?= $exibir($statusVeiculo ?: ($vistoria['statusveic'] ?? '')) ?></span></div><div class="col-md-4"><strong>Data da vistoria</strong><br><span class="text-muted"><?= $formatarData($vistoria['datavistoria'] ?? '') ?></span></div><div class="col-md-4"><strong>Possui avaria</strong><br><span class="text-muted"><?= ($vistoria['avaria'] ?? '') === '1' ? 'SIM' : (($vistoria['avaria'] ?? '') === '0' ? 'NÃO' : 'Não informado') ?></span></div><div class="col-12"><strong>Observações</strong><br><span class="text-muted"><?= nl2br($exibir($vistoria['observacao'] ?? '')) ?></span></div></div></section>
<section class="card report-card mb-4"><div class="card-header bg-white"><h2 class="h5 mb-0">Itens personalizados da vistoria</h2></div><div class="card-body"><?php foreach ($gruposItens as $grupo=>$itens): ?><h3 class="h6 border-bottom pb-2 mt-3"><?= $e($grupo) ?></h3><div class="row g-2"><?php foreach ($itens as $campo=>$rotulo): ?><?php $valor=$rotuloItem($vistoria[$campo] ?? ''); ?><div class="col-md-4"><strong><?= $e($rotulo) ?>:</strong> <span class="<?= $valor === 'OK' ? 'item-ok' : ($valor === 'NÃO OK' ? 'item-bad' : 'text-muted') ?>"><?= $e($valor) ?></span></div><?php endforeach ?></div><?php endforeach ?></div></section>
<section class="card report-card mb-4 page-break"><div class="card-header bg-white"><h2 class="h5 mb-0">Fotos da vistoria</h2></div><div class="card-body row g-3"><?php $temFoto=false; foreach ($camposFotos as $campo=>$rotulo): if (empty($fotos[$campo])) continue; $temFoto=true; ?><figure class="col-md-6 mb-0"><figcaption class="fw-bold mb-2"><?= $e($rotulo) ?></figcaption><img class="photo" src=".<?= $e($fotos[$campo]) ?>" alt="<?= $e($rotulo) ?>"></figure><?php endforeach; if (!$temFoto): ?><div class="col-12 text-muted">Nenhuma foto foi registrada.</div><?php endif ?></div></section>
<section class="row g-5 mt-5 pt-5"><div class="col-md-6 text-center"><div class="border-top border-dark pt-2">Assinatura do vistoriador — <?= $exibir($vistoria['vistoriador'] ?? '') ?></div></div><div class="col-md-6 text-center"><div class="border-top border-dark pt-2">Assinatura do condutor — <?= $exibir($vistoria['nome'] ?? '') ?></div></div></section>
<?php endif ?></main><footer class="py-4 bg-light"><div class="container-fluid text-center small text-muted">Copyright &copy; FFA Infraestrutura</div></footer><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script></body></html>
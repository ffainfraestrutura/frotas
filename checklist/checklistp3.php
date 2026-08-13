<?php
require_once __DIR__ . '/../includes/autofrota_common.php';
$autofrota = autofrotaInit();
$con = $autofrota['conn'];
$databaseName = (string) ($autofrota['databaseName'] ?? '');
$id = (int) ($_GET['id'] ?? 0);
$vistoria = [];
$fotos = [];

if ($id > 0 && $con instanceof mysqli) {
    $stmt = mysqli_prepare($con, "SELECT nome, matricula, datavistoria, placa, modelo, hodometro, estado, observacao FROM `{$databaseName}`.`tbvistoria` WHERE idtbvistoria = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $vistoria = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];

    $stmtFotos = mysqli_prepare($con, "SELECT frontal, traseira, direita, esquerda, painel, selfie, cnh, extra1, extra2, extra3 FROM `{$databaseName}`.`tbvistoriafotos` WHERE idtbvistoria = ?");
    mysqli_stmt_bind_param($stmtFotos, 'i', $id);
    mysqli_stmt_execute($stmtFotos);
    $fotos = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtFotos)) ?: [];
}

$escape = static fn(mixed $valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$camposFotos = ['frontal'=>'Frontal','traseira'=>'Traseira','direita'=>'Lateral direita','esquerda'=>'Lateral esquerda','painel'=>'Painel','selfie'=>'Selfie','cnh'=>'CNH','extra1'=>'Extra 1','extra2'=>'Extra 2','extra3'=>'Extra 3'];
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Checklist - Passo 3</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
  <style>.wrap{max-width:1000px}.card{border:0;box-shadow:0 .125rem .5rem #00000014}.photo{height:140px;object-fit:cover;width:100%;border-radius:.4rem;background:#f2f4f5}.photo-empty{align-items:center;border:2px dashed #adb5bd;color:#6c757d;display:flex;justify-content:center}</style>
</head>
<body>
<?php autofrotaMenu(); ?>
<main class="container-fluid wrap px-4 pb-5">
  <div class="pt-3 pb-2"><h1 class="h2">Revisão da vistoria</h1><p class="text-muted">Checklist · Passo 3<?= !empty($vistoria['placa']) ? ' · '.$escape(strtoupper($vistoria['placa'])) : '' ?></p></div>
  <?php if (!$vistoria): ?>
    <div class="alert alert-warning">Vistoria não encontrada. Volte ao início e realize as etapas anteriores.</div>
  <?php else: ?>
    <section class="card my-3"><div class="card-header bg-white"><h2 class="h5 mb-0">Dados salvos no Passo 1</h2></div><div class="card-body row g-3">
      <?php foreach (['nome'=>'Condutor','matricula'=>'Matrícula','datavistoria'=>'Data da vistoria','modelo'=>'Modelo','hodometro'=>'Hodômetro','estado'=>'Estado geral'] as $campo=>$label): ?>
        <div class="col-md-4"><strong><?= $label ?></strong><br><span class="text-muted"><?= $escape($vistoria[$campo] ?? '') ?: 'Não informado' ?></span></div>
      <?php endforeach; ?>
      <div class="col-12"><strong>Observações</strong><br><span class="text-muted"><?= nl2br($escape($vistoria['observacao'] ?? '')) ?: 'Nenhuma observação informada' ?></span></div>
    </div></section>
    <section class="card my-3"><div class="card-header bg-white"><h2 class="h5 mb-0">Fotos salvas no Passo 2</h2></div><div class="card-body row g-3">
      <?php foreach ($camposFotos as $campo=>$titulo): ?><div class="col-6 col-md-4"><strong><?= $titulo ?></strong><?php if (!empty($fotos[$campo])): $fotoSrc = trim((string) $fotos[$campo]); if ($fotoSrc !== '' && !preg_match('/^https?:\/\//i', $fotoSrc) && !str_starts_with($fotoSrc, '/')) { $fotoSrc = '.' . $fotoSrc; } ?><img class="photo mt-2" src="<?= $escape($fotoSrc) ?>" alt="<?= $titulo ?>"><?php else: ?><div class="photo photo-empty mt-2"><span><i class="fas fa-image me-1"></i>Não enviada</span></div><?php endif; ?></div><?php endforeach; ?>
    </div></section>
    <div class="d-flex justify-content-between"><a class="btn btn-outline-secondary" href="./checklistinicio.php">Nova vistoria</a><a class="btn btn-success" href="./verrelatorio.php?id=<?= $id ?>"><i class="fas fa-file-lines me-1"></i>Visualizar relatório</a></div>
  <?php endif; ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
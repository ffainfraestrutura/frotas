<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../control/conecta.php';
exigirLogin();

$perfil = (string) ($_SESSION['perfil'] ?? '0');
if ($perfil === '0' || $perfil === '') {
    http_response_code(403);
    exit('Sem permissão.');
}

function esc(?string $v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
$idtbveiculo = (int) ($_POST['idtbveiculo'] ?? $_GET['idtbveiculo'] ?? 0);
$placa = trim((string)($_POST['placa'] ?? $_GET['placa'] ?? ''));
if ($idtbveiculo <= 0 || $placa === '') { exit('Veículo inválido.'); }
$msg='';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['enviar'])) {
    $dirFs = __DIR__ . '/docs/veiculos/';
    $dirDb = './docs/veiculos/';
    if (!is_dir($dirFs)) { mkdir($dirFs, 0775, true); }
    $campos=['crlv'=>'crlv','crv'=>'crv','certipva'=>'cert_ipva'];
    $salvos=[];
    foreach($campos as $input=>$col){
        if (!empty($_FILES[$input]['tmp_name'])) {
            $ext = strtolower(pathinfo((string)$_FILES[$input]['name'], PATHINFO_EXTENSION));
            if (!in_array($ext,['pdf','doc','docx'],true)) { continue; }
            $nome = preg_replace('/[^A-Za-z0-9_-]/','', $placa) . '-' . date('YmdHis') . '-' . $input . '.' . $ext;
            if (move_uploaded_file($_FILES[$input]['tmp_name'], $dirFs . $nome)) { $salvos[$col] = $dirDb . $nome; }
        }
    }
    if ($salvos) {
        $stmt = mysqli_prepare($conn, 'SELECT idtbveicdocs FROM bdautofrotas.tbveicdocs WHERE placa=? LIMIT 1'); mysqli_stmt_bind_param($stmt,'s',$placa); mysqli_stmt_execute($stmt); $r=mysqli_stmt_get_result($stmt); $ex=mysqli_fetch_assoc($r); mysqli_stmt_close($stmt);
        if ($ex) {
            $sets=[];$vals=[];$types=''; foreach($salvos as $k=>$v){$sets[]="$k=?";$vals[]=$v;$types.='s';}
            $sql='UPDATE bdautofrotas.tbveicdocs SET '.implode(',',$sets).' WHERE placa=?'; $types.='s'; $vals[]=$placa;
            $st=mysqli_prepare($conn,$sql); mysqli_stmt_bind_param($st,$types,...$vals); mysqli_stmt_execute($st); mysqli_stmt_close($st);
        } else {
            $crlv=$salvos['crlv']??'';$crv=$salvos['crv']??'';$cert=$salvos['cert_ipva']??'';
            $st=mysqli_prepare($conn,'INSERT INTO bdautofrotas.tbveicdocs (placa,crlv,crv,cert_ipva) VALUES (?,?,?,?)'); mysqli_stmt_bind_param($st,'ssss',$placa,$crlv,$crv,$cert); mysqli_stmt_execute($st); mysqli_stmt_close($st);
        }
        $msg='Documentos enviados com sucesso.';
    } else { $msg='Nenhum arquivo válido enviado.'; }
}
?><!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Enviar documentos</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script></head>
<body class="sb-nav-fixed bg-light">
    <?php include __DIR__ . '/../includes/menu_superior_simples.php'; ?>
<div id="layoutSidenav_content"><main class="container py-4"><h1 class="h3">Enviar documentos do veículo <?=esc($placa)?></h1><?php if($msg): ?><div class="alert alert-info"><?=esc($msg)?></div><?php endif; ?><form method="post" action="enviar-documento-veiculo.php?idtbveiculo=<?=esc((string)$idtbveiculo)?>&placa=<?=urlencode($placa)?>" enctype="multipart/form-data" class="card card-body"><input type="hidden" name="idtbveiculo" value="<?=esc((string)$idtbveiculo)?>"><input type="hidden" name="placa" value="<?=esc($placa)?>"><div class="mb-3"><label class="form-label">CRLV</label><input type="file" name="crlv" class="form-control" accept=".pdf,.doc,.docx"></div><div class="mb-3"><label class="form-label">CRV</label><input type="file" name="crv" class="form-control" accept=".pdf,.doc,.docx"></div><div class="mb-3"><label class="form-label">Certificado IPVA</label><input type="file" name="certipva" class="form-control" accept=".pdf,.doc,.docx"></div><button class="btn btn-success" name="enviar" value="1">Enviar</button></form></main></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script><script>document.getElementById('sidebarToggle')?.addEventListener('click', function(event){event.preventDefault();document.body.classList.toggle('sb-sidenav-toggled');});</script></body></html>

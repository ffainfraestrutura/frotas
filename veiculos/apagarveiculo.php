<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../control/conecta.php';
exigirLogin();
$perfilLogado = (string) ($_SESSION['perfil'] ?? '');
if ($perfilLogado !== '4') { http_response_code(403); exit('Sem permissão.'); }
$id = (int) ($_POST['idtbveiculo'] ?? 0);
if ($id <= 0) { exit('ID inválido.'); }
$st = mysqli_prepare($conn, 'DELETE FROM bdautofrotas.tbveiculo WHERE idtbveiculo=? LIMIT 1');
mysqli_stmt_bind_param($st, 'i', $id);
$ok = mysqli_stmt_execute($st);
mysqli_stmt_close($st);
echo "<script>alert('" . ($ok ? 'Registro apagado com sucesso.' : 'Falha ao apagar registro.') . "');window.location='veiculos.php';</script>";

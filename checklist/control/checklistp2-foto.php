<?php
require_once __DIR__ . '/../../includes/autofrota_common.php';
$autofrota = autofrotaInit();
$con = $autofrota['conn'];
$databaseName = (string) ($autofrota['databaseName'] ?? '');
header('Content-Type: application/json; charset=utf-8');

$responder = static function (int $status, string $mensagem, array $extra = []): never {
    http_response_code($status);
    echo json_encode(['ok' => $status < 400, 'message' => $mensagem] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
};

$permitidos = ['frontal','traseira','direita','esquerda','painel','selfie','cnh','extra1','extra2','extra3'];
$id = (int) ($_POST['idinserido'] ?? 0);
$campo = str_replace('-foto', '', (string) ($_POST['localcarro'] ?? ''));
$arquivo = $_FILES['file'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !($con instanceof mysqli) || $id < 1 || !in_array($campo, $permitidos, true)) {
    $responder(400, 'Dados do upload inválidos.');
}
if (!is_array($arquivo) || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $responder(400, 'Arquivo não recebido.');
}
if (($arquivo['size'] ?? 0) > 10 * 1024 * 1024) {
    $responder(413, 'A imagem deve ter no máximo 10 MB.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
$extensoes = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
if (!isset($extensoes[$mime])) {
    $responder(415, 'Formato não permitido. Use JPEG, PNG ou WebP.');
}

$baseHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
$baseScheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)) ? 'https' : 'http';
$diretorio = '/tmp/frotas_docs/vistoria';
if (!is_dir($diretorio) && !@mkdir($diretorio, 0775, true) && !is_dir($diretorio)) {
    $responder(500, 'Não foi possível preparar o diretório de fotos da vistoria.');
}
$nome = sprintf('%d-%s-%s-%s.%s', $id, date('YmdHis'), $campo, bin2hex(random_bytes(4)), $extensoes[$mime]);
$destino = $diretorio . DIRECTORY_SEPARATOR . $nome;
if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
    $responder(500, 'Não foi possível salvar a imagem.');
}

$caminho = $baseScheme . '://' . $baseHost . '/diagnostico-uploads.php?abrir=' . rawurlencode($nome);
$stmt = mysqli_prepare($con, "UPDATE `{$databaseName}`.`tbvistoriafotos` SET `$campo` = ? WHERE idtbvistoria = ?");
mysqli_stmt_bind_param($stmt, 'si', $caminho, $id);
if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) < 1) {
    @unlink($destino);
    $responder(500, 'Não foi possível vincular a imagem à vistoria.');
}
$responder(200, 'Foto salva.', ['path' => $caminho]);
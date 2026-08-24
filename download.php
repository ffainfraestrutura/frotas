<?php
// download.php
error_reporting(0); // Suprimir warnings
$arquivo = $_GET['arquivo'] ?? '';

if (empty($arquivo)) {
    http_response_code(400);
    die('Arquivo não especificado.');
}

// Sanitizar o nome do arquivo
$arquivo = basename($arquivo);

// Permitir apenas arquivos de erros
if (strpos($arquivo, 'erros_importacao_') !== 0) {
    http_response_code(403);
    die('Arquivo não permitido.');
}

// Caminho completo do arquivo
$caminho = '/opt/apps/files/storage/frotas/' . $arquivo;

// Verificar se o arquivo existe
if (!file_exists($caminho)) {
    http_response_code(404);
    die('Arquivo não encontrado.');
}

// Limpar buffers anteriores
if (ob_get_level()) {
    ob_end_clean();
}

// Headers para forçar download seguro
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $arquivo . '"');
header('Content-Length: ' . filesize($caminho));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Expires: 0');

// Headers de segurança para evitar bloqueio
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');

// Enviar arquivo
readfile($caminho);
exit;
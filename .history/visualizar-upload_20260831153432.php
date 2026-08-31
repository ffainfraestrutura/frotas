<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/portal_helpers.php';
exigirLogin();

$uploadDirPreferida = rtrim((string) (getenv('FROTAS_UPLOAD_DIR') ?: diretorioUploadsPortal()), '/\\');
$mensagemUpload = '';
$arquivoEnviado = null;

if (isset($_GET['abrir']) && is_string($_GET['abrir'])) {
    $nomeArquivo = basename((string) $_GET['abrir']);
    $caminhosPossiveis = [
        $uploadDirPreferida . DIRECTORY_SEPARATOR . $nomeArquivo,
        '/tmp/frotas_docs' . DIRECTORY_SEPARATOR . $nomeArquivo,
        '/tmp/frotas_docs/cnhs' . DIRECTORY_SEPARATOR . $nomeArquivo,
        '/tmp/frotas_docs/condutor' . DIRECTORY_SEPARATOR . $nomeArquivo,
    ];

    $caminhoArquivo = null;
    foreach ($caminhosPossiveis as $caminhoTeste) {
        if ($nomeArquivo !== '' && file_exists($caminhoTeste) && is_file($caminhoTeste)) {
            $caminhoArquivo = $caminhoTeste;
            break;
        }
    }

    if ($caminhoArquivo !== null) {
        $tipoConteudo = mime_content_type($caminhoArquivo) ?: 'application/octet-stream';
        header('Content-Type: ' . $tipoConteudo);
        header('Content-Disposition: inline; filename="' . basename($nomeArquivo) . '"');
        readfile($caminhoArquivo);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo'])) {
    header('Content-Type: text/html; charset=utf-8');
    $arquivo = $_FILES['arquivo'];
    if (is_array($arquivo) && !empty($arquivo['name'])) {
        $pastaDestino = $uploadDirPreferida;
        if (!is_dir($pastaDestino) && !@mkdir($pastaDestino, 0775, true) && !is_dir($pastaDestino)) {
            $mensagemUpload = 'Não foi possível criar a pasta de upload em ' . $pastaDestino . '.';
        } else {
            $nomeOriginal = basename((string) $arquivo['name']);
            $nomeSeguro = preg_replace('/[^A-Za-z0-9._-]/', '_', $nomeOriginal);
            $destino = $pastaDestino . DIRECTORY_SEPARATOR . $nomeSeguro;
            if (is_uploaded_file((string) $arquivo['tmp_name']) && move_uploaded_file((string) $arquivo['tmp_name'], $destino)) {
                $arquivoEnviado = ['nome' => $nomeSeguro, 'caminho' => $destino, 'url' => '?abrir=' . rawurlencode($nomeSeguro)];
                $mensagemUpload = 'Arquivo enviado com sucesso para ' . $pastaDestino . '.';
            } else {
                $mensagemUpload = 'Não foi possível gravar o arquivo na pasta ' . $pastaDestino . '.';
            }
        }
    }
}
$escape = static fn(mixed $valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Diagnóstico de uploads</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container-fluid px-4 py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div><h1 class="h2 mb-1">Uploads</h1><p class="text-muted mb-0">Envio e visualização de arquivos.</p></div>
    </div>

    <section class="card shadow-sm mb-4">
        <div class="card-header"><h2 class="h5 mb-0">Teste de upload</h2></div>
        <div class="card-body">
            <div class="mb-3"><strong>Pasta de destino:</strong> <code><?= $escape($uploadDirPreferida) ?></code></div>
            <?php if ($mensagemUpload !== ''): ?>
                <div class="alert <?= str_contains($mensagemUpload, 'sucesso') ? 'alert-success' : 'alert-warning' ?> mb-3"><?= $escape($mensagemUpload) ?></div>
            <?php endif ?>
            <?php if ($arquivoEnviado !== null): ?>
                <div class="alert alert-info mb-3">
                    <div><strong>Arquivo:</strong> <?= $escape($arquivoEnviado['nome']) ?></div>
                    <div class="mt-2"><a class="btn btn-primary btn-sm" href="<?= $escape($arquivoEnviado['url']) ?>" target="_blank" rel="noopener noreferrer">Abrir arquivo</a></div>
                </div>
            <?php endif ?>
            <form method="post" enctype="multipart/form-data">
                <div class="input-group">
                    <input class="form-control" type="file" name="arquivo" required>
                    <button class="btn btn-success" type="submit">Enviar</button>
                </div>
            </form>
        </div>
    </section>
</main>
</body>
</html>
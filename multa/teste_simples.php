<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$mensagem = '';
$sucesso = false;
$destino = '';

// Cria a pasta docs/multas na mesma pasta
$uploadDir = '/docs/multas/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cria a pasta se não existir
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
        $mensagem .= "Pasta criada: " . $uploadDir . "<br>";
    }
    
    $mensagem .= "Pasta upload: " . $uploadDir . "<br>";
    $mensagem .= "Pasta existe? " . (is_dir($uploadDir) ? 'SIM' : 'NÃO') . "<br>";
    $mensagem .= "Pasta writable? " . (is_writable($uploadDir) ? 'SIM' : 'NÃO') . "<br>";
    
    if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
        $nomeOriginal = $_FILES['arquivo']['name'];
        $tamanho = $_FILES['arquivo']['size'];
        $temp = $_FILES['arquivo']['tmp_name'];
        
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        $nomeFinal = time() . '_' . rand(1000, 9999) . '.' . $extensao;
        $destino = $uploadDir . $nomeFinal;
        
        $mensagem .= "Arquivo temp: " . $temp . "<br>";
        $mensagem .= "Arquivo temp existe? " . (file_exists($temp) ? 'SIM' : 'NÃO') . "<br>";
        $mensagem .= "Destino: " . $destino . "<br>";
        
        if (move_uploaded_file($temp, $destino)) {
            $sucesso = true;
            $mensagem .= "<span style='color:green'>✅ ARQUIVO SALVO COM SUCESSO!</span><br>";
            $mensagem .= "Tamanho salvo: " . filesize($destino) . " bytes<br>";
        } else {
            $erro = error_get_last();
            $mensagem .= "<span style='color:red'>❌ ERRO AO SALVAR!</span><br>";
            $mensagem .= "Erro: " . print_r($erro, true) . "<br>";
        }
    } else {
        $erroCode = $_FILES['arquivo']['error'] ?? 'Nenhum arquivo';
        $mensagem .= "<span style='color:red'>Erro no upload: " . $erroCode . "</span><br>";
    }
}

// Lista arquivos existentes
$arquivos = [];
if (is_dir($uploadDir)) {
    $arquivos = scandir($uploadDir);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teste Upload Simples</title>
    <meta charset="utf-8">
    <style>
        body { font-family: monospace; padding: 20px; }
        .success { background: #d4edda; padding: 10px; border: 1px solid #c3e6cb; margin: 10px 0; }
        .error { background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; margin: 10px 0; }
        .info { background: #d1ecf1; padding: 10px; border: 1px solid #bee5eb; margin: 10px 0; }
        pre { background: #f4f4f4; padding: 10px; overflow: auto; }
    </style>
</head>
<body>
    <h1>Teste de Upload - Diagnóstico</h1>
    
    <div class="info">
        <strong>Informações do Sistema:</strong><br>
        Script: <?= __FILE__ ?><br>
        Diretório: <?= __DIR__ ?><br>
        Usuário PHP: <?= exec('whoami') ?><br>
        upload_max_filesize: <?= ini_get('upload_max_filesize') ?><br>
        post_max_size: <?= ini_get('post_max_size') ?>
    </div>
    
    <?php if ($mensagem): ?>
        <div class="<?= $sucesso ? 'success' : 'error' ?>">
            <strong><?= $sucesso ? 'SUCESSO' : 'ERRO' ?>:</strong><br>
            <?= $mensagem ?>
        </div>
    <?php endif; ?>
    
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="arquivo" required>
        <button type="submit">Enviar</button>
    </form>
    
    <h3>Arquivos na pasta docs/multas/</h3>
    <ul>
    <?php 
    if (!empty($arquivos)) {
        foreach ($arquivos as $file) {
            if ($file != '.' && $file != '..') {
                $size = is_file($uploadDir . $file) ? filesize($uploadDir . $file) : 0;
                echo "<li>" . htmlspecialchars($file) . " - " . number_format($size) . " bytes</li>";
            }
        }
    } else {
        echo "<li>Nenhum arquivo encontrado ou pasta não existe</li>";
    }
    ?>
    </ul>
    
    <?php if ($destino && file_exists($destino)): ?>
        <h3>Último arquivo enviado:</h3>
        <pre><?php
        if (file_exists($destino)) {
            echo "Arquivo: " . basename($destino) . "\n";
            echo "Tamanho: " . filesize($destino) . " bytes\n";
            echo "Caminho completo: " . $destino . "\n";
        }
        ?></pre>
    <?php endif; ?>
</body>
</html>
<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
exigirLogin();

header('Content-Type: text/html; charset=utf-8');

$escape = static fn(mixed $valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');

$uploadDir = trim((string) ($_POST['caminho'] ?? ''));
if ($uploadDir === '' && isset($_GET['usar'])) {
    $uploadDir = trim((string) $_GET['usar']);
}

$caminhoPadrao = '/var/www/html/uploads/frotas';
$mensagem = '';
$sucesso = false;
$listar = [];

if (isset($_GET['checar']) && isset($_GET['usar'])) {
    $diretorio = trim((string) $_GET['usar']);
    $nomeArquivo = basename((string) $_GET['checar']);
    $caminhoArquivo = $diretorio . DIRECTORY_SEPARATOR . $nomeArquivo;
    
    if ($nomeArquivo !== '' && file_exists($caminhoArquivo) && is_file($caminhoArquivo)) {
        $tipoConteudo = mime_content_type($caminhoArquivo) ?: 'application/octet-stream';
        header('Content-Type: ' . $tipoConteudo);
        header('Content-Disposition: inline; filename="' . basename($nomeArquivo) . '"');
        readfile($caminhoArquivo);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uploadDir !== '') {
    $uploadDir = rtrim($uploadDir, '/\\');
    
    $pastaRaiz = dirname($uploadDir);
    $pastaRaizExiste = is_dir($pastaRaiz);
    $pastaRaizGravavel = $pastaRaizExiste && is_writable($pastaRaiz);
    
    if (!is_dir($uploadDir)) {
        if (@mkdir($uploadDir, 0777, true)) {
            $mensagem = "✓ Diretório criado com sucesso (incluindo pastas pai se necessário): $uploadDir";
            $sucesso = true;
        } else {
            $erro = error_get_last();
            $detalhes = $erro ? $erro['message'] : 'motivo desconhecido';
            $mensagem = "Erro ao criar <code>" . $escape($uploadDir) . "</code>: " . $escape($detalhes) . ". Verifique se você tem permissão de escrita em alguma pasta raiz do servidor.";
        }
    } else {
        $mensagem = "✓ Diretório já existe: $uploadDir";
        $sucesso = true;
    }

    if ($sucesso && is_dir($uploadDir)) {
        if (@chmod($uploadDir, 0775)) {
            $mensagem .= " | Permissões ajustadas para 775.";
        } else {
            if (@chmod($uploadDir, 0777)) {
                $mensagem .= " | Permissões ajustadas para 777.";
            }
        }
    }

    clearstatcache(true, $uploadDir);
    if (is_dir($uploadDir) && is_writable($uploadDir)) {
        $sucesso = true;
        $permsAtuais = decoct(fileperms($uploadDir) & 0777);
        $mensagem = "✓ Diretório pronto para receber arquivos: <code>" . $escape($uploadDir) . "</code> (permissões: $permsAtuais)";
    }

    if ($sucesso && is_dir($uploadDir) && is_writable($uploadDir) && isset($_FILES['arquivo'])) {
        $arquivo = $_FILES['arquivo'];
        if (is_array($arquivo) && !empty($arquivo['name']) && is_uploaded_file((string) $arquivo['tmp_name'])) {
            $nomeOriginal = basename((string) $arquivo['name']);
            $nomeSeguro = preg_replace('/[^A-Za-z0-9._-]/', '_', $nomeOriginal);
            $destino = $uploadDir . DIRECTORY_SEPARATOR . $nomeSeguro;
            if (move_uploaded_file((string) $arquivo['tmp_name'], $destino)) {
                $mensagem = "✓ Arquivo enviado com sucesso: " . $nomeSeguro;
            }
        }
    }
}

$exists = $uploadDir !== '' && is_dir($uploadDir);
$writable = $exists && is_writable($uploadDir);
$perms = $exists ? decoct(fileperms($uploadDir) & 0777) : 'N/A';

if ($exists && is_dir($uploadDir)) {
    try {
        $listar = array_diff(scandir($uploadDir), ['.', '..']);
        sort($listar);
    } catch (Throwable $e) {
        $listar = [];
    }
}

$pastasGravaveis = [];
$raizesVerificar = ['/var/www/html', '/var/www', '/srv', '/app', '/home', '/tmp', dirname(__DIR__)];
$pastaAplicacao = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'files';

foreach ($raizesVerificar as $raiz) {
    if (!is_dir($raiz)) {
        continue;
    }
    
    if (is_writable($raiz)) {
        $temporario = strpos($raiz, '/tmp') !== false ? ' ⚠️ (temporário)' : '';
        $pastasGravaveis[$raiz] = ['gravavel' => true, 'direto' => true, 'temporario' => $temporario];
    }
    
    try {
        $subs = array_diff(scandir($raiz), ['.', '..', '.git']);
        foreach (array_slice($subs, 0, 20) as $sub) {
            $caminhoSub = $raiz . DIRECTORY_SEPARATOR . $sub;
            if (is_dir($caminhoSub) && is_writable($caminhoSub)) {
                $temporario = strpos($caminhoSub, '/tmp') !== false ? ' ⚠️ (temporário)' : '';
                $pastasGravaveis[$caminhoSub] = ['gravavel' => true, 'parent' => $raiz, 'temporario' => $temporario];
            }
        }
    } catch (Throwable $e) {
        // ignorar
    }
}

if (empty($pastasGravaveis) && is_writable(dirname(__DIR__))) {
    $pastasGravaveis[dirname(__DIR__)] = ['gravavel' => true, 'direto' => true];
}

// Remover pastas temporárias da lista para priorizar outras
$pastasGraveisNaoTemp = array_filter($pastasGravaveis, static fn($v) => !str_contains($v['temporario'] ?? '', 'temporário'));
if (!empty($pastasGraveisNaoTemp)) {
    $pastasGravaveis = $pastasGraveisNaoTemp;
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup - Diretório de uploads</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="h5 mb-0">Setup do diretório de uploads</h1>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Caminho do diretório a criar</label>
                        <form method="post" class="input-group">
                            <input class="form-control" type="text" name="caminho" placeholder="/var/www/html/uploads/frotas" value="<?= $escape($uploadDir ?: $caminhoPadrao) ?>" required>
                            <button class="btn btn-primary" type="submit">Criar</button>
                        </form>
                        <div class="small text-muted mt-2">
                            Exemplo: <code>/var/www/html/uploads/frotas</code> ou <code>/srv/uploads/frotas</code>
                        </div>
                    </div>

                    <?php if ($mensagem !== ''): ?>
                        <div class="alert <?= $sucesso ? 'alert-success' : 'alert-danger' ?> mb-4">
                            <?= $escape($mensagem) ?>
                        </div>
                    <?php endif ?>

                    <?php if (!$sucesso && $uploadDir !== ''): ?>
                        <div class="alert alert-danger mb-4">
                            <strong>Falha ao criar:</strong> <?= $mensagem ?>
                        </div>

                        <?php if (!empty($pastasGravaveis)): ?>
                            <div class="alert alert-warning mb-4">
                                <strong>✓ Pastas graváveis encontradas:</strong>
                                <div class="mt-3">
                                    <div class="list-group">
                                        <?php foreach ($pastasGravaveis as $caminho => $info): ?>
                                            <button type="button" class="list-group-item list-group-item-action text-start" onclick="document.querySelector('input[name=caminho]').value = '<?= $escape(addslashes($caminho)) ?>/uploads/frotas'; document.querySelector('input[name=caminho]').focus(); return false;">
                                                <div class="fw-semibold"><code><?= $escape($caminho) ?>/uploads/frotas</code> <?= $escape($info['temporario'] ?? '') ?></div>
                                                <small class="text-muted">Clique para tentar neste local</small>
                                            </button>
                                        <?php endforeach ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger mb-4">
                                <strong>⚠️ Nenhuma pasta gravável encontrada</strong>
                                <p class="mt-2 mb-0">O script não conseguiu identificar pastas com permissão de escrita. Opções:</p>
                                <ol class="mt-2 mb-0">
                                    <li><strong>Peça ao administrador do servidor</strong> para criar: <code>/var/www/html/frotas</code> com permissão 777</li>
                                    <li><strong>Configure via variáveis de ambiente:</strong>
                                        <pre class="mt-2 bg-dark text-light p-2 rounded" style="font-size: 0.85rem;">FROTAS_UPLOAD_DIR=/caminho/onde/tem/permissao
FROTAS_UPLOAD_URL=https://seu-dominio.com/uploads</pre>
                                    </li>
                                    <li><strong>Em container:</strong> Configure um volume montado no docker-compose ou Kubernetes</li>
                                </ol>
                            </div>
                        <?php endif ?>
                    <?php endif ?>

                    <?php if ($uploadDir !== ''): ?>
                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <div class="border rounded p-3 text-center">
                                    <div class="small text-muted mb-1">Existe</div>
                                    <span class="badge text-bg-<?= $exists ? 'success' : 'danger' ?> p-2"><?= $exists ? 'Sim' : 'Não' ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3 text-center">
                                    <div class="small text-muted mb-1">Gravável</div>
                                    <span class="badge text-bg-<?= $writable ? 'success' : 'danger' ?> p-2"><?= $writable ? 'Sim' : 'Não' ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3 text-center">
                                    <div class="small text-muted mb-1">Permissões</div>
                                    <code class="badge text-bg-info p-2"><?= $escape($perms) ?></code>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3 text-center">
                                    <div class="small text-muted mb-1">Arquivos</div>
                                    <code class="badge text-bg-secondary p-2"><?= count($listar) ?></code>
                                </div>
                            </div>
                        </div>

                        <?php if ($exists && !empty($listar)): ?>
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Arquivos no diretório</label>
                                <div class="list-group list-group-sm">
                                    <?php foreach ($listar as $arquivo): ?>
                                        <a href="?checar=<?= rawurlencode($arquivo) ?>&usar=<?= rawurlencode($uploadDir) ?>" class="list-group-item list-group-item-action">
                                            <?= $escape($arquivo) ?>
                                        </a>
                                    <?php endforeach ?>
                                </div>
                            </div>
                        <?php elseif ($exists): ?>
                            <div class="alert alert-info mb-4">
                                Diretório pronto, mas vazio. Envie arquivos para aqui.
                            </div>
                        <?php endif ?>

                        <?php if ($writable): ?>
                            <div class="alert alert-success mb-4">
                                <strong>✓ Pronto!</strong> O diretório está criado e pronto para receber uploads: <code><?= $escape($uploadDir) ?></code>
                            </div>

                            <div class="alert alert-warning mb-4">
                                <strong>Próximo passo:</strong> Configure a aplicação para usar este diretório.
                                <p class="mt-2 mb-2">Opção 1: Variáveis de ambiente (recomendado)</p>
                                <pre class="bg-dark text-light p-2 rounded mb-2" style="font-size: 0.85rem;">FROTAS_UPLOAD_DIR=<?= $escape($uploadDir) ?>
FROTAS_UPLOAD_URL=https://seu-dominio.com/caminho/publico</pre>
                                <p class="mb-0">Opção 2: Editar o código (não recomendado)</p>
                                <small>Modifique os arquivos de upload da aplicação para apontar para este diretório.</small>
                            </div>

                            <form method="post" enctype="multipart/form-data" class="mb-4">
                                <input type="hidden" name="caminho" value="<?= $escape($uploadDir) ?>">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Testar upload</label>
                                    <div class="input-group">
                                        <input class="form-control" type="file" name="arquivo" required>
                                        <button class="btn btn-success" type="submit" name="enviar">Enviar arquivo</button>
                                    </div>
                                </div>
                            </form>
                        <?php endif ?>
                    <?php endif ?>

                    <?php if ($uploadDir === ''): ?>
                        <div class="alert alert-info mb-4">
                            <strong>Digite o caminho</strong> do diretório acima e clique em "Criar".
                            <br><small class="mt-2 d-block">O script criará todas as pastas necessárias automaticamente com permissão 777.</small>
                        </div>

                        <?php if (!empty($pastasGravaveis)): ?>
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Atalhos - Clique para usar</label>
                                <div class="list-group">
                                    <?php foreach ($pastasGravaveis as $caminho => $info): ?>
                                        <button type="button" class="list-group-item list-group-item-action text-start" onclick="document.querySelector('input[name=caminho]').value = '<?= $escape(addslashes($caminho)) ?>/uploads/frotas'; document.querySelector('input[name=caminho]').focus(); return false;">
                                            <div class="fw-semibold"><code><?= $escape($caminho) ?>/uploads/frotas</code> 
                                                <?php if (!empty($info['temporario'])): ?>
                                                    <span class="badge bg-warning">⚠️ Temporário</span>
                                                <?php endif ?>
                                            </div>
                                            <small class="text-muted">Clique para preencher automaticamente</small>
                                        </button>
                                    <?php endforeach ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger mb-4">
                                <strong>⚠️ Nenhuma pasta gravável detectada</strong>
                                <p class="mt-2 mb-0">O script não conseguiu encontrar pastas com permissão de escrita. Possíveis causas:</p>
                                <ul class="mt-2 mb-2">
                                    <li>Você está em um container sem volumes montados</li>
                                    <li>As permissões do servidor são muito restritivas</li>
                                    <li>Você não tem permissão de escrita em nenhum lugar</li>
                                </ul>
                                <p class="mb-0"><strong>Solução:</strong></p>
                                <ol class="mt-2 mb-0">
                                    <li>Peça ao administrador do servidor o caminho correto para uploads</li>
                                    <li>Ou configure as variáveis de ambiente <code>FROTAS_UPLOAD_DIR</code> e <code>FROTAS_UPLOAD_URL</code></li>
                                    <li>Em container, configure um volume montado</li>
                                </ol>
                            </div>
                        <?php endif ?>
                    <?php endif ?>
                </div>
            </div>

            <div class="mt-4 text-center text-muted small">
                <p>Este script cria <code>/var/www/html/files/frotas</code> com permissões 775 para que a aplicação possa gravar arquivos sem problemas de acesso.</p>
                <p><a href="visualizar-upload.php" class="link-secondary">Ver diagnóstico completo</a></p>
            </div>
        </div>
    </div>
</main>
</body>
</html>

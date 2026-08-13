<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
exigirLogin();

header('Content-Type: text/html; charset=utf-8');

$portalRoot = realpath(__DIR__) ?: __DIR__;
$uploadDirPreferida = '/tmp/frotas_docs';
$baseHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
$baseScheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)) ? 'https' : 'http';
$uploadDir = rtrim((string) (getenv('FROTAS_UPLOAD_DIR') ?: (is_dir($uploadDirPreferida) && is_writable($uploadDirPreferida) ? $uploadDirPreferida : '/var/www/html/files/frotas')), '/\\');
$uploadUrl = rtrim((string) (getenv('FROTAS_UPLOAD_URL') ?: $baseScheme . '://' . $baseHost . '/diagnostico-uploads.php?abrir='), '/');
$ignorar = ['.git', 'node_modules', 'vendor'];
$diretorios = [];
$estruturaServidor = [];
$mensagemUpload = '';
$arquivoEnviado = null;

if (isset($_GET['abrir']) && is_string($_GET['abrir'])) {
    $nomeArquivo = basename((string) $_GET['abrir']);
    $caminhosPossiveis = [
        $uploadDir . DIRECTORY_SEPARATOR . $nomeArquivo,
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

$adicionarEstrutura = static function (string $raiz, array &$colecao): void {
    if (!is_dir($raiz)) {
        $colecao[] = ['caminho' => $raiz, 'tipo' => 'inexistente'];
        return;
    }

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD // ignora erros ao abrir subpastas
        );
    } catch (Throwable $e) {
        $colecao[] = ['caminho' => $raiz, 'tipo' => 'acesso-negado'];
        return;
    }

    $itens = [];
    foreach ($iterator as $item) {
        try {
            $itens[] = [
                'caminho' => $item->getPathname(),
                'tipo' => $item->isDir() ? 'diretorio' : 'arquivo',
            ];
        } catch (Throwable $e) {
            continue;
        }
    }

    $colecao[] = ['caminho' => $raiz, 'tipo' => 'raiz', 'filhos' => $itens];
};

foreach (['/var', '/var/www', '/var/www/html', '/html', '/app', '/apps', '/srv', '/tmp'] as $raiz) {
    $adicionarEstrutura($raiz, $estruturaServidor);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($portalRoot, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $item) use ($ignorar): bool {
            return !$item->isLink() && !in_array($item->getFilename(), $ignorar, true);
        }
    ),
    RecursiveIteratorIterator::SELF_FIRST
);

$adicionarDiretorio = static function (string $caminho, bool $externo = false) use (&$diretorios, $portalRoot): void {
    clearstatcache(true, $caminho);
    $existe = is_dir($caminho);
    $real = $existe ? realpath($caminho) : false;
    $diretorios[$caminho] = [
        'caminho' => $caminho,
        'relativo' => $externo ? 'Destino externo configurado' : ltrim(str_replace('\\', '/', substr($caminho, strlen($portalRoot))), '/'),
        'existe' => $existe,
        'legivel' => $existe && is_readable($caminho),
        'gravavel' => $existe && is_writable($caminho),
        'real' => $real !== false ? $real : '',
        'externo' => $externo,
    ];
};

$adicionarDiretorio($portalRoot);
foreach ($iterator as $item) {
    if ($item->isDir()) {
        $adicionarDiretorio($item->getPathname());
    }
}
$adicionarDiretorio($uploadDir, true);
ksort($diretorios, SORT_NATURAL | SORT_FLAG_CASE);

$candidatosUpload = [];
$raizesBusca = [$portalRoot, '/var/www/html', '/var/www', '/tmp'];
$visitadas = [];
foreach ($raizesBusca as $raizBusca) {
    $realRaizBusca = realpath($raizBusca) ?: $raizBusca;
    if (!is_dir($realRaizBusca) || in_array($realRaizBusca, $visitadas, true)) {
        continue;
    }
    $visitadas[] = $realRaizBusca;

    try {
        $iteratorBusca = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realRaizBusca, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
    } catch (Throwable $e) {
        continue;
    }

    foreach ($iteratorBusca as $item) {
        if (!$item->isDir()) {
            continue;
        }

        $caminhoItem = $item->getPathname();
        $nomeItem = strtolower($item->getFilename());
        $permitido = is_writable($caminhoItem) && (
            stripos($caminhoItem, 'files') !== false ||
            stripos($caminhoItem, 'upload') !== false ||
            stripos($caminhoItem, 'frotas') !== false ||
            stripos($caminhoItem, 'docs') !== false ||
            in_array($nomeItem, ['files', 'uploads', 'frotas', 'docs', 'tmp'], true)
        );

        if ($permitido) {
            $candidatosUpload[$caminhoItem] = [
                'caminho' => $caminhoItem,
                'real' => realpath($caminhoItem) ?: $caminhoItem,
                'gravavel' => is_writable($caminhoItem),
                'legivel' => is_readable($caminhoItem),
            ];
        }
    }
}

if (!isset($candidatosUpload[$uploadDir]) && is_dir($uploadDir) && is_writable($uploadDir)) {
    $candidatosUpload[$uploadDir] = [
        'caminho' => $uploadDir,
        'real' => realpath($uploadDir) ?: $uploadDir,
        'gravavel' => true,
        'legivel' => is_readable($uploadDir),
    ];
}

ksort($candidatosUpload, SORT_NATURAL | SORT_FLAG_CASE);

$urlResultado = [
    'ok' => false,
    'status' => null,
    'erro' => '',
    'ip' => '',
    'tempo' => null,
    'methods' => '',
    'server' => '',
    'testeUpload' => [
        'ok' => false,
        'status' => null,
        'erro' => '',
        'caminho' => '',
    ],
];
$host = (string) parse_url($uploadUrl, PHP_URL_HOST);
if ($host === '') {
    $urlResultado['erro'] = 'URL inválida: host não encontrado.';
} else {
    $ip = gethostbyname($host);
    $urlResultado['ip'] = $ip !== $host ? $ip : '';
    if (!function_exists('curl_init')) {
        $urlResultado['erro'] = 'A extensão cURL não está disponível no PHP.';
    } else {
        $curl = curl_init($uploadUrl . '/');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'Autofrotas-Diagnostico/1.0',
            CURLOPT_CUSTOMREQUEST => 'OPTIONS',
        ]);
        $headersResposta = curl_exec($curl);
        $urlResultado['status'] = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $urlResultado['tempo'] = curl_getinfo($curl, CURLINFO_TOTAL_TIME);
        $urlResultado['erro'] = curl_error($curl);

        if (is_string($headersResposta) && $headersResposta !== '') {
            $cabecalhos = explode("\r\n", $headersResposta);
            foreach ($cabecalhos as $cabecalho) {
                if (stripos($cabecalho, 'Allow:') === 0) {
                    $urlResultado['methods'] = trim(substr($cabecalho, 6));
                }
                if (stripos($cabecalho, 'Server:') === 0) {
                    $urlResultado['server'] = trim(substr($cabecalho, 7));
                }
            }
        }

        $urlResultado['ok'] = $urlResultado['status'] >= 200 && $urlResultado['status'] < 400;
        curl_close($curl);

        $metodos = strtolower((string) $urlResultado['methods']);
        $aceitaUpload = stripos($metodos, 'put') !== false || stripos($metodos, 'post') !== false || stripos($metodos, 'mkcol') !== false;

        if ($urlResultado['ok'] && !$aceitaUpload) {
            $urlResultado['erro'] = 'URL respondeu, mas não expõe métodos de upload (PUT/POST) na raiz do destino.';
        }

        $arquivoTeste = 'frotas-diagnostico-' . time() . '.txt';
        $caminhoTeste = rtrim($uploadUrl, '/') . '/' . rawurlencode($arquivoTeste);
        $curlUpload = curl_init($caminhoTeste);
        curl_setopt_array($curlUpload, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'Autofrotas-Diagnostico/1.0',
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => "diagnostico de upload\n",
            CURLOPT_HTTPHEADER => ['Content-Type: text/plain'],
        ]);
        $resultadoTeste = curl_exec($curlUpload);
        $statusTeste = curl_getinfo($curlUpload, CURLINFO_RESPONSE_CODE);
        $erroTeste = curl_error($curlUpload);
        curl_close($curlUpload);

        $urlResultado['testeUpload'] = [
            'ok' => in_array($statusTeste, [200, 201, 202, 204], true),
            'status' => $statusTeste,
            'erro' => $erroTeste,
            'caminho' => $caminhoTeste,
        ];

        if (!empty($resultadoTeste) && !in_array($statusTeste, [200, 201, 202, 204], true) && $urlResultado['erro'] === '') {
            $urlResultado['erro'] = 'A URL está acessível, mas a gravação de teste falhou.';
        }

        if ($urlResultado['testeUpload']['ok']) {
            $curlDelete = curl_init($caminhoTeste);
            curl_setopt_array($curlDelete, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_USERAGENT => 'Autofrotas-Diagnostico/1.0',
                CURLOPT_CUSTOMREQUEST => 'DELETE',
            ]);
            curl_exec($curlDelete);
            curl_close($curlDelete);
        }
    }
}

$total = count($diretorios);
$gravaveis = count(array_filter($diretorios, static fn(array $item): bool => $item['gravavel']));
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
        <div><h1 class="h2 mb-1">Diagnóstico de uploads</h1><p class="text-muted mb-0">Verificação executada pelo mesmo usuário do processo PHP.</p></div>
        <button class="btn btn-primary" type="button" onclick="window.location.reload()">Verificar novamente</button>
    </div>

    <section class="card shadow-sm mb-4">
        <div class="card-header"><h2 class="h5 mb-0">Destino centralizado</h2></div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-md-3">Diretório físico</dt><dd class="col-md-9"><code><?= $escape($uploadDir) ?></code></dd>
                <dt class="col-md-3">Situação da pasta</dt><dd class="col-md-9"><?php $destino=$diretorios[$uploadDir]; ?><span class="badge text-bg-<?= $destino['existe'] ? 'success' : 'danger' ?>"><?= $destino['existe'] ? 'Existe' : 'Não existe' ?></span> <span class="badge text-bg-<?= $destino['gravavel'] ? 'success' : 'danger' ?>"><?= $destino['gravavel'] ? 'Pode receber arquivos' : 'Sem permissão de escrita' ?></span><?php if (!$destino['gravavel']): ?><div class="mt-2"><a class="btn btn-sm btn-warning" href="setup-upload-dir.php">Criar pasta com mkdir()</a></div><?php endif ?></dd>
                <dt class="col-md-3">URL pública</dt><dd class="col-md-9"><a href="<?= $escape($uploadUrl) ?>/" target="_blank" rel="noopener noreferrer"><?= $escape($uploadUrl) ?>/</a></dd>
                <dt class="col-md-3">Validação da URL</dt><dd class="col-md-9"><span class="badge text-bg-<?= $urlResultado['ok'] ? 'success' : 'danger' ?>"><?= $urlResultado['ok'] ? 'URL acessível' : 'URL não validada' ?></span> HTTP <?= $escape($urlResultado['status'] ?? 'sem resposta') ?><?= $urlResultado['ip'] ? ' · IP '.$escape($urlResultado['ip']) : '' ?><?= $urlResultado['tempo'] !== null ? ' · '.number_format((float)$urlResultado['tempo'], 3, ',', '.').' s' : '' ?><?php if ($urlResultado['methods']): ?><div class="small text-muted mt-1">Métodos disponíveis: <?= $escape($urlResultado['methods']) ?></div><?php endif ?><?php if ($urlResultado['erro']): ?><div class="text-danger mt-1"><?= $escape($urlResultado['erro']) ?></div><?php endif ?></dd>
                <dt class="col-md-3">Teste de gravação</dt><dd class="col-md-9"><span class="badge text-bg-<?= $urlResultado['testeUpload']['ok'] ? 'success' : 'warning' ?>"><?= $urlResultado['testeUpload']['ok'] ? 'Gravação OK' : 'Gravação falhou' ?></span> <?= $urlResultado['testeUpload']['status'] !== null ? 'HTTP '.$escape((string)$urlResultado['testeUpload']['status']) : 'sem resposta' ?><?php if ($urlResultado['testeUpload']['caminho']): ?><div class="small text-muted mt-1">Caminho teste: <?= $escape($urlResultado['testeUpload']['caminho']) ?></div><?php endif ?><?php if ($urlResultado['testeUpload']['erro']): ?><div class="text-warning mt-1"><?= $escape($urlResultado['testeUpload']['erro']) ?></div><?php endif ?></dd>
            </dl>
            <div class="alert alert-warning mt-3 mb-0"><strong>Atenção:</strong> a URL estar acessível não garante que o diretório físico compartilhado accepte escrita via HTTP. Se o OPTIONS mostrar métodos sem PUT/POST ou o teste de gravação falhar, o problema é na configuração do servidor de arquivos ou no mapeamento do diretório.</div>
        </div>
    </section>

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
                    <button class="btn btn-success" type="submit">Enviar para /tmp/frotas_docs</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card shadow-sm mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between gap-2"><h2 class="h5 mb-0">Estrutura do servidor</h2><span>Árvores comuns do sistema</span></div>
        <div class="card-body">
            <?php foreach ($estruturaServidor as $entrada): ?>
                <?php $caminho = $entrada['caminho'] ?? ''; ?>
                <div class="border rounded p-2 mb-2">
                    <div class="fw-semibold mb-1"><?= $escape($caminho) ?></div>
                    <?php if (($entrada['tipo'] ?? '') === 'inexistente'): ?>
                        <span class="badge text-bg-secondary">Não existe</span>
                    <?php elseif (($entrada['tipo'] ?? '') === 'acesso-negado'): ?>
                        <span class="badge text-bg-warning">Acesso negado</span>
                    <?php elseif (!empty($entrada['filhos'])): ?>
                        <div class="small text-muted mb-1">Primeiros itens encontrados:</div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach (array_slice($entrada['filhos'], 0, 15) as $filho): ?>
                                <span class="badge text-bg-light border"><?= $escape($filho['tipo']) ?>: <?= $escape(basename($filho['caminho'])) ?></span>
                            <?php endforeach ?>
                        </div>
                    <?php else: ?>
                        <span class="badge text-bg-info">Diretório vazio ou sem itens visíveis</span>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    </section>

    <section class="card shadow-sm mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between gap-2"><h2 class="h5 mb-0">Pastas candidatas para receber arquivos</h2><span><?= count($candidatosUpload) ?> diretórios encontrados</span></div>
        <div class="card-body">
            <?php if ($candidatosUpload === []): ?>
                <div class="alert alert-secondary mb-0">Nenhuma pasta com nome ou caminho compatível com upload foi encontrada com permissão de escrita.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead><tr><th>Pasta</th><th>Leitura</th><th>Escrita</th><th>Caminho real</th></tr></thead>
                        <tbody>
                            <?php foreach ($candidatosUpload as $item): ?>
                                <tr>
                                    <td><code><?= $escape($item['caminho']) ?></code></td>
                                    <td><span class="badge text-bg-<?= $item['legivel'] ? 'success' : 'danger' ?>"><?= $item['legivel'] ? 'Sim' : 'Não' ?></span></td>
                                    <td><span class="badge text-bg-<?= $item['gravavel'] ? 'success' : 'danger' ?>"><?= $item['gravavel'] ? 'Permitida' : 'Negada' ?></span></td>
                                    <td><small><?= $escape($item['real']) ?></small></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </div>
    </section>

    <section class="card shadow-sm">
        <div class="card-header d-flex flex-wrap justify-content-between gap-2"><h2 class="h5 mb-0">Todos os diretórios do portal</h2><span><?= $gravaveis ?> de <?= $total ?> diretórios com escrita</span></div>
        <div class="card-body"><input class="form-control mb-3" id="filtro" type="search" placeholder="Filtrar por diretório..."><div class="table-responsive"><table class="table table-striped table-hover align-middle" id="tabela"><thead><tr><th>Diretório</th><th>Existe</th><th>Leitura</th><th>Escrita/upload</th><th>Caminho real</th></tr></thead><tbody>
        <?php foreach ($diretorios as $item): ?><tr<?= $item['externo'] ? ' class="table-info"' : '' ?>><td><code><?= $escape($item['relativo'] ?: '.') ?></code></td><td><span class="badge text-bg-<?= $item['existe'] ? 'success' : 'danger' ?>"><?= $item['existe'] ? 'Sim' : 'Não' ?></span></td><td><span class="badge text-bg-<?= $item['legivel'] ? 'success' : 'danger' ?>"><?= $item['legivel'] ? 'Sim' : 'Não' ?></span></td><td><span class="badge text-bg-<?= $item['gravavel'] ? 'success' : 'danger' ?>"><?= $item['gravavel'] ? 'Permitida' : 'Negada' ?></span></td><td><small><?= $escape($item['real'] ?: $item['caminho']) ?></small></td></tr><?php endforeach ?>
        </tbody></table></div></div>
    </section>
</main>
<script>document.getElementById('filtro').addEventListener('input',function(){const termo=this.value.toLowerCase();document.querySelectorAll('#tabela tbody tr').forEach(function(linha){linha.hidden=!linha.textContent.toLowerCase().includes(termo)})});</script>
</body>
</html>
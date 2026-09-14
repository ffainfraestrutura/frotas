<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/autofrota_common.php';
$autofrota = autofrotaInit();

$conn = $autofrota['conn'] ?? null;
$databaseName = (string) ($autofrota['databaseName'] ?? '');
$perfil = trim((string) ($autofrota['perfil'] ?? ''));
$idVistoria = (int) ($_GET['id'] ?? $_GET['idtbvistoria'] ?? 0);
$vistoria = [];
$fotos = [];
$erro = '';

if ($perfil !== '4') {
    http_response_code(403);
    exit('Acesso permitido apenas para perfil 4.');
}

if (!$conn instanceof mysqli || preg_match('/^[A-Za-z0-9_]+$/', $databaseName) !== 1) {
    $erro = 'Não foi possível conectar à base de vistorias.';
} elseif ($idVistoria < 1) {
    $erro = 'Vistoria inválida.';
} else {
    $sql = "SELECT v.idtbvistoria, v.placa, v.datavistoria, v.nome,
                   COALESCE(tv.tipo, v.tipo) AS tipo_vistoria
              FROM `{$databaseName}`.`tbvistoria` v
         LEFT JOIN `{$databaseName}`.`tbatipovist` tv ON tv.idtbatipovist = v.tipo
             WHERE v.idtbvistoria = ? AND v.statusreg = 1
             LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $idVistoria);
        if (mysqli_stmt_execute($stmt)) {
            $vistoria = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        }
        mysqli_stmt_close($stmt);
    }

    if ($vistoria) {
        $stmtFotos = mysqli_prepare(
            $conn,
            "SELECT frontal, traseira, direita, esquerda, bateria, painel, selfie, cnh,
                    extra1, extra2, extra3, extra4, extra5
               FROM `{$databaseName}`.`tbvistoriafotos`
              WHERE idtbvistoria = ?
                AND idtbvistfotos NOT IN (40, 41, 42, 43, 44, 45, 47)
              ORDER BY idtbvistfotos DESC
              LIMIT 1"
        );
        if ($stmtFotos) {
            mysqli_stmt_bind_param($stmtFotos, 'i', $idVistoria);
            if (mysqli_stmt_execute($stmtFotos)) {
                $fotos = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtFotos)) ?: [];
            }
            mysqli_stmt_close($stmtFotos);
        }
    } else {
        $erro = 'Vistoria não encontrada.';
    }
}

$camposFotos = [
    'frontal' => 'Frontal', 'traseira' => 'Traseira', 'direita' => 'Lateral direita',
    'esquerda' => 'Lateral esquerda', 'bateria' => 'Bateria', 'painel' => 'Painel',
    'selfie' => 'Selfie', 'cnh' => 'CNH', 'extra1' => 'Extra 1', 'extra2' => 'Extra 2',
    'extra3' => 'Extra 3', 'extra4' => 'Extra 4', 'extra5' => 'Extra 5',
];
$urlFoto = static function (mixed $caminho): string {
    $caminho = trim((string) $caminho);
    if ($caminho === '') {
        return '';
    }
    if (filter_var($caminho, FILTER_VALIDATE_URL) !== false
        && in_array(strtolower((string) parse_url($caminho, PHP_URL_SCHEME)), ['http', 'https'], true)) {
        return $caminho;
    }
    if (str_starts_with($caminho, '/visualizar-upload.php') || str_starts_with($caminho, '/frotas/')) {
        return $caminho;
    }
    $arquivo = basename(str_replace('\\', '/', $caminho));
    return $arquivo !== '' ? '/frotas/checklist/docs/' . rawurlencode($arquivo) : '';
};
$escape = static fn(mixed $valor): string => htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$dataFormatada = !empty($vistoria['datavistoria']) && ($data = date_create((string) $vistoria['datavistoria']))
    ? $data->format('d/m/Y H:i:s')
    : (string) ($vistoria['datavistoria'] ?? '');
$fotosExibidas = [];
foreach ($camposFotos as $campo => $rotulo) {
    $url = $urlFoto($fotos[$campo] ?? '');
    if ($url !== '') {
        $fotosExibidas[] = ['rotulo' => $rotulo, 'url' => $url];
    }
}
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fotos da Vistoria - AutoFrota</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body{background:#f5f7fb;color:#212529}.photos-page{max-width:1200px}.photo-card{border:0;box-shadow:0 .25rem 1rem #0f172a14;overflow:hidden}.photo{background:#eef2f7;height:330px;object-fit:contain;width:100%}.photo-link{display:block}.meta{color:#64748b}.empty-icon{font-size:3rem;color:#94a3b8}.photo-placeholder{align-items:center;background:#eef2f7;color:#64748b;display:flex;font-weight:600;height:330px;justify-content:center;width:100%}
    </style>
</head>
<body>
<?php autofrotaMenu(); ?>
<main class="container-fluid photos-page px-4 pb-5">
    <header class="d-flex flex-wrap justify-content-between align-items-center gap-3 py-4">
        <div>
            <h1 class="h2 mb-1"><i class="fas fa-images text-primary me-2"></i>Fotos da vistoria</h1>
            <?php if ($vistoria): ?>
                <p class="meta mb-0">Placa <?= $escape(strtoupper((string) $vistoria['placa'])) ?> · <?= $escape($dataFormatada) ?> · <?= $escape($vistoria['tipo_vistoria'] ?? '') ?></p>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" type="button" onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = 'relatorio-vistoria-por-placa.php'; }"><i class="fas fa-arrow-left me-1"></i>Voltar</button>
            <button class="btn btn-outline-danger" type="button" onclick="window.close()"><i class="fas fa-times me-1"></i>Fechar</button>
            <?php if ($vistoria): ?><a class="btn btn-success" href="verrelatorio.php?id=<?= $idVistoria ?>"><i class="fas fa-file-lines me-1"></i>Ver relatório</a><?php endif; ?>
        </div>
    </header>

    <?php if ($erro !== ''): ?>
        <div class="alert alert-warning" role="alert"><?= $escape($erro) ?></div>
    <?php else: ?>
        <?php if (!$fotosExibidas): ?>
            <div class="alert alert-warning" role="alert"><i class="fas fa-exclamation-triangle me-1"></i>Esta vistoria não possui imagens salvas.</div>
            <div class="row g-4">
                <?php foreach ($camposFotos as $rotuloEsperado): ?>
                    <div class="col-md-6 col-lg-4">
                        <article class="card photo-card h-100">
                            <div class="photo-placeholder">Sem foto</div>
                            <div class="card-body"><h2 class="h6 mb-0"><?= $escape($rotuloEsperado) ?></h2></div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($fotosExibidas as $foto): ?>
                <div class="col-md-6 col-lg-4">
                    <article class="card photo-card h-100">
                        <a class="photo-link" href="<?= $escape($foto['url']) ?>" target="_blank" rel="noopener" title="Abrir imagem em tamanho original">
                            <img class="photo" src="<?= $escape($foto['url']) ?>" alt="Foto <?= $escape($foto['rotulo']) ?>" loading="lazy">
                        </a>
                        <div class="card-body"><h2 class="h6 mb-0"><?= $escape($foto['rotulo']) ?></h2></div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
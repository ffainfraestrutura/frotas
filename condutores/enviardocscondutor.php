<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();

header('Content-Type: text/html; charset=utf-8');

$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');

$matriculaLogada = (string) ($autofrotaSessao['matricula'] ?? $_POST['matr_autor'] ?? '');
$matriculaFuncionario = trim((string) ($_POST['matcondutor'] ?? $_POST['matricula'] ?? $_GET['matcondutor'] ?? $_GET['matricula'] ?? ''));
$mensagemErro = '';
$dados = null;
$ufs = $conn instanceof mysqli ? buscarUfsPortal($conn) : [];

function buscarDocumentosCondutor(mysqli $conn, string $databaseName, string $matricula): ?array
{
    if ($matricula === '') {
        return null;
    }

    $sql = "
        SELECT cn.*, f.nome
        FROM `{$databaseName}`.`tbcnh` cn
        INNER JOIN `{$databaseName}`.`tbfuncionario` f
            ON f.matricula = cn.matricula
        WHERE cn.matricula = ?
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 's', $matricula);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $registro = $resultado ? mysqli_fetch_assoc($resultado) : null;

    if ($resultado) {
        mysqli_free_result($resultado);
    }

    mysqli_stmt_close($stmt);

    return $registro ?: null;
}

function hrefDocumentoCondutor(?string $caminho): string
{
    $caminho = trim((string) $caminho);
    if ($caminho === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $caminho)) {
        return $caminho;
    }

    return './' . ltrim($caminho, '/');
}

if ($matriculaFuncionario === '') {
    $mensagemErro = 'Nenhuma matrícula foi informada para envio de documentos.';
} elseif (!isset($conn) || !($conn instanceof mysqli)) {
    $mensagemErro = 'Não foi possível conectar ao banco de dados.';
} else {
    $dados = buscarDocumentosCondutor($conn, $databaseName, $matriculaFuncionario);

    if (!$dados) {
        $mensagemErro = 'CNH não encontrada para a matrícula informada.';
    }
}

$validade = isset($dados['validade']) ? substr((string) $dados['validade'], 0, 10) : '';
$consulta = isset($dados['consulta']) ? substr((string) $dados['consulta'], 0, 10) : '';
$docAtual = !empty($dados['doc2']) ? (string) $dados['doc2'] : (string) ($dados['doc1'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="AutoFrota" />
    <meta name="author" content="FFA" />
    <title>AutoFrota - Enviar Documentos</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body { background: #ffffff; color: #212529; font-size: 14px; }
        .content-container { width: 90%; }
        .page-content { padding-top: 1.25rem; }
        .doc-row { border-top: 1px solid #dee2e6; padding-top: 14px; margin-top: 14px; }
        @media (max-width: 992px) { .content-container { width: 100%; } }
    </style>
</head>
<body class="sb-nav-fixed vsc-initialized sb-sidenav-toggled">
    <?php autofrotaMenu(); ?>

    <div id="layoutSidenav">
    <div id="layoutSidenav_content">
        <main style="width: 100%;" class="mb-2 py-3 page-content">
            <div class="container-fluid px-4 content-container">
                <h1 class="h1 pt-2 pb-2">Enviar Documentos</h1>

                <?php if ($mensagemErro !== ''): ?>
                    <div class="alert alert-warning mt-3" role="alert">
                        <?= htmlspecialchars($mensagemErro) ?>
                    </div>
                    <div class="mt-3 d-flex gap-3">
                        <button class="btn btn-secondary" type="button" onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = 'listagemcnh.php'; }">Fechar</button>
                        <a class="btn btn-secondary" href="listagemcnh.php">Listagem de CNH</a>
                    </div>
                <?php else: ?>
                    <form method="post" action="./control/enviardocscond.php" enctype="multipart/form-data" class="pb-5 mb-5">
                        <input type="hidden" name="matcond" value="<?= htmlspecialchars((string) ($dados['matricula'] ?? '')) ?>">
                        <input type="hidden" name="mat_autor" value="<?= htmlspecialchars($matriculaLogada) ?>">

                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label" for="nome">Nome:</label>
                                <input class="form-control" type="text" name="nome" id="nome" value="<?= htmlspecialchars((string) ($dados['nome'] ?? '')) ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="matricula">Matrícula:</label>
                                <input class="form-control" type="text" name="matricula" id="matricula" value="<?= htmlspecialchars((string) ($dados['matricula'] ?? '')) ?>" readonly>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-3">
                                <label class="form-label" for="cnh">Número da CNH:</label>
                                <input class="form-control" type="text" name="cnh" id="cnh" value="<?= htmlspecialchars((string) ($dados['numcnh'] ?? '')) ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="validade">Validade:</label>
                                <input class="form-control" type="date" name="validade" id="validade" value="<?= htmlspecialchars($validade) ?>" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="uf">U.F.:</label>
                                <select class="form-select" name="uf" id="uf" disabled>
                                    <?php foreach ($ufs as $uf): ?>
                                        <option value="<?= htmlspecialchars($uf) ?>" <?= (string) ($dados['uf'] ?? '') === $uf ? 'selected' : '' ?>><?= htmlspecialchars($uf) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="categoria">Categoria:</label>
                                <input class="form-control" type="text" name="categoria" id="categoria" value="<?= htmlspecialchars((string) ($dados['categoria'] ?? '')) ?>" readonly>
                            </div>
                        </div>

                        <div class="row g-3 mt-1 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label" for="pontos">Pontos:</label>
                                <input class="form-control" type="text" name="pontos" id="pontos" value="<?= htmlspecialchars((string) ($dados['pontos'] ?? '')) ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="consulta">Consulta ao DETRAN:</label>
                                <input class="form-control" type="date" name="consulta" id="consulta" value="<?= htmlspecialchars($consulta) ?>" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="suspensa">Suspensa:</label>
                                <select name="suspensa" id="suspensa" class="form-select" disabled>
                                    <option value="0" <?= (string) ($dados['suspensa'] ?? '0') === '0' ? 'selected' : '' ?>>Não</option>
                                    <option value="1" <?= (string) ($dados['suspensa'] ?? '0') === '1' ? 'selected' : '' ?>>Sim</option>
                                </select>
                            </div>
                            <?php if ($docAtual !== ''): ?>
                                <div class="col-md-3">
                                    <a href="<?= htmlspecialchars(hrefDocumentoCondutor($docAtual)) ?>" class="btn btn-secondary" download>Ver CNH</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="doc-row row g-3 align-items-end">
                            <div class="col-md-7">
                                <label class="form-label" for="politicauso">Enviar Política de Uso:</label>
                                <input class="form-control" type="file" name="politicauso" id="politicauso" accept=".pdf,.doc,.docx">
                            </div>
                            <?php if (!empty($dados['politicauso'])): ?>
                                <div class="col-md-3"><a href="<?= htmlspecialchars(hrefDocumentoCondutor((string) $dados['politicauso'])) ?>" class="btn btn-secondary" download>Último Documento</a></div>
                            <?php endif; ?>
                        </div>

                        <div class="doc-row row g-3 align-items-end">
                            <div class="col-md-7">
                                <label class="form-label" for="termocombustivel">Enviar Termo de Responsabilidade Combustível:</label>
                                <input class="form-control" type="file" name="termocombustivel" id="termocombustivel" accept=".pdf,.doc,.docx">
                            </div>
                            <?php if (!empty($dados['termocombust'])): ?>
                                <div class="col-md-3"><a href="<?= htmlspecialchars(hrefDocumentoCondutor((string) $dados['termocombust'])) ?>" class="btn btn-secondary" download>Último Documento</a></div>
                            <?php endif; ?>
                        </div>

                        <div class="doc-row row g-3 align-items-end">
                            <div class="col-md-7">
                                <label class="form-label" for="contrato">Enviar Contrato de Agregamento:</label>
                                <input class="form-control" type="file" name="contrato" id="contrato" accept=".pdf,.doc,.docx">
                            </div>
                            <?php if (!empty($dados['contratoagregamento'])): ?>
                                <div class="col-md-3"><a href="<?= htmlspecialchars(hrefDocumentoCondutor((string) $dados['contratoagregamento'])) ?>" class="btn btn-secondary" download>Último Documento</a></div>
                            <?php endif; ?>
                        </div>

                        <div class="doc-row row g-3 align-items-end">
                            <div class="col-md-7">
                                <label class="form-label" for="rescisao">Enviar Termo de Rescisão:</label>
                                <input class="form-control" type="file" name="rescisao" id="rescisao" accept=".pdf,.doc,.docx">
                            </div>
                            <?php if (!empty($dados['termorescisao'])): ?>
                                <div class="col-md-3"><a href="<?= htmlspecialchars(hrefDocumentoCondutor((string) $dados['termorescisao'])) ?>" class="btn btn-secondary" download>Último Documento</a></div>
                            <?php endif; ?>
                        </div>

                        <div class="doc-row row g-3 align-items-end">
                            <div class="col-md-7">
                                <label class="form-label" for="ultrecibo">Enviar Último Recibo:</label>
                                <input class="form-control" type="file" name="ultrecibo" id="ultrecibo" accept=".pdf,.doc,.docx">
                            </div>
                            <?php if (!empty($dados['ultrecibo'])): ?>
                                <div class="col-md-3"><a href="<?= htmlspecialchars(hrefDocumentoCondutor((string) $dados['ultrecibo'])) ?>" class="btn btn-secondary" download>Último Documento</a></div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-4 pb-3 d-flex justify-content-start gap-4">
                            <button class="btn btn-success" type="submit">Confirmar envio</button>
                            <button class="btn btn-secondary" type="button" onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = 'listagemcnh.php'; }">Cancelar envio</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>

        <footer class="mt-4 py-4 bg-light mt-auto">
            <div class="container-fluid px-4">
                <div class="d-flex align-items-center justify-content-between small">
                    <div class="text-muted">Copyright &copy; FFA Infraestrutura</div>
                </div>
            </div>
        </footer>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', function(event) {
        event.preventDefault();
        document.body.classList.toggle('sb-sidenav-toggled');
    });
</script>
</body>
</html>

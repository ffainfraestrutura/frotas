<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();

header('Content-Type: text/html; charset=utf-8');

$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');

$matriculaLogada = (string) ($autofrotaSessao['matricula'] ?? $_POST['matr_autor'] ?? '');
$matriculaFuncionario = trim((string) ($_POST['matricula'] ?? $_GET['matricula'] ?? ''));
$ufs = $conn instanceof mysqli ? buscarUfsPortal($conn) : [];
$mensagemErro = '';
$cnh = null;

function colunaExisteEditarCnh(mysqli $conn, string $databaseName, string $tabela, string $coluna): bool
{
    $sql = "SHOW COLUMNS FROM `{$databaseName}`.`{$tabela}` LIKE ?";
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 's', $coluna);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $existe = $resultado && mysqli_num_rows($resultado) > 0;

    if ($resultado) {
        mysqli_free_result($resultado);
    }

    mysqli_stmt_close($stmt);

    return $existe;
}

function buscarCnhParaEdicao(mysqli $conn, string $databaseName, string $matricula): ?array
{
    if ($matricula === '') {
        return null;
    }

    $camposFuncionario = 'f.nome';
    if (colunaExisteEditarCnh($conn, $databaseName, 'tbfuncionario', 'codempresa')) {
        $camposFuncionario .= ', f.codempresa';
    }

    $sql = "
        SELECT cn.*, {$camposFuncionario}
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

function caminhoDownloadCnh(?string $caminho): string
{
    $caminho = trim((string) $caminho);
    if ($caminho === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $caminho)) {
        return $caminho;
    }

    return '.' . '/' . ltrim($caminho, '/');
}

if ($matriculaFuncionario === '') {
    $mensagemErro = 'Nenhuma matrícula foi informada para edição de CNH.';
} elseif (!isset($conn) || !($conn instanceof mysqli)) {
    $mensagemErro = 'Não foi possível conectar ao banco de dados.';
} else {
    $cnh = buscarCnhParaEdicao($conn, $databaseName, $matriculaFuncionario);

    if (!$cnh) {
        $mensagemErro = 'CNH não encontrada para a matrícula informada.';
    }
}

$validade = isset($cnh['validade']) ? substr((string) $cnh['validade'], 0, 10) : '';
$consulta = isset($cnh['consulta']) ? substr((string) $cnh['consulta'], 0, 10) : '';
$docAtual = !empty($cnh['doc2']) ? (string) $cnh['doc2'] : (string) ($cnh['doc1'] ?? '');
$hrefDocAtual = caminhoDownloadCnh($docAtual);
$codEmpresaAtual = (string) ($cnh['codempresa'] ?? '1');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="AutoFrota" />
    <meta name="author" content="FFA" />
    <title>AutoFrota - Editar CNH</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body { background: #ffffff; color: #212529; font-size: 14px; }
        .content-wrap { width: 100%; }
        .form-wrap { width: 70%; }
        .row-fields { --bs-gutter-x: 1rem; }
        @media (max-width: 992px) { .form-wrap { width: 100%; } }
    </style>
</head>
<body class="sb-nav-fixed vsc-initialized sb-sidenav-toggled">
    <?php autofrotaMenu(); ?>

    <div id="layoutSidenav_content">
        <main class="content-wrap mb-2">
            <div class="container-fluid px-4 content-wrap">
                <h1 class="h1 pt-2 pb-2 text-center">Editar CNH</h1>

                <?php if ($mensagemErro !== ''): ?>
                    <div class="alert alert-warning mt-3" role="alert">
                        <?= htmlspecialchars($mensagemErro) ?>
                    </div>
                    <div class="mt-3 d-flex gap-3">
                        <button class="btn btn-secondary" type="button" onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = 'listagemcnh.php'; }">Fechar</button>
                        <a class="btn btn-secondary" href="listagemcnh.php">Listagem de CNH</a>
                    </div>
                <?php else: ?>
                    <form method="post" action="./control/editarcnh.php" class="pb-5 mb-5 m-auto form-wrap" enctype="multipart/form-data">
                        <input type="hidden" name="matr_autor" value="<?= htmlspecialchars($matriculaLogada) ?>">
                        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($cnh['idtbcnh'] ?? '')) ?>">

                        <div class="row row-fields">
                            <div class="col-sm-7 d-flex flex-column">
                                <label class="form-label mt-2" for="nome">Nome: <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="nome" id="nome" value="<?= htmlspecialchars((string) ($cnh['nome'] ?? '')) ?>" placeholder="Nome" required>
                            </div>

                            <div class="col-sm-3 d-flex flex-column">
                                <label class="form-label mt-2" for="matricula">Matrícula: <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="matricula" id="matricula" value="<?= htmlspecialchars((string) ($cnh['matricula'] ?? '')) ?>" placeholder="Matrícula" maxlength="6" required>
                            </div>
                        </div>

                        <div class="row row-fields">
                            <div class="col-sm-3 d-flex flex-column">
                                <label class="form-label mt-2" for="cnh">Número da CNH: <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="cnh" id="cnh" value="<?= htmlspecialchars((string) ($cnh['numcnh'] ?? '')) ?>" placeholder="000000000000" maxlength="12" required>
                            </div>

                            <div class="col-sm-3 d-flex flex-column">
                                <label class="form-label mt-2" for="validade">Validade: <span class="text-danger">*</span></label>
                                <input class="form-control" type="date" name="validade" id="validade" value="<?= htmlspecialchars($validade) ?>" required>
                            </div>

                            <div class="col-sm-2 d-flex flex-column">
                                <label class="form-label mt-2" for="uf">U.F.: <span class="text-danger">*</span></label>
                                <select class="form-select" name="uf" id="uf" required>
                                    <?php foreach ($ufs as $uf): ?>
                                        <option value="<?= htmlspecialchars($uf) ?>" <?= (string) ($cnh['uf'] ?? '') === $uf ? 'selected' : '' ?>><?= htmlspecialchars($uf) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-sm-2 d-flex flex-column">
                                <label class="form-label mt-2" for="categoria">Categoria: <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="categoria" id="categoria" value="<?= htmlspecialchars((string) ($cnh['categoria'] ?? '')) ?>" maxlength="5" required>
                            </div>
                        </div>

                        <div class="row row-fields">
                            <div class="col-sm-3 d-flex flex-column">
                                <label class="form-label mt-2" for="pontos">Pontos:</label>
                                <input class="form-control" type="text" name="pontos" id="pontos" value="<?= htmlspecialchars((string) ($cnh['pontos'] ?? '')) ?>" maxlength="3">
                            </div>

                            <div class="col-sm-3 d-flex flex-column">
                                <label class="form-label mt-2" for="consulta">Consulta ao DETRAN: <span class="text-danger">*</span></label>
                                <input class="form-control" type="date" name="consulta" id="consulta" value="<?= htmlspecialchars($consulta) ?>" required>
                            </div>

                            <div class="col-sm-3 d-flex flex-column">
                                <label class="form-label mt-2" for="suspensa">Suspensa: <span class="text-danger">*</span></label>
                                <select name="suspensa" id="suspensa" class="form-select" required>
                                    <option value="0" <?= (string) ($cnh['suspensa'] ?? '0') === '0' ? 'selected' : '' ?>>Não</option>
                                    <option value="1" <?= (string) ($cnh['suspensa'] ?? '0') === '1' ? 'selected' : '' ?>>Sim</option>
                                </select>
                            </div>
                        </div>

                        <div class="row row-fields">
                            <div class="col-sm-6 d-flex flex-column">
                                <label class="form-label mt-2" for="codempresa">Empresa emissora do cartão de combustível: <span class="text-danger">*</span></label>
                                <select name="codempresa" id="codempresa" class="form-select" required>
                                    <option value="1" <?= $codEmpresaAtual === '1' ? 'selected' : '' ?>>Ticketlog</option>
                                    <option value="2" <?= $codEmpresaAtual === '2' ? 'selected' : '' ?>>Valecard</option>
                                </select>
                            </div>
                        </div>

                        <div class="row row-fields mb-3 align-items-end">
                            <div class="col-sm-5 d-flex flex-column">
                                <label class="form-label mt-2" for="arquivo">Anexar habilitação:</label>
                                <input class="form-control" type="file" name="arquivo" id="arquivo" accept=".jpg,.jpeg,.png,.gif,.pdf">
                            </div>

                            <?php if ($hrefDocAtual !== ''): ?>
                                <div class="col-sm-3 mt-2">
                                    <a href="<?= htmlspecialchars($hrefDocAtual) ?>" class="btn btn-secondary" download>Última CNH anexada</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="border-top border-dark pt-2 text-danger">
                            * Campos obrigatórios.
                        </div>

                        <div class="mt-3 pb-2 d-flex col-sm-12 justify-content-center gap-5">
                            <button class="btn btn-success" type="submit">Confirmar edição</button>
                            <button class="btn btn-secondary" type="button" onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = 'listagemcnh.php'; }">Cancelar edição</button>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.15/jquery.mask.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', function(event) {
        event.preventDefault();
        document.body.classList.toggle('sb-sidenav-toggled');
    });

    $(document).ready(function() {
        $('#cnh').mask('000000000000', { reverse: true });
        $('#pontos').mask('000', { reverse: true });

        $('#nome, #categoria').on('input', function() {
            $(this).val($(this).val().toUpperCase());
        });
    });
</script>
</body>
</html>

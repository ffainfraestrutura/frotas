<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/autofrota_common.php';
$autofrotaSessao = autofrotaInit();

$conn = $GLOBALS['conn'] ?? null;
$perfilLogado = (string) ($autofrotaSessao['perfil'] ?? $_SESSION['perfil'] ?? '');

$banco = 'bdautofrotas';

/**
 * Nome do schema principal usado nas consultas das telas.
 *
 * As demais páginas devem reutilizar esta variável após incluir este arquivo,
 * evitando redefinição local de "$databaseName".
 *
 * @var string $databaseName
 */
$databaseName = (string) ($GLOBALS['databaseName'] ?? $banco);
if ($databaseName === '' || !preg_match('/^[A-Za-z0-9_]+$/', $databaseName)) {
    $databaseName = $banco;
}

$tabelaCotaFixa = sprintf('`%s`.`tbcotafixa`', str_replace('`', '``', $databaseName));
$tabelaCotaFixaSemSchema = '`tbcotafixa`';

if (!$conn instanceof mysqli) {
    http_response_code(500);
    exit('Conexão com o banco indisponível.');
}

if ($perfilLogado === '' || $perfilLogado === '0') {
    http_response_code(403);
    exit('Sem permissão para acessar a gestão de cotas fixas.');
}

mysqli_set_charset($conn, 'utf8mb4');

function escCotaFixa(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function normalizarValorCota(string $valor): ?float
{
    $valor = trim(str_replace(['R$', ' '], '', $valor));
    if ($valor === '') {
        return null;
    }

    if (str_contains($valor, ',')) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }

    if (!is_numeric($valor)) {
        return null;
    }

    $numero = (float) $valor;
    return $numero >= 0 && $numero <= 999999.99 ? $numero : null;
}

function redirecionarCotaFixa(string $tipo, string $mensagem): never
{
    $_SESSION['cotafixa_retorno'] = ['tipo' => $tipo, 'mensagem' => $mensagem];
    header('Location: cotafixa.php');
    exit;
}

function prepararCotaFixa(mysqli $conn, string $sqlComSchema, string $sqlSemSchema): mysqli_stmt|false
{
    $stmt = mysqli_prepare($conn, $sqlComSchema);
    if ($stmt === false) {
        $stmt = mysqli_prepare($conn, $sqlSemSchema);
    }
    return $stmt;
}

if (empty($_SESSION['cotafixa_csrf'])) {
    $_SESSION['cotafixa_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['cotafixa_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenRecebido = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $tokenRecebido)) {
        redirecionarCotaFixa('danger', 'Não foi possível validar a solicitação. Atualize a página e tente novamente.');
    }

    $acao = (string) ($_POST['action'] ?? '');
    $placa = strtoupper(trim((string) ($_POST['placa'] ?? '')));
    if ($placa === '' || strlen($placa) > 10) {
        redirecionarCotaFixa('danger', 'Informe uma placa válida.');
    }

    if ($acao === 'editar') {
        $valor = normalizarValorCota((string) ($_POST['valor'] ?? ''));
        if ($valor === null) {
            redirecionarCotaFixa('danger', 'Informe um valor de cota válido.');
        }

        $stmt = prepararCotaFixa(
            $conn,
            "UPDATE {$tabelaCotaFixa} SET valor = ? WHERE placa = ?",
            "UPDATE {$tabelaCotaFixaSemSchema} SET valor = ? WHERE placa = ?"
        );
        if (!$stmt) {
            redirecionarCotaFixa('danger', 'Não foi possível preparar a atualização da cota.');
        }
        mysqli_stmt_bind_param($stmt, 'ds', $valor, $placa);
        $sucesso = mysqli_stmt_execute($stmt);
        $alteradas = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if (!$sucesso) {
            redirecionarCotaFixa('danger', 'Não foi possível atualizar a cota fixa.');
        }
        redirecionarCotaFixa('success', $alteradas > 0 ? 'Cota fixa atualizada com sucesso.' : 'Nenhuma alteração foi necessária.');
    }

    if ($acao === 'adicionar') {
        $matricula = trim((string) ($_POST['matricula'] ?? ''));
        $valor = normalizarValorCota((string) ($_POST['valor'] ?? ''));
        if ($matricula === '' || strlen($matricula) > 30 || $valor === null) {
            redirecionarCotaFixa('danger', 'Preencha placa, matrícula e valor com dados válidos.');
        }

        $stmt = prepararCotaFixa(
            $conn,
            "INSERT INTO {$tabelaCotaFixa} (placa, matricula, valor) VALUES (?, ?, ?)",
            "INSERT INTO {$tabelaCotaFixaSemSchema} (placa, matricula, valor) VALUES (?, ?, ?)"
        );
        if (!$stmt) {
            redirecionarCotaFixa('danger', 'Não foi possível preparar o cadastro da cota.');
        }
        mysqli_stmt_bind_param($stmt, 'ssd', $placa, $matricula, $valor);
        $sucesso = mysqli_stmt_execute($stmt);
        $erro = mysqli_stmt_errno($stmt);
        mysqli_stmt_close($stmt);

        if (!$sucesso) {
            redirecionarCotaFixa('danger', $erro === 1062 ? 'Já existe uma cota cadastrada para essa placa.' : 'Não foi possível adicionar a cota fixa.');
        }
        redirecionarCotaFixa('success', 'Cota fixa adicionada com sucesso.');
    }

    if ($acao === 'remover') {
        $stmt = prepararCotaFixa(
            $conn,
            "DELETE FROM {$tabelaCotaFixa} WHERE placa = ?",
            "DELETE FROM {$tabelaCotaFixaSemSchema} WHERE placa = ?"
        );
        if (!$stmt) {
            redirecionarCotaFixa('danger', 'Não foi possível preparar a exclusão da cota.');
        }
        mysqli_stmt_bind_param($stmt, 's', $placa);
        $sucesso = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        redirecionarCotaFixa($sucesso ? 'success' : 'danger', $sucesso ? 'Cota fixa excluída com sucesso.' : 'Não foi possível excluir a cota fixa.');
    }

    redirecionarCotaFixa('danger', 'Ação inválida.');
}

$cotas = [];
$erroConsulta = '';
$resultado = mysqli_query($conn, "SELECT placa, matricula, valor FROM {$tabelaCotaFixa} ORDER BY matricula, placa");
if (!$resultado) {
    $resultado = mysqli_query($conn, "SELECT placa, matricula, valor FROM {$tabelaCotaFixaSemSchema} ORDER BY matricula, placa");
}
if ($resultado instanceof mysqli_result) {
    $cotas = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
    mysqli_free_result($resultado);
} else {
    $erroConsulta = 'Não foi possível carregar as cotas fixas no momento. Erro SQL: ' . mysqli_error($conn);
}

$retorno = $_SESSION['cotafixa_retorno'] ?? null;
unset($_SESSION['cotafixa_retorno']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cota fixa | AutoFrota</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="src/css/styles.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        .cotafixa-value { min-width: 135px; }
        .cotafixa-actions { min-width: 265px; }
        .cotafixa-empty { padding: 3rem 1rem !important; }
    </style>
</head>
<body>
<?php autofrotaMenu(); ?>
<main class="container-fluid px-3 px-lg-4 pb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Cota fixa</h1>
            <p class="text-muted mb-0">Cadastre e gerencie as cotas recorrentes dos veículos.</p>
        </div>
        <button class="btn btn-success" type="button" data-bs-toggle="modal" data-bs-target="#modalAdicionarCota">
            <i class="fa-solid fa-plus me-1"></i> Nova cota fixa
        </button>
    </div>

    <?php if (is_array($retorno)): ?>
        <div class="alert alert-<?= escCotaFixa($retorno['tipo'] ?? 'info') ?> alert-dismissible fade show" role="alert">
            <?= escCotaFixa($retorno['mensagem'] ?? '') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>
    <?php if ($erroConsulta !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= escCotaFixa($erroConsulta) ?></div>
    <?php endif; ?>

    <section class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <strong><i class="fa-solid fa-gas-pump text-success me-2"></i>Cotas cadastradas</strong>
            <span class="badge text-bg-light border"><?= count($cotas) ?> registro<?= count($cotas) === 1 ? '' : 's' ?></span>
        </div>
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-md-6 col-lg-4">
                    <label for="filtroCotas" class="visually-hidden">Pesquisar cotas</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input id="filtroCotas" class="form-control" type="search" placeholder="Pesquisar por placa ou matrícula">
                    </div>
                </div>
                <div class="col-md-auto ms-md-auto">
                    <button id="btnExportarCotas" class="btn btn-outline-success" type="button">
                        <i class="fa-solid fa-file-excel me-1"></i> Exportar Excel
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table id="tabelaCotas" class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Placa</th><th>Matrícula</th><th>Valor</th><th class="text-end">Ações</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cotas as $cota): ?>
                        <tr>
                            <td><span class="badge text-bg-dark fs-6"><?= escCotaFixa($cota['placa']) ?></span></td>
                            <td><?= escCotaFixa($cota['matricula']) ?></td>
                            <td class="fw-semibold">R$ <?= number_format((float) $cota['valor'], 2, ',', '.') ?></td>
                            <td class="text-end cotafixa-actions">
                                <form method="post" class="d-inline-flex gap-2 cotafixa-value">
                                    <input type="hidden" name="csrf_token" value="<?= escCotaFixa($csrfToken) ?>">
                                    <input type="hidden" name="action" value="editar">
                                    <input type="hidden" name="placa" value="<?= escCotaFixa($cota['placa']) ?>">
                                    <label class="visually-hidden" for="valor-<?= escCotaFixa($cota['placa']) ?>">Novo valor</label>
                                    <input id="valor-<?= escCotaFixa($cota['placa']) ?>" name="valor" class="form-control form-control-sm campo-moeda" inputmode="decimal" value="<?= escCotaFixa(number_format((float) $cota['valor'], 2, ',', '.')) ?>" required>
                                    <button class="btn btn-sm btn-outline-primary" type="submit" title="Salvar novo valor" aria-label="Salvar novo valor"><i class="fa-solid fa-floppy-disk"></i></button>
                                </form>
                                <form method="post" class="d-inline" data-form-excluir data-placa="<?= escCotaFixa($cota['placa']) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= escCotaFixa($csrfToken) ?>">
                                    <input type="hidden" name="action" value="remover">
                                    <input type="hidden" name="placa" value="<?= escCotaFixa($cota['placa']) ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Excluir cota" aria-label="Excluir cota"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($cotas === []): ?>
                        <tr id="linhaSemCotas"><td colspan="4" class="text-center text-muted cotafixa-empty"><i class="fa-solid fa-inbox fa-2x d-block mb-2"></i>Nenhuma cota fixa cadastrada.</td></tr>
                    <?php endif; ?>
                    <tr id="linhaSemResultado" class="d-none"><td colspan="4" class="text-center text-muted cotafixa-empty">Nenhuma cota corresponde à pesquisa.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>

<div class="modal fade" id="modalAdicionarCota" tabindex="-1" aria-labelledby="tituloAdicionarCota" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
        <form method="post">
            <div class="modal-header"><h2 class="modal-title fs-5" id="tituloAdicionarCota">Adicionar cota fixa</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= escCotaFixa($csrfToken) ?>">
                <input type="hidden" name="action" value="adicionar">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label" for="novaPlaca">Placa</label><input id="novaPlaca" name="placa" class="form-control text-uppercase" maxlength="10" autocomplete="off" required></div>
                    <div class="col-md-4"><label class="form-label" for="novaMatricula">Matrícula</label><input id="novaMatricula" name="matricula" class="form-control" maxlength="30" required></div>
                    <div class="col-md-4"><label class="form-label" for="novoValor">Valor</label><div class="input-group"><span class="input-group-text">R$</span><input id="novoValor" name="valor" class="form-control campo-moeda" inputmode="decimal" placeholder="0,00" required></div></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-success"><i class="fa-solid fa-check me-1"></i> Adicionar cota</button></div>
        </form>
    </div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const filtro = document.getElementById('filtroCotas');
    const linhas = Array.from(document.querySelectorAll('#tabelaCotas tbody tr')).filter((linha) => !linha.id);
    const semResultado = document.getElementById('linhaSemResultado');

    filtro.addEventListener('input', function () {
        const termo = this.value.trim().toLocaleLowerCase('pt-BR');
        let visiveis = 0;
        linhas.forEach(function (linha) {
            const exibir = linha.textContent.toLocaleLowerCase('pt-BR').includes(termo);
            linha.classList.toggle('d-none', !exibir);
            if (exibir) visiveis++;
        });
        semResultado.classList.toggle('d-none', termo === '' || visiveis > 0);
    });

    document.querySelectorAll('[data-form-excluir]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm('Tem certeza que deseja excluir a cota da placa ' + form.dataset.placa + '?')) event.preventDefault();
        });
    });

    document.querySelectorAll('.campo-moeda').forEach(function (campo) {
        campo.addEventListener('input', function () { this.value = this.value.replace(/[^0-9.,]/g, ''); });
    });

    document.getElementById('btnExportarCotas').addEventListener('click', function () {
        const tabela = document.getElementById('tabelaCotas').cloneNode(true);
        tabela.querySelectorAll('tr').forEach(function (linha) { if (linha.lastElementChild) linha.lastElementChild.remove(); });
        tabela.querySelectorAll('.d-none').forEach(function (linha) { linha.remove(); });
        const blob = new Blob(['\ufeff' + tabela.outerHTML], {type: 'application/vnd.ms-excel;charset=utf-8'});
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'cotas-fixas.xls';
        link.click();
        URL.revokeObjectURL(link.href);
    });
})();
</script>
</body>
</html>
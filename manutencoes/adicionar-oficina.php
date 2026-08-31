<?php
require_once __DIR__ . '/../auth.php';

exigirLogin();

$perfilLogado = (string) ($_SESSION['perfil'] ?? '');
$podeEditar = $perfilLogado === '4';
$placa = strtoupper(trim((string) ($_GET['placa'] ?? $_POST['placa'] ?? '')));
$placa = str_replace(['-', ' '], '', $placa);
$mensagem = trim((string) ($_GET['msg'] ?? ''));
$origem = (string) ($_GET['origem'] ?? $_POST['origem'] ?? '');
$origem = $origem === 'cadastro-veiculo' ? $origem : 'manutencao';

function esc(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Adicionar Oficina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="sb-nav-fixed bg-light">
<?php include __DIR__ . '/../includes/menu_superior_simples.php'; ?>
<div id="layoutSidenav_content">
    <main class="container py-4">
        <h1 class="h2 text-center mb-4">Adicionar oficina</h1>
        <?php if ($mensagem !== ''): ?>
            <div class="alert alert-danger m-auto mb-3" style="max-width: 680px;"><?= esc($mensagem) ?></div>
        <?php endif; ?>
        <form method="post" action="control/salvar-oficina.php" class="card card-body m-auto" style="max-width: 680px;">
            <input type="hidden" name="placa" value="<?= esc($placa) ?>">
            <input type="hidden" name="origem" value="<?= esc($origem) ?>">
            <?php if (!$podeEditar): ?>
                <div class="alert alert-warning">Cadastro disponível apenas para perfis autorizados.</div>
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label" for="nome">Nome:<span class="text-danger">*</span></label>
                <input class="form-control" id="nome" name="nome" maxlength="150" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label" for="telefone">Telefone</label>
                <input class="form-control" id="telefone" name="telefone" type="tel" maxlength="30">
            </div>
            <div class="d-flex gap-3">
                <?php if ($podeEditar): ?>
                    <button class="btn btn-success" type="submit">Salvar oficina</button>
                <?php endif; ?>
                <?php if ($origem === 'cadastro-veiculo'): ?>
                    <a class="btn btn-secondary" href="../veiculos/cadastroveiculo.php">Voltar</a>
                <?php else: ?>
                    <a class="btn btn-secondary" href="cadastrar-manutencao-preventiva.php?placa=<?= rawurlencode($placa) ?>">Voltar</a>
                <?php endif; ?>
            </div>
        </form>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
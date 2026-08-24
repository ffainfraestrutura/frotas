<?php
require_once __DIR__ . '/../includes/autofrota_common.php';
$sessao = autofrotaInit();
$conn = $sessao['conn'] ?? null;
$databaseName = (string) ($sessao['databaseName'] ?? '');
$databaseCorp = trim((string) ($sessao['databaseCorp'] ?? ($GLOBALS['databaseCorp'] ?? 'bdcorp')));
$matricula = valorRequisicao(['matricula', 'matcond']);
$funcionario = buscarUmaLinha($conn, "SELECT matricula, nome, status, cargo, ccusto FROM `{$databaseCorp}`.`tbfuncionario` WHERE matricula = ? AND idtbempresa = 2 LIMIT 1", 's', [$matricula]);
if ($matricula === '' || $funcionario === []) {
    header('Location: listar_condutoresclt.php?msg=' . urlencode('Funcionário da empresa 2 não encontrado.'));
    exit;
}
$cnh = buscarUmaLinha($conn, "SELECT * FROM `{$databaseName}`.`tbcnh` WHERE matricula = ? LIMIT 1", 's', [$matricula]);
$cnhObrigatoria = true;
$ufs = $conn instanceof mysqli ? buscarUfsPortal($conn) : [];
$mensagem = valorRequisicao(['msg']);
renderCabecalhoAutofrota('Editar CNH de Colaborador');
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Editar CNH de Colaborador</h1><p class="text-muted mb-0"><?= esc($funcionario['nome'] ?? '') ?> — <?= esc($matricula) ?></p></div><a class="btn btn-secondary" href="listar_condutoresclt.php"><i class="fa fa-arrow-left me-1"></i>Voltar</a></div>
    <?php if ($mensagem !== ''): ?><div class="alert alert-info"><?= esc($mensagem) ?></div><?php endif; ?>
    <form method="post" action="control/processarcondutorclt.php" class="card">
        <input type="hidden" name="matricula" value="<?= esc($matricula) ?>">
        <div class="card-header fw-semibold">Dados vindos de tbfuncionario</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5"><label class="form-label">Nome</label><input class="form-control" value="<?= esc($funcionario['nome'] ?? '') ?>" readonly></div>
                <div class="col-md-2"><label class="form-label">Matrícula</label><input class="form-control" value="<?= esc($matricula) ?>" readonly></div>
                <div class="col-md-2"><label class="form-label">Status</label><input class="form-control" value="<?= esc($funcionario['status'] ?? '') ?>" readonly></div>
                <div class="col-md-3"><label class="form-label">Cargo</label><input class="form-control" value="<?= esc($funcionario['cargo'] ?? '') ?>" readonly></div>
            </div>
            <?php require __DIR__ . '/includes/form_cnh_opcional.php'; ?>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2"><a class="btn btn-secondary" href="listar_condutoresclt.php">Cancelar</a><button class="btn btn-success"><i class="fa fa-save me-1"></i>Salvar CNH</button></div>
    </form>
</div>
<?php renderRodapeAutofrota(); ?>
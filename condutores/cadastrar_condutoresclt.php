<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$sessao = autofrotaInit();
$conn = $sessao['conn'] ?? null;
$databaseCorp = trim((string) ($sessao['databaseCorp'] ?? ($GLOBALS['databaseCorp'] ?? 'bdcorp')));
$funcionarios = consultaPreparada(
    $conn,
    "SELECT matricula, nome, status, cargo, ccusto FROM `{$databaseCorp}`.`tbfuncionario` WHERE idtbempresa = 2 AND UPPER(TRIM(status)) = 'ATIVO' ORDER BY nome"
);
$mensagem = valorRequisicao(['msg']);
$cnh = [];
$ufs = $conn instanceof mysqli ? buscarUfsPortal($conn) : [];

renderCabecalhoAutofrota('Cadastrar CNH de Colaborador');
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h1 class="h3 mb-1">Cadastrar CNH de Colaborador</h1><p class="text-muted mb-0">Selecione um funcionário da empresa 2 e informe somente os dados da CNH.</p></div>
        <a class="btn btn-secondary" href="listar_condutoresclt.php"><i class="fa fa-arrow-left me-1"></i>Voltar</a>
    </div>
    <?php if ($mensagem !== ''): ?><div class="alert alert-info"><?= esc($mensagem) ?></div><?php endif; ?>
    <?php if ($funcionarios['erro'] !== ''): ?><div class="alert alert-danger"><?= esc($funcionarios['erro']) ?></div><?php endif; ?>
    <form method="post" action="../control/processarcondutorclt.php" class="card">
        <div class="card-header fw-semibold">Funcionário</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="matricula">Funcionário</label>
                    <select class="form-select" id="matricula" name="matricula" required>
                        <option value="">Selecione</option>
                        <?php foreach ($funcionarios['linhas'] as $funcionario): ?>
                            <option value="<?= esc($funcionario['matricula'] ?? '') ?>" data-status="<?= esc($funcionario['status'] ?? '') ?>" data-cargo="<?= esc($funcionario['cargo'] ?? '') ?>" data-ccusto="<?= esc($funcionario['ccusto'] ?? '') ?>"><?= esc(($funcionario['nome'] ?? '') . ' — ' . ($funcionario['matricula'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2"><label class="form-label">Status</label><input class="form-control" id="funcionario-status" readonly></div>
                <div class="col-md-2"><label class="form-label">Cargo</label><input class="form-control" id="funcionario-cargo" readonly></div>
                <div class="col-md-2"><label class="form-label">Centro de custo</label><input class="form-control" id="funcionario-ccusto" readonly></div>
            </div>
            <?php require __DIR__ . '/includes/form_cnh_opcional_clt.php'; ?>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2"><a class="btn btn-secondary" href="listar_condutoresclt.php">Cancelar</a><button class="btn btn-success"><i class="fa fa-save me-1"></i>Salvar CNH</button></div>
    </form>
</div>
<script>
document.getElementById('matricula').addEventListener('change', function () {
    const option = this.options[this.selectedIndex];
    document.getElementById('funcionario-status').value = option.dataset.status || '';
    document.getElementById('funcionario-cargo').value = option.dataset.cargo || '';
    document.getElementById('funcionario-ccusto').value = option.dataset.ccusto || '';
});
</script>
<?php renderRodapeAutofrota(); ?>
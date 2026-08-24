<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();

$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');
$databaseCorp = trim((string) ($autofrotaSessao['databaseCorp'] ?? ($GLOBALS['databaseCorp'] ?? '')));
if ($databaseCorp === '') {
    $databaseCorp = 'bdcorp';
}

$matricula = valorRequisicao(['matricula', 'matcond']);
$mensagem = valorRequisicao(['msg']);

if ($matricula === '') {
    header('Location: listar_condutorespj.php?msg=' . urlencode('Informe a matrícula do condutor PJ.'));
    exit;
}

$condutor = buscarUmaLinha(
    $conn,
    "SELECT * FROM `{$databaseName}`.`tbcondutor` WHERE matricula = ? AND matricula REGEXP '^16[0-9]{5}$' ORDER BY idtbcondutor DESC LIMIT 1",
    's',
    [$matricula]
);

if ($condutor === []) {
    header('Location: listar_condutorespj.php?msg=' . urlencode('Condutor PJ não encontrado.'));
    exit;
}

$ccustos = consultaPreparada($conn, "SELECT DISTINCT ccusto FROM `{$databaseName}`.`tbcondutor` WHERE ccusto IS NOT NULL AND ccusto <> '' ORDER BY ccusto");
$cargos = consultaPreparada($conn, "SELECT DISTINCT cargo FROM `{$databaseName}`.`tbcondutor` WHERE cargo IS NOT NULL AND cargo <> '' ORDER BY cargo");

function valorCondutorPj(array $condutor, string $campo): string
{
    return (string) ($condutor[$campo] ?? '');
}

renderCabecalhoAutofrota('Editar Cadastro de Funcionário');
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Editar Cadastro de Funcionário</h1>
            <small class="text-muted">Atualize os dados cadastrais do condutor <?= esc($matricula) ?>.</small>
        </div>
        <a class="btn btn-secondary" href="listar_condutorespj.php"><i class="fa fa-arrow-left me-1"></i>Voltar</a>
    </div>

    <style>
        .condutor-card-header{background-color:#2E2851;color:#fff;font-weight:600}
        .condutor-card-header i{color:#ffc107}
        .required-marker{color:#dc3545}
    </style>

    <?php if ($mensagem !== ''): ?><div class="alert alert-info"><?= esc($mensagem) ?></div><?php endif; ?>

    <form method="post" action="control/processarcondutor.php" class="card">
        <input type="hidden" name="acao" value="editar">
        <input type="hidden" name="matricula_original" value="<?= esc($matricula) ?>">
        <div class="card-header condutor-card-header"><i class="fa fa-id-card me-2"></i>Cadastro Funcionário</div>
        <div class="card-body">
            <p class="small mb-3"><span class="required-marker">*</span> Campos obrigatórios.</p>
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Matrícula <span class="required-marker">*</span></label>
                    <input class="form-control" name="matricula" value="<?= esc(valorCondutorPj($condutor, 'matricula')) ?>" inputmode="numeric" minlength="7" maxlength="7" pattern="16[0-9]{5}" title="A matrícula deve começar com 16 e conter exatamente 7 dígitos." required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nome <span class="required-marker">*</span></label>
                    <input class="form-control text-uppercase" name="nome" value="<?= esc(valorCondutorPj($condutor, 'nome')) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <?php foreach (['Ativo', 'Inativo', 'Afastado', 'Ferias', 'Demitido'] as $status): ?>
                            <option value="<?= esc($status) ?>" <?= strcasecmp(valorCondutorPj($condutor, 'status'), $status) === 0 ? 'selected' : '' ?>><?= esc($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data admissão</label>
                    <input class="form-control" type="date" name="dtadmissao" value="<?= esc(formatarDataPortal(valorCondutorPj($condutor, 'dtadmissao'), 'Y-m-d')) ?>">
                </div>
                <div class="col-md-3"><label class="form-label">CPF</label><input class="form-control" name="cpf" value="<?= esc(valorCondutorPj($condutor, 'cpf')) ?>"></div>
                <div class="col-md-3"><label class="form-label">RG</label><input class="form-control" name="rg" value="<?= esc(valorCondutorPj($condutor, 'rg')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Data nascimento</label><input class="form-control" type="date" name="dtnasc" value="<?= esc(formatarDataPortal(valorCondutorPj($condutor, 'dtnasc'), 'Y-m-d')) ?>"></div>
                <div class="col-md-3"><label class="form-label">UF trabalho</label><input class="form-control text-uppercase" name="uf_trabalho" maxlength="2" value="<?= esc(valorCondutorPj($condutor, 'uf_trabalho') ?: valorCondutorPj($condutor, 'estado')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Centro de custo</label><input class="form-control" name="ccusto" list="listaCcusto" value="<?= esc(valorCondutorPj($condutor, 'ccusto')) ?>"><datalist id="listaCcusto"><?php foreach ($ccustos['linhas'] as $linha): ?><option value="<?= esc($linha['ccusto'] ?? '') ?>"><?php endforeach; ?></datalist></div>
                <div class="col-md-4"><label class="form-label">Cargo</label><input class="form-control text-uppercase" name="cargo" list="listaCargo" value="<?= esc(valorCondutorPj($condutor, 'cargo')) ?>"><datalist id="listaCargo"><?php foreach ($cargos['linhas'] as $linha): ?><option value="<?= esc($linha['cargo'] ?? '') ?>"><?php endforeach; ?></datalist></div>
                <div class="col-md-4"><label class="form-label">Projeto</label><input class="form-control text-uppercase" name="projeto" value="<?= esc(valorCondutorPj($condutor, 'projeto')) ?>"></div>
                <div class="col-md-5"><label class="form-label">Endereço</label><input class="form-control text-uppercase" name="endereco" value="<?= esc(valorCondutorPj($condutor, 'endereco')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Bairro</label><input class="form-control text-uppercase" name="bairro" value="<?= esc(valorCondutorPj($condutor, 'bairro')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Cidade</label><input class="form-control text-uppercase" name="cidade" value="<?= esc(valorCondutorPj($condutor, 'cidade')) ?>"></div>
                <div class="col-md-1"><label class="form-label">CEP</label><input class="form-control" name="cep" value="<?= esc(valorCondutorPj($condutor, 'cep')) ?>"></div>
                <div class="col-md-4"><label class="form-label">E-mail</label><input class="form-control" type="email" name="email" maxlength="99" value="<?= esc(valorCondutorPj($condutor, 'email')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Telefone</label><input class="form-control" name="tel_corp" inputmode="numeric" maxlength="11" pattern="[0-9]*" value="<?= esc(valorCondutorPj($condutor, 'tel_corp')) ?>"></div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a class="btn btn-secondary" href="listar_condutorespj.php"><i class="fa fa-xmark me-1"></i>Cancelar</a>
            <button class="btn btn-success"><i class="fa fa-save me-1"></i>Salvar alterações</button>
        </div>
    </form>
</div>
<?php renderRodapeAutofrota(); ?>
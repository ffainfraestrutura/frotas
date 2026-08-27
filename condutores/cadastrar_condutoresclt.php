<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$sessao = autofrotaInit();
$conn = $sessao['conn'] ?? null;
$databaseCorp = trim((string) ($sessao['databaseCorp'] ?? ($GLOBALS['databaseCorp'] ?? 'bdcorp')));
$funcionarios = consultaPreparada(
    $conn,
    "SELECT matricula, nome, status, cargo, ccusto, dtadmissao, cpf, rg, dtnasc, uf_trabalho, estado, projeto, endereco, bairro, cidade, cep, email, tel_corp FROM `{$databaseCorp}`.`tbfuncionario` WHERE idtbempresa = 2 AND UPPER(TRIM(status)) = 'ATIVO' ORDER BY nome"
);
$mensagem = valorRequisicao(['msg']);
$cnh = [];
$ufs = $conn instanceof mysqli ? buscarUfsPortal($conn) : [];

renderCabecalhoAutofrota('Cadastrar CNH de Colaborador');
?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
    .select2-container .select2-selection--single { height: 38px; display: flex; align-items: center; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
</style>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h1 class="h3 mb-1">Cadastrar CNH de Colaborador</h1><p class="text-muted mb-0">Selecione um funcionário, confira os dados carregados e informe somente a CNH.</p></div>
        <button class="btn btn-secondary" type="button" onclick="window.history.back()"><i class="fa fa-arrow-left me-1"></i>Voltar</button>
    </div>
    <?php if ($mensagem !== ''): ?><div class="alert alert-info"><?= esc($mensagem) ?></div><?php endif; ?>
    <?php if ($funcionarios['erro'] !== ''): ?><div class="alert alert-danger"><?= esc($funcionarios['erro']) ?></div><?php endif; ?>
    <form id="form-condutor-clt" method="post" action="../control/processarcondutorclt.php" enctype="multipart/form-data">
        <input type="hidden" id="matricula" name="matricula">
        <div class="card">
            <div class="card-header fw-semibold"><i class="fas fa-user me-2"></i>Informações Pessoais</div>
            <div class="card-body"><div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label" for="funcionario">Nome Completo</label>
                    <select class="form-select" id="funcionario" required>
                        <option value="">Selecione um funcionário</option>
                        <?php foreach ($funcionarios['linhas'] as $funcionario): ?>
                            <option value="<?= esc($funcionario['matricula'] ?? '') ?>" data-funcionario="<?= esc(json_encode($funcionario, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"><?= esc(($funcionario['nome'] ?? '') . ' (' . ($funcionario['matricula'] ?? '') . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Clique para selecionar ou pesquise pelo nome/matrícula.</small>
                </div>
                <div class="col-md-3"><label class="form-label">Data de Nascimento</label><input class="form-control campo-funcionario" id="dtnasc" type="date" readonly></div>
                <div class="col-md-2"><label class="form-label">Telefone</label><input class="form-control campo-funcionario" id="tel_corp" readonly></div>
            </div></div>
        </div>

        <div class="card mt-3">
            <div class="card-header fw-semibold"><i class="fas fa-file-alt me-2"></i>Documentos</div>
            <div class="card-body"><div class="row g-3">
                <div class="col-md-3"><label class="form-label">CPF</label><input class="form-control campo-funcionario" id="cpf" readonly></div>
                <div class="col-md-3"><label class="form-label">RG</label><input class="form-control campo-funcionario" id="rg" readonly></div>
            </div></div>
        </div>

        <div class="card mt-3">
            <div class="card-header fw-semibold"><i class="fas fa-address-book me-2"></i>Informações de Endereço</div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-2"><label class="form-label">CEP</label><input class="form-control campo-funcionario" id="cep" readonly></div>
                    <div class="col-md-5"><label class="form-label">Endereço</label><input class="form-control campo-funcionario" id="endereco" readonly></div>
                    <div class="col-md-3"><label class="form-label">Bairro</label><input class="form-control campo-funcionario" id="bairro" readonly></div>
                    <div class="col-md-2"><label class="form-label">Estado (UF)</label><input class="form-control campo-funcionario" id="estado" readonly></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">Cidade</label><input class="form-control campo-funcionario" id="cidade" readonly></div>
                    <div class="col-md-4"><label class="form-label">E-mail</label><input class="form-control campo-funcionario" id="email" readonly></div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header fw-semibold"><i class="fas fa-briefcase me-2"></i>Dados Contratuais</div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-2"><label class="form-label">Matrícula</label><input class="form-control campo-funcionario" id="matricula-exibicao" data-campo="matricula" readonly></div>
                    <div class="col-md-2"><label class="form-label">Situação</label><input class="form-control campo-funcionario" id="status" readonly></div>
                    <div class="col-md-2"><label class="form-label">Data Admissão</label><input class="form-control campo-funcionario" id="dtadmissao" type="date" readonly></div>
                    <div class="col-md-3"><label class="form-label">Departamento/Centro de Custo</label><input class="form-control campo-funcionario" id="ccusto" readonly></div>
                    <div class="col-md-3"><label class="form-label">Cargo</label><input class="form-control campo-funcionario" id="cargo" readonly></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Projeto</label><input class="form-control campo-funcionario" id="projeto" readonly></div>
                    <div class="col-md-2"><label class="form-label">UF de Trabalho</label><input class="form-control campo-funcionario" id="uf_trabalho" readonly></div>
                </div>
            </div>
        </div>

        <?php require __DIR__ . '/includes/form_cnh_opcional_clt.php'; ?>
        <div class="d-flex justify-content-end gap-2 my-3">
            <button class="btn btn-secondary" type="button" onclick="window.history.back()">Cancelar</button>
            <button class="btn btn-success" type="submit"><i class="fa fa-save me-1"></i>Salvar CNH</button>
        </div>
    </form>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const busca = document.getElementById('funcionario');
const matricula = document.getElementById('matricula');

function preencherFuncionario(opcaoSelecionada) {
    const opcao = opcaoSelecionada instanceof HTMLOptionElement
        ? opcaoSelecionada
        : busca.options[busca.selectedIndex];
    const dados = opcao && opcao.dataset.funcionario ? JSON.parse(opcao.dataset.funcionario) : {};
    matricula.value = dados.matricula || '';
    document.querySelectorAll('.campo-funcionario').forEach(function (campo) {
        const valor = dados[campo.dataset.campo || campo.id] || '';
        campo.value = campo.type === 'date' && valor ? valor.substring(0, 10) : valor;
    });
}

busca.addEventListener('change', function () { preencherFuncionario(); });
document.getElementById('form-condutor-clt').addEventListener('submit', function () { preencherFuncionario(); });

if (window.jQuery && jQuery.fn.select2) {
    jQuery(busca).select2({
        width: '100%',
        placeholder: 'Selecione um funcionário',
        minimumResultsForSearch: 0,
        language: {
            inputTooShort: function () { return 'Digite para buscar por nome ou matrícula'; },
            noResults: function () { return 'Nenhum funcionário encontrado'; },
            searching: function () { return 'Buscando...'; }
        }
    });
    jQuery(busca).on('select2:select', function (evento) {
        const opcao = evento.params && evento.params.data ? evento.params.data.element : null;
        preencherFuncionario(opcao);
    });
    jQuery(busca).on('select2:clear', function () {
        preencherFuncionario(null);
    });
    jQuery(busca).on('select2:open', function () {
        const pesquisa = document.querySelector('.select2-container--open .select2-search__field');
        if (pesquisa) {
            pesquisa.placeholder = 'Digite o nome ou a matrícula';
            pesquisa.focus();
        }
    });
} else {
    console.error('Não foi possível inicializar a busca de funcionários.');
}
</script>
<?php renderRodapeAutofrota(); ?>
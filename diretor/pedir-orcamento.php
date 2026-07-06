<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? ($GLOBALS['conn'] ?? null);
$databaseCorp = (string) ($autofrotaSessao['databaseCorp'] ?? ($GLOBALS['databaseCorp'] ?? 'bdcorp'));
$matriculaLogada = (string) ($autofrotaSessao['matricula'] ?? $_SESSION['matricula'] ?? '');
$nomeLogado = (string) ($autofrotaSessao['usuario'] ?? $_SESSION['nome'] ?? '');
$perfilLogado = (string) ($autofrotaSessao['perfil'] ?? $_SESSION['perfil'] ?? '');

if (!$conn instanceof mysqli) {
    exit('Conexão indisponível.');
}

if ($perfilLogado !== '10') {
    http_response_code(403);
    exit('Acesso permitido apenas para perfil diretor.');
}

function moedaOrcamentoDiretor($valor): string
{
    return number_format((float) ($valor ?? 0), 2, ',', '.');
}

$mensagem = (string) ($_SESSION['pedir_orcamento_diretor_mensagem'] ?? '');
$tipoMensagem = (string) ($_SESSION['pedir_orcamento_diretor_tipo'] ?? 'info');
unset($_SESSION['pedir_orcamento_diretor_mensagem'], $_SESSION['pedir_orcamento_diretor_tipo']);

$_SESSION['pedir_orcamento_diretor_token'] = bin2hex(random_bytes(32));
$token = $_SESSION['pedir_orcamento_diretor_token'];

$saldoDiretor = null;
$orcamentoRecebido = null;
$erroTela = '';
$linhaDiretor = buscarUmaLinha(
    $conn,
    "SELECT valor, orcrecebido FROM `{$databaseCorp}`.`tbdiretor` WHERE matricula = ? LIMIT 1",
    's',
    [$matriculaLogada]
);
if ($linhaDiretor === []) {
    $erroTela = 'Cadastro do diretor não encontrado.';
} else {
    $saldoDiretor = is_numeric($linhaDiretor['valor'] ?? null) ? (float) $linhaDiretor['valor'] : 0.0;
    $orcamentoRecebido = is_numeric($linhaDiretor['orcrecebido'] ?? null) ? (float) $linhaDiretor['orcrecebido'] : 0.0;
}

renderCabecalhoAutofrota('Solicitar Orçamento Complementar');
?>
<div class="container-fluid px-4">
    <section class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <h1 class="h3 mb-1">Solicitar Orçamento Complementar</h1>
                <p class="text-muted mb-0">Diretor: <strong><?= esc($nomeLogado) ?></strong> (<?= esc($matriculaLogada) ?>)</p>
            </div>
            <div class="d-flex flex-column flex-sm-row gap-2">
                <!-- <div class="border rounded-3 px-3 py-2 bg-light">
                    <div class="text-muted small">Valor orçamento</div>
                    <div class="fw-bold">R$ <?= esc(moedaOrcamentoDiretor($orcamentoRecebido)) ?></div>
                </div> -->
                <div class="border rounded-3 px-3 py-2 bg-light">
                    <div class="text-muted small">Saldo atual</div>
                    <div class="fw-bold text-primary">R$ <?= esc(moedaOrcamentoDiretor($saldoDiretor)) ?></div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($mensagem !== ''): ?>
        <div class="alert alert-<?= esc($tipoMensagem) ?> alert-dismissible fade show" role="alert">
            <?= esc($mensagem) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>
    <?php if ($erroTela !== ''): ?><div class="alert alert-danger"><?= esc($erroTela) ?></div><?php endif; ?>

    <section class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-file-invoice-dollar me-2"></i>Solicitação:</div>
        <div class="card-body">
            <form action="control/pedir-orcamento.php" method="post" class="row g-3">
                <input type="hidden" name="token" value="<?= esc($token) ?>">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="valor">Valor solicitado</label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input class="form-control" type="text" name="valor" id="valor" required placeholder="0,00" inputmode="decimal">
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="justificativa">Justificativa</label>
                    <textarea rows="8" class="form-control" name="justificativa" id="justificativa" placeholder="Escreva a justificativa" required></textarea>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button class="btn btn-success" type="submit" <?= $erroTela !== '' ? 'disabled' : '' ?>><i class="fas fa-check me-1"></i>Confirmar</button>
                </div>
            </form>
        </div>
    </section>
</div>
<script>
document.getElementById('valor').addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '');
    if (v === '') { this.value = ''; return; }
    v = (parseInt(v, 10) / 100).toFixed(2).replace('.', ',');
    this.value = v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
});
</script>
<?php renderRodapeAutofrota(); ?>
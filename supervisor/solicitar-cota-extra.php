<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? ($GLOBALS['conn'] ?? null);
$databaseCorp = (string) ($autofrotaSessao['databaseCorp'] ?? ($GLOBALS['databaseCorp'] ?? 'bdcorp'));
$matriculaLogada = (string) ($autofrotaSessao['matricula'] ?? $_SESSION['matricula'] ?? '');
$nomeLogado = (string) ($autofrotaSessao['usuario'] ?? $_SESSION['nome'] ?? '');
$nomeExibicao = autofrotaNomeExibicaoPorMatricula($conn, $databaseCorp, $matriculaLogada, $nomeLogado);

if (!$conn instanceof mysqli) {
    exit('Conexão indisponível.');
}

$mensagem = (string) ($_SESSION['solicitar_cota_supervisor_mensagem'] ?? '');
$tipoMensagem = (string) ($_SESSION['solicitar_cota_supervisor_tipo'] ?? 'info');
unset($_SESSION['solicitar_cota_supervisor_mensagem'], $_SESSION['solicitar_cota_supervisor_tipo']);

$_SESSION['solicitar_cota_supervisor_token'] = bin2hex(random_bytes(32));
$token = $_SESSION['solicitar_cota_supervisor_token'];

function moedaSupervisor($valor): string
{
    return number_format((float) ($valor ?? 0), 2, ',', '.');
}

renderCabecalhoAutofrota('Solicitação de Cota Extra');
?>
<div class="container-fluid px-4">
    <section class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-3">
            <div>
                <h1 class="h3 mb-1">Solicitação de cota extra</h1>
                <p class="text-muted mb-0">Supervisor: <strong><?= esc($nomeExibicao . '(' . $matriculaLogada . ')') ?></strong></p>
            </div>
        </div>
    </section>

    <?php if ($mensagem !== ''): ?>
        <div class="alert alert-<?= esc($tipoMensagem) ?> alert-dismissible fade show" role="alert">
            <?= esc($mensagem) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <section class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-plus-circle me-2"></i>Novo pedido</div>
        <div class="card-body">
            <form action="control/solicitar-cota-extra.php" method="post" class="row g-3">
                <input type="hidden" name="token" value="<?= esc($token) ?>">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="valor">Solicitação</label>
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
                    <button class="btn btn-success" type="submit"><i class="fas fa-check me-1"></i>Confirmar</button>
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
<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/toaqui.php';

$contexto = toaquiContextoSupervisor();
$conn = $contexto['conn'];
$databaseName = $contexto['databaseName'];
$databaseCorp = $contexto['databaseCorp'];
$idSupervisor = $contexto['idtbsupervisor'];

$mensagem = (string) ($_SESSION['toaqui_mensagem'] ?? '');
$tipoMensagem = (string) ($_SESSION['toaqui_tipo_mensagem'] ?? 'info');
unset($_SESSION['toaqui_mensagem'], $_SESSION['toaqui_tipo_mensagem']);

$_SESSION['toaqui_token'] = bin2hex(random_bytes(32));
$token = $_SESSION['toaqui_token'];

$consulta = consultaPreparada(
    $conn,
    "SELECT u.matricula,
            COALESCE(NULLIF(TRIM(f.nome), ''), NULLIF(TRIM(u.nome), ''), u.matricula) AS nome,
            COUNT(t.idtbtoaqui) AS total,
            MIN(COALESCE(t.data, t.hora)) AS mais_antigo
       FROM `{$databaseCorp}`.`tbusuario` u
       INNER JOIN `{$databaseName}`.`tbtoaqui` t ON t.matricula = u.matricula AND t.aceite = 0
       LEFT JOIN `{$databaseCorp}`.`tbfuncionario` f ON f.matricula = u.matricula
      WHERE u.idtbsupervisor = ?
      GROUP BY u.matricula, f.nome, u.nome
      ORDER BY mais_antigo, nome",
    'i',
    [$idSupervisor]
);
$tecnicos = $consulta['linhas'];
$erroConsulta = $consulta['erro'];
$totalPendencias = array_sum(array_map(static fn(array $linha): int => (int) $linha['total'], $tecnicos));

renderCabecalhoAutofrota('Aprovação de rota — To Aqui');
?>
<style>
    .toaqui-summary { border-left: 4px solid #0d6efd; }
    .toaqui-avatar { width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; }
    .toaqui-detail-address { min-width: 240px; max-width: 430px; white-space: normal; }
    #toaquiDetalhesTable tbody tr:has(.toaqui-choice:checked) { --bs-table-bg: #eaf7ee; }
</style>
<div class="container-fluid px-4">
    <section class="card shadow-sm border-0 toaqui-summary mb-4">
        <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <h1 class="h3 mb-1">Aprovação de rota — To Aqui</h1>
                <p class="text-muted mb-0">Analise os apontamentos pendentes dos técnicos da sua equipe.</p>
            </div>
            <span class="badge rounded-pill text-bg-primary fs-6"><?= $totalPendencias ?> pendência<?= $totalPendencias === 1 ? '' : 's' ?></span>
        </div>
    </section>

    <?php if ($mensagem !== ''): ?>
        <div class="alert alert-<?= esc($tipoMensagem) ?> alert-dismissible fade show" role="alert">
            <?= esc($mensagem) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>
    <?php if ($erroConsulta !== ''): ?>
        <div class="alert alert-danger">Não foi possível carregar as pendências: <?= esc($erroConsulta) ?></div>
    <?php endif; ?>

    <section class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-route me-2"></i>Técnicos com apontamentos pendentes</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" data-datatable="1">
                    <thead><tr><th>Matrícula</th><th>Nome</th><th class="text-center">Pendências</th><th>Registro mais antigo</th><th class="text-end">Ação</th></tr></thead>
                    <tbody>
                    <?php foreach ($tecnicos as $tecnico): ?>
                        <tr>
                            <td><?= esc($tecnico['matricula']) ?></td>
                            <td><span class="toaqui-avatar rounded-circle bg-primary-subtle text-primary me-2"><i class="fas fa-user"></i></span><?= esc($tecnico['nome']) ?></td>
                            <td class="text-center"><span class="badge text-bg-warning"><?= (int) $tecnico['total'] ?></span></td>
                            <td><?= esc(formatarDataPortal($tecnico['mais_antigo'], 'd/m/Y H:i')) ?></td>
                            <td class="text-end"><button class="btn btn-outline-primary btn-sm toaqui-open" type="button" data-matricula="<?= esc($tecnico['matricula']) ?>" data-nome="<?= esc($tecnico['nome']) ?>"><i class="fas fa-eye me-1"></i>Ver detalhes</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="toaquiModal" tabindex="-1" aria-labelledby="toaquiModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="toaquiModalLabel">Detalhes do To Aqui</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <form action="control/toaqui-decisao.php" method="post" id="toaquiForm">
                <div class="modal-body">
                    <input type="hidden" name="token" value="<?= esc($token) ?>">
                    <input type="hidden" name="decisao" id="toaquiDecisao" value="">
                    <div id="toaquiLoading" class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando</span></div></div>
                    <div id="toaquiError" class="alert alert-danger d-none"></div>
                    <div class="table-responsive d-none" id="toaquiTableWrapper">
                        <table class="table table-striped align-middle" id="toaquiDetalhesTable">
                            <thead><tr><th>Selecionar</th><th>Motivo</th><th>Endereço</th><th>Observação</th><th>Data e hora</th></tr></thead><tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <small class="text-muted me-auto">Selecione um apontamento por operação.</small>
                    <button type="submit" value="2" class="btn btn-outline-danger toaqui-submit" disabled><i class="fas fa-xmark me-1"></i>Rejeitar</button>
                    <button type="submit" value="1" class="btn btn-success toaqui-submit" disabled><i class="fas fa-check me-1"></i>Aprovar e incluir na rota</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('toaquiModal');
    const modal = new bootstrap.Modal(modalElement);
    const body = document.querySelector('#toaquiDetalhesTable tbody');
    const loading = document.getElementById('toaquiLoading');
    const error = document.getElementById('toaquiError');
    const wrapper = document.getElementById('toaquiTableWrapper');
    const submits = document.querySelectorAll('.toaqui-submit');
    const decisao = document.getElementById('toaquiDecisao');
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));

    document.querySelectorAll('.toaqui-open').forEach(button => button.addEventListener('click', async function () {
        document.getElementById('toaquiModalLabel').textContent = 'Detalhes do To Aqui — ' + this.dataset.nome;
        body.innerHTML = '';
        decisao.value = '';
        loading.classList.remove('d-none'); error.classList.add('d-none'); wrapper.classList.add('d-none');
        submits.forEach(item => item.disabled = true);
        modal.show();
        try {
            const response = await fetch('control/toaqui-detalhes.php?matricula=' + encodeURIComponent(this.dataset.matricula), {headers: {'Accept': 'application/json'}});
            const payload = await response.json();
            if (!response.ok || !payload.sucesso) throw new Error(payload.mensagem || 'Falha ao carregar os detalhes.');
            payload.registros.forEach(item => {
                const endereco = item.endereco
                    ? escapeHtml(item.endereco)
                    : (item.latitude && item.longitude
                        ? `<a class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener" href="https://www.google.com/maps?q=${encodeURIComponent(item.latitude + ',' + item.longitude)}"><i class="fas fa-map-location-dot me-1"></i>Abrir coordenada</a><div class="small text-danger mt-1">Endereço obrigatório para aprovar</div>`
                        : '<span class="text-danger">Endereço e coordenadas ausentes</span>');
                body.insertAdjacentHTML('beforeend', `<tr><td class="text-center"><input class="form-check-input toaqui-choice" type="radio" name="idtbtoaqui" value="${Number(item.idtbtoaqui)}" required></td><td>${escapeHtml(item.motivo)}</td><td class="toaqui-detail-address">${endereco}</td><td>${escapeHtml(item.obs) || '—'}</td><td class="text-nowrap">${escapeHtml(item.data_formatada)}</td></tr>`);
            });
            wrapper.classList.remove('d-none');
            if (!payload.registros.length) throw new Error('Não há mais pendências para este técnico.');
        } catch (exception) {
            error.textContent = exception.message; error.classList.remove('d-none');
        } finally { loading.classList.add('d-none'); }
    }));

    body.addEventListener('change', function () { submits.forEach(item => item.disabled = false); });
    submits.forEach(item => item.addEventListener('click', function () { decisao.value = this.value; }));
    document.getElementById('toaquiForm').addEventListener('submit', function (event) {
        // Botões desabilitados não são enviados pelo navegador. Preserve a decisão
        // antes de bloquear novos cliques durante o processamento da requisição.
        decisao.value = event.submitter?.value ?? decisao.value;
        submits.forEach(item => item.disabled = true);
    });
});
</script>
<?php renderRodapeAutofrota(); ?>
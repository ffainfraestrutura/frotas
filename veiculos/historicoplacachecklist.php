<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../control/conecta.php';
require_once __DIR__ . '/../includes/portal_helpers.php';
exigirLogin();

$perfil = (string) ($_SESSION['perfil'] ?? '0');
if ($perfil === '0' || $perfil === '') {
    http_response_code(403);
    exit('Sem permissão.');
}

$placa = strtoupper(valorRequisicao(['placa']));
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $placa !== '') {
    header('Location: ' . basename(__FILE__) . '?' . http_build_query(['placa' => $placa]));
    exit;
}
$consulta = $placa !== '' ? consultaPreparada($conn, "SELECT v.nome, v.matricula, v.placa, COALESCE(tv.tipo, v.tipo) AS tipo_vistoria, v.vistoriador, v.datavistoria FROM `{$databaseName}`.`tbvistoria` v LEFT JOIN `{$databaseName}`.`tbatipovist` tv ON tv.idtbatipovist = v.tipo WHERE v.placa = ? ORDER BY v.datavistoria DESC", 's', [$placa]) : ['erro' => '', 'linhas' => []];
renderCabecalhoAutofrota('Histórico de Checklist da Placa');
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Histórico de Vistorias por Placa</h1><p class="text-muted mb-0">Placa: <?= esc($placa) ?></p></div><a class="btn btn-secondary" href="/autofrota/veiculos/dadosplaca.php<?= $placa !== '' ? '?' . http_build_query(['placa' => $placa]) : '' ?>">Voltar</a></div>
    <?php if ($placa === ''): ?><div class="alert alert-warning">Informe a placa.</div><?php endif; ?>
    <?php if ($consulta['erro'] !== ''): ?><div class="alert alert-danger"><?= esc($consulta['erro']) ?></div><?php endif; ?>
    <div class="card"><div class="card-body table-responsive"><table class="table table-striped" data-datatable="1"><thead><tr><th>Condutor</th><th>Matrícula</th><th>Placa</th><th>Tipo da vistoria</th><th>Realizada por</th><th>Data de vistoria</th></tr></thead><tbody>
        <?php foreach ($consulta['linhas'] as $linha): ?><tr><td><?= esc($linha['nome'] ?? '') ?></td><td><?= esc($linha['matricula'] ?? '') ?></td><td><?= esc($linha['placa'] ?? '') ?></td><td><?= esc($linha['tipo_vistoria'] ?? '') ?></td><td><?= esc($linha['vistoriador'] ?? '') ?></td><td><?= esc(formatarDataPortal($linha['datavistoria'] ?? '')) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
</div>
<?php renderRodapeAutofrota(); ?>
<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');

$perfil = (string) ($_SESSION['perfil'] ?? '0');
if ($perfil === '0' || $perfil === '') {
    http_response_code(403);
    exit('Sem permissão.');
}

$placa = strtoupper(valorRequisicao(['placa']));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $placa !== '') {
    header('Location: historico-placas-condutor.php?' . http_build_query(['placa' => $placa]));
    exit;
}

$consulta = $placa !== '' && $conn instanceof mysqli
    ? consultaPreparada(
        $conn,
        "SELECT placaassoc, nome, matricula, ativo, dataassoc, datadissoc, cartaoassoc, statuscond
         FROM `{$databaseName}`.`tbcondutor`
         WHERE placaassoc = ?
         ORDER BY ativo DESC, dataassoc DESC, idtbcondutor DESC",
        's',
        [$placa]
    )
    : ['erro' => '', 'linhas' => []];

renderCabecalhoAutofrota('Histórico de Condutores da Placa');
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Histórico de Condutores da Placa</h1><p class="text-muted mb-0">Placa: <?= esc($placa) ?></p></div><a class="btn btn-secondary" href="dadosplaca.php?placa=<?= urlencode($placa) ?>">Voltar</a></div>
    <?php if ($placa === ''): ?><div class="alert alert-warning">Informe a placa.</div><?php endif; ?>
    <?php if ($consulta['erro'] !== ''): ?><div class="alert alert-danger"><?= esc($consulta['erro']) ?></div><?php endif; ?>
    <div class="card"><div class="card-body table-responsive"><table class="table table-striped" data-datatable="1"><thead><tr><th>Condutor</th><th>Matrícula</th><th>Placa</th><th>Status</th><th>Cartão associado</th><th>Status condutor</th><th>Data associação</th><th>Data dissociação</th></tr></thead><tbody>
        <?php foreach ($consulta['linhas'] as $linha): ?><tr><td><?= esc($linha['nome'] ?? '') ?></td><td><?= esc($linha['matricula'] ?? '') ?></td><td><?= esc($linha['placaassoc'] ?? '') ?></td><td><?= esc(badgeStatusAtivo($linha['ativo'] ?? '0')) ?></td><td><?= esc($linha['cartaoassoc'] ?? '') ?></td><td><?= esc($linha['statuscond'] ?? '') ?></td><td><?= esc(formatarDataPortal($linha['dataassoc'] ?? '')) ?></td><td><?= esc(formatarDataPortal($linha['datadissoc'] ?? '')) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
</div>
<?php renderRodapeAutofrota(); ?>

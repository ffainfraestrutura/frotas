<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../control/conecta.php';
require_once __DIR__ . '/../includes/portal_helpers.php';
exigirLogin();

$matcond = valorRequisicao(['matcond', 'matcondutor', 'matricula']);
$placa = strtoupper(valorRequisicao(['placa']));

if ($matcond === '' && $placa !== '' && isset($conn) && $conn instanceof mysqli) {
    $veiculoCondutor = buscarUmaLinha($conn, "SELECT * FROM `{$databaseName}`.`tbveiculo` WHERE placa = ? LIMIT 1", 's', [$placa]);
    $matcond = trim((string) ($veiculoCondutor['matcond'] ?? $veiculoCondutor['matcondutor'] ?? ''));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $parametros = [];
    if ($matcond !== '') {
        $parametros['matcond'] = $matcond;
    } elseif ($placa !== '') {
        $parametros['placa'] = $placa;
    }

    header('Location: historico-placas-condutor.php' . ($parametros !== [] ? '?' . http_build_query($parametros) : ''));
    exit;
}

$condutor = $matcond !== '' ? buscarUmaLinha($conn, "SELECT nome FROM `{$databaseName}`.`tbfuncionario` WHERE matricula = ? LIMIT 1", 's', [$matcond]) : [];
$consulta = $matcond !== '' ? consultaPreparada($conn, "SELECT placaassoc, ativo, dataassoc, datadissoc, cartaoassoc, statuscond FROM `{$databaseName}`.`tbcondutor` WHERE matricula = ? ORDER BY dataassoc DESC, idtbcondutor DESC", 's', [$matcond]) : ['erro' => '', 'linhas' => []];
renderCabecalhoAutofrota('Histórico do Condutor');
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Histórico do Condutor</h1><p class="text-muted mb-0"><?= esc($condutor['nome'] ?? '') ?> - Matrícula <?= esc($matcond) ?></p></div><a class="btn btn-secondary" href="dados-condutor.php?matcond=<?= urlencode($matcond) ?>">Voltar</a></div>
    <?php if ($matcond === ''): ?><div class="alert alert-warning">Informe a matrícula do condutor.</div><?php endif; ?>
    <?php if ($consulta['erro'] !== ''): ?><div class="alert alert-danger"><?= esc($consulta['erro']) ?></div><?php endif; ?>
    <div class="card"><div class="card-body table-responsive"><table class="table table-striped" data-datatable="1"><thead><tr><th>Placa</th><th>Status</th><th>Cartão associado</th><th>Status condutor</th><th>Data associação</th><th>Data dissociação</th></tr></thead><tbody>
        <?php foreach ($consulta['linhas'] as $linha): ?><tr><td><?= esc($linha['placaassoc'] ?? '') ?></td><td><?= esc(badgeStatusAtivo($linha['ativo'] ?? '0')) ?></td><td><?= esc($linha['cartaoassoc'] ?? '') ?></td><td><?= esc($linha['statuscond'] ?? '') ?></td><td><?= esc(formatarDataPortal($linha['dataassoc'] ?? '')) ?></td><td><?= esc(formatarDataPortal($linha['datadissoc'] ?? '')) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
</div>
<?php renderRodapeAutofrota(); ?>
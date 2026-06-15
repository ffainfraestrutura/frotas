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
$veiculo = $placa !== '' ? buscarUmaLinha($conn, "SELECT v.placa, v.unidade, v.basegestao, v.matcond, f.nome AS nomecond, COALESCE(s.status, v.statusvel) AS situacao, COALESCE(a.aplicacao, v.aplicacao) AS aplicacao, COALESCE(m.modelo, v.modelo) AS modelo FROM `{$databaseName}`.`tbveiculo` v LEFT JOIN `{$databaseName}`.`tbfuncionario` f ON f.matricula = v.matcond LEFT JOIN `{$databaseName}`.`tbveiculostatus` s ON s.idtbstatusveic = v.statusvel LEFT JOIN `{$databaseName}`.`tbveiculoaplicacao` a ON a.idtbaplicacaoveic = v.aplicacao LEFT JOIN `{$databaseName}`.`tbveiculomodelo` m ON m.idtbmodeloveic = v.modelo WHERE v.placa = ? LIMIT 1", 's', [$placa]) : [];
$consulta = $placa !== '' ? consultaPreparada($conn, "SELECT c.matricula, f.nome, c.ativo, c.dataassoc, c.datadissoc, c.cartaoassoc, c.statuscond FROM `{$databaseName}`.`tbcondutor` c LEFT JOIN `{$databaseName}`.`tbfuncionario` f ON f.matricula = c.matricula WHERE c.placaassoc = ? ORDER BY c.dataassoc DESC, c.idtbcondutor DESC", 's', [$placa]) : ['erro' => '', 'linhas' => []];
renderCabecalhoAutofrota('Histórico da Placa');
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Histórico da Placa</h1><p class="text-muted mb-0">Placa: <?= esc($placa) ?></p></div><button class="btn btn-secondary" type="button" onclick="if (window.history.length > 1) { history.back(); } else { window.location.href='inventario-veiculo.php'; }">Voltar</button></div>
    <?php if ($placa === ''): ?><div class="alert alert-warning">Informe a placa.</div><?php endif; ?>
    <?php if ($veiculo !== []): ?><div class="card card-info mb-3"><div class="card-body row g-3"><?php foreach (['Unidade'=>'unidade','Base gestão'=>'basegestao','Condutor atual'=>'nomecond','Matrícula atual'=>'matcond','Situação'=>'situacao','Aplicação'=>'aplicacao','Modelo'=>'modelo'] as $label=>$campo): ?><div class="col-md-3"><label><?= esc($label) ?></label><input class="form-control" value="<?= esc($veiculo[$campo] ?? '') ?>" readonly></div><?php endforeach; ?></div></div><?php endif; ?>
    <?php if ($consulta['erro'] !== ''): ?><div class="alert alert-danger"><?= esc($consulta['erro']) ?></div><?php endif; ?>
    <div class="card"><div class="card-body table-responsive"><table class="table table-striped" data-datatable="1"><thead><tr><th>Matrícula</th><th>Condutor</th><th>Status</th><th>Cartão</th><th>Status condutor</th><th>Data associação</th><th>Data dissociação</th></tr></thead><tbody>
        <?php foreach ($consulta['linhas'] as $linha): ?><tr><td><?= esc($linha['matricula'] ?? '') ?></td><td><?= esc($linha['nome'] ?? '') ?></td><td><?= esc(badgeStatusAtivo($linha['ativo'] ?? '0')) ?></td><td><?= esc($linha['cartaoassoc'] ?? '') ?></td><td><?= esc($linha['statuscond'] ?? '') ?></td><td><?= esc(formatarDataPortal($linha['dataassoc'] ?? '')) ?></td><td><?= esc(formatarDataPortal($linha['datadissoc'] ?? '')) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
</div>
<?php renderRodapeAutofrota(); ?>

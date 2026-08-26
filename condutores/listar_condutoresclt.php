<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();

$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');
$databaseCorp = trim((string) ($autofrotaSessao['databaseCorp'] ?? ($GLOBALS['databaseCorp'] ?? '')));
if ($databaseCorp === '') {
    $databaseCorp = 'bdcorp';
}
$ccustoFiltro = valorRequisicao(['ccusto']);
$cargoFiltro = valorRequisicao(['cargo']);
$cnhFiltro = valorRequisicao(['cnh']);
$where = ['f.idtbempresa = 2', "UPPER(TRIM(f.status)) = 'ATIVO'"];
$tipos = '';
$params = [];
if ($ccustoFiltro !== '') { $where[] = 'f.ccusto = ?'; $tipos .= 's'; $params[] = $ccustoFiltro; }
if ($cargoFiltro !== '') { $where[] = 'f.cargo = ?'; $tipos .= 's'; $params[] = $cargoFiltro; }
if ($cnhFiltro === 'com') {
    $where[] = "EXISTS (SELECT 1 FROM `{$databaseName}`.`tbcnh` c WHERE c.matricula = f.matricula)";
} elseif ($cnhFiltro === 'sem') {
    $where[] = "NOT EXISTS (SELECT 1 FROM `{$databaseName}`.`tbcnh` c WHERE c.matricula = f.matricula)";
}
$consulta = consultaPreparada(
    $conn,
    "SELECT f.matricula, f.nome, f.ccusto, f.cargo, f.uf_trabalho, f.estado, f.dtadmissao, f.status,
            CASE WHEN EXISTS (SELECT 1 FROM `{$databaseName}`.`tbcnh` c WHERE c.matricula = f.matricula) THEN 'Sim' ELSE 'Não' END AS possui_cnh
       FROM `{$databaseCorp}`.`tbfuncionario` f
      WHERE " . implode(' AND ', $where) . " ORDER BY f.nome",
    $tipos,
    $params
);
$ccustos = consultaPreparada($conn, "SELECT DISTINCT ccusto FROM `{$databaseCorp}`.`tbfuncionario` WHERE idtbempresa = 2 AND UPPER(TRIM(status)) = 'ATIVO' AND ccusto IS NOT NULL AND ccusto <> '' ORDER BY ccusto");
$cargos = consultaPreparada($conn, "SELECT DISTINCT cargo FROM `{$databaseCorp}`.`tbfuncionario` WHERE idtbempresa = 2 AND UPPER(TRIM(status)) = 'ATIVO' AND cargo IS NOT NULL AND cargo <> '' ORDER BY cargo");
$mensagem = valorRequisicao(['msg']);

function normalizarStatusCondutorPj($status): string
{
    return mb_strtoupper(trim((string) ($status ?? '')), 'UTF-8');
}

function classeBadgeStatusCondutorPj($status): string
{
    $statusNormalizado = normalizarStatusCondutorPj($status);
    if ($statusNormalizado === 'INATIVO' || $statusNormalizado === 'DEMITIDO') {
        return 'text-bg-danger';
    }
    if ($statusNormalizado === 'AFASTADO') {
        return 'text-bg-warning';
    }
    if ($statusNormalizado === 'FERIAS' || $statusNormalizado === 'FÉRIAS') {
        return 'text-bg-info';
    }
    return 'text-bg-success';
}

renderCabecalhoAutofrota('Colaboradores Cadastrados');
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h1 class="h3 mb-1">Colaboradores Cadastrados</h1></div>
        <div class="d-flex gap-2">
            <!-- <a href="funcionarios-semcnh.php" class="btn btn-secondary"><i class="fa fa-user-times me-1"></i>Condutores sem CNH</a> -->
            <a href="listagemcnh.php" class="btn btn-secondary"><i class="fa fa-id-card me-1"></i>Anexar Documentos</a>
            <a href="listar_condutorespj.php" class="btn btn-success"><i class="fa fa-plus me-1"></i>Novo Condutor PJ</a>
            <a href="cadastrar_condutoresclt.php" class="btn btn-success"><i class="fa fa-plus me-1"></i>Novo Condutor Colaborador</a>

        </div>
    </div>
    <style>
        .condutor-status-badge{display:inline-flex;align-items:center;gap:.35rem;min-width:86px;justify-content:center;font-weight:600}
        .condutor-action{width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center}
    </style>
    <?php if ($mensagem !== ''): ?><div class="alert alert-info"><?= esc($mensagem) ?></div><?php endif; ?>
    <?php if ($consulta['erro'] !== ''): ?><div class="alert alert-danger"><?= esc($consulta['erro']) ?></div><?php endif; ?>
    <form method="get" class="card mb-3"><div class="card-body row g-3 align-items-end">
        <div class="col-md-3"><label class="form-label">Centro de custo</label><select class="form-select" name="ccusto"><option value="">Todos</option><?php foreach ($ccustos['linhas'] as $linha): $valor=$linha['ccusto']??''; ?><option value="<?= esc($valor) ?>" <?= $ccustoFiltro===$valor?'selected':'' ?>><?= esc($valor) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Cargo</label><select class="form-select" name="cargo"><option value="">Todos</option><?php foreach ($cargos['linhas'] as $linha): $valor=$linha['cargo']??''; ?><option value="<?= esc($valor) ?>" <?= $cargoFiltro===$valor?'selected':'' ?>><?= esc($valor) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">CNH cadastrada</label><select class="form-select" name="cnh"><option value="">Todos</option><option value="com" <?= $cnhFiltro === 'com' ? 'selected' : '' ?>>Com CNH</option><option value="sem" <?= $cnhFiltro === 'sem' ? 'selected' : '' ?>>Sem CNH</option></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="fa fa-filter me-1"></i>Filtrar</button><a class="btn btn-secondary flex-fill" href="listar_condutoresclt.php">Limpar</a></div>
    </div></form>
    <div class="card"><div class="card-body table-responsive"><table class="table table-striped table-hover" data-datatable="1"><thead class="table-dark"><tr><th>Matrícula</th><th>Nome</th><th>Centro de custo</th><th>Cargo</th><th>UF</th><th>Data admissão</th><th>Status</th><th>CNH cadastrada</th><th>Ações</th></tr></thead><tbody>
        <?php foreach ($consulta['linhas'] as $linha): $mat=$linha['matricula']??''; $status=$linha['status'] ?? ''; $possuiCnh=($linha['possui_cnh'] ?? 'Não') === 'Sim'; ?><tr><td><?= esc($mat) ?></td><td><?= esc($linha['nome'] ?? '') ?></td><td><?= esc($linha['ccusto'] ?? '') ?></td><td><?= esc($linha['cargo'] ?? '') ?></td><td><?= esc(($linha['uf_trabalho'] ?? '') ?: ($linha['estado'] ?? '')) ?></td><td><?= esc(formatarDataPortal($linha['dtadmissao'] ?? '', 'd/m/Y')) ?></td><td><span class="badge rounded-pill condutor-status-badge <?= esc(classeBadgeStatusCondutorPj($status)) ?>"><i class="fa fa-circle"></i><?= esc($status) ?></span></td><td><?= $possuiCnh ? 'Sim' : 'Não' ?></td><td class="text-nowrap"><a class="btn btn-sm btn-info condutor-action" href="dados-condutor-clt.php?matcond=<?= urlencode((string) $mat) ?>" title="Dados da CNH"><i class="fa fa-eye"></i></a><a class="btn btn-sm btn-warning condutor-action" href="editar_condutoresclt.php?matricula=<?= urlencode((string) $mat) ?>" title="Editar CNH"><i class="fa fa-pen-to-square"></i></a><?php if ($possuiCnh): ?><form class="d-inline" method="post" action="enviardocscondutorclt.php"><input type="hidden" name="matcondutor" value="<?= esc($mat) ?>"><button class="btn btn-sm btn-secondary condutor-action" type="submit" title="Anexar documentos" aria-label="Anexar documentos"><i class="fa fa-paperclip" aria-hidden="true"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
</div>
<?php renderRodapeAutofrota(); ?>
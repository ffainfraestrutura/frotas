<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();

$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');
$databaseCorp = trim((string) ($autofrotaSessao['databaseCorp'] ?? ($GLOBALS['databaseCorp'] ?? '')));
if ($databaseCorp === '') {
    $databaseCorp = 'bdcorp';
}
$statusFiltro = valorRequisicao(['status']);
$ccustoFiltro = valorRequisicao(['ccusto']);
$cargoFiltro = valorRequisicao(['cargo']);
$regimeFiltro = valorRequisicao(['regime']);
$where = ['1=1'];
$tipos = '';
$params = [];
if ($statusFiltro !== '') { $where[] = 'status = ?'; $tipos .= 's'; $params[] = $statusFiltro; }
if ($ccustoFiltro !== '') { $where[] = 'ccusto = ?'; $tipos .= 's'; $params[] = $ccustoFiltro; }
if ($cargoFiltro !== '') { $where[] = 'cargo = ?'; $tipos .= 's'; $params[] = $cargoFiltro; }
if ($regimeFiltro === '0' || $regimeFiltro === '1') { $where[] = 'regime = ?'; $tipos .= 's'; $params[] = $regimeFiltro; }
$consulta = consultaPreparada($conn, "SELECT matricula, nome, ccusto, cargo, uf_trabalho, estado, dtadmissao, status FROM `{$databaseName}`.`tbcondutor` WHERE " . implode(' AND ', $where) . " ORDER BY nome", $tipos, $params);
$ccustos = consultaPreparada($conn, "SELECT DISTINCT ccusto FROM `{$databaseName}`.`tbcondutor` WHERE ccusto IS NOT NULL AND ccusto <> '' ORDER BY ccusto");
$cargos = consultaPreparada($conn, "SELECT DISTINCT cargo FROM `{$databaseName}`.`tbcondutor` WHERE cargo IS NOT NULL AND cargo <> '' ORDER BY cargo");
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

renderCabecalhoAutofrota('Condutores Cadastrados');
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h1 class="h3 mb-1">Condutores Cadastrados</h1></div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-success" id="exportarNomesExcel"><i class="fa fa-file-excel me-1"></i>Exportar nomes</button>
            <!-- <a href="funcionarios-semcnh.php" class="btn btn-secondary"><i class="fa fa-user-times me-1"></i>Condutores sem CNH</a> -->
            <a href="listagemcnh.php" class="btn btn-secondary"><i class="fa fa-id-card me-1"></i>Anexar Documentos</a>
            <a href="cadastrar_condutorespj.php" class="btn btn-success"><i class="fa fa-plus me-1"></i>Novo Condutor PJ</a>
            <a href="listar_condutoresclt.php" class="btn btn-success"><i class="fa fa-plus me-1"></i>Novo Condutor Colaborador</a>

        </div>
    </div>
    <style>
        .condutor-status-badge{display:inline-flex;align-items:center;gap:.35rem;min-width:86px;justify-content:center;font-weight:600}
        .condutor-action{width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center}
    </style>
    <?php if ($mensagem !== ''): ?><div class="alert alert-info"><?= esc($mensagem) ?></div><?php endif; ?>
    <?php if ($consulta['erro'] !== ''): ?><div class="alert alert-danger"><?= esc($consulta['erro']) ?></div><?php endif; ?>
    <form method="get" class="card mb-3"><div class="card-body row g-3 align-items-end">
        <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="">Todos</option><?php foreach (['Ativo','Inativo','Afastado','Ferias','Demitido'] as $status): ?><option value="<?= esc($status) ?>" <?= $statusFiltro===$status?'selected':'' ?>><?= esc($status) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Centro de custo</label><select class="form-select" name="ccusto"><option value="">Todos</option><?php foreach ($ccustos['linhas'] as $linha): $valor=$linha['ccusto']??''; ?><option value="<?= esc($valor) ?>" <?= $ccustoFiltro===$valor?'selected':'' ?>><?= esc($valor) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Cargo</label><select class="form-select" name="cargo"><option value="">Todos</option><?php foreach ($cargos['linhas'] as $linha): $valor=$linha['cargo']??''; ?><option value="<?= esc($valor) ?>" <?= $cargoFiltro===$valor?'selected':'' ?>><?= esc($valor) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Regime</label><select class="form-select" name="regime"><option value="">Todos</option><option value="0" <?= $regimeFiltro==='0'?'selected':'' ?>>PJ</option><option value="1" <?= $regimeFiltro==='1'?'selected':'' ?>>CLT</option></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="fa fa-filter me-1"></i>Filtrar</button><a class="btn btn-secondary flex-fill" href="listar_condutorespj.php">Limpar</a></div>
    </div></form>
    <div class="card"><div class="card-body table-responsive"><table class="table table-striped table-hover" id="tabelaCondutoresPj" data-datatable="1"><thead class="table-dark"><tr><th>Matrícula</th><th>Nome</th><th>Centro de custo</th><th>Cargo</th><th>UF</th><th>Data admissão</th><th>Status</th><th>Ações</th></tr></thead><tbody>
        <?php foreach ($consulta['linhas'] as $linha): $mat=$linha['matricula']??''; $status=$linha['status'] ?? ''; $condutorAtivo=normalizarStatusCondutorPj($status)==='ATIVO'; ?><tr><td><?= esc($mat) ?></td><td><?= esc($linha['nome'] ?? '') ?></td><td><?= esc($linha['ccusto'] ?? '') ?></td><td><?= esc($linha['cargo'] ?? '') ?></td><td><?= esc(($linha['uf_trabalho'] ?? '') ?: ($linha['estado'] ?? '')) ?></td><td><?= esc(formatarDataPortal($linha['dtadmissao'] ?? '', 'd/m/Y')) ?></td><td><span class="badge rounded-pill condutor-status-badge <?= esc(classeBadgeStatusCondutorPj($status)) ?>"><i class="fa fa-circle"></i><?= esc($status) ?></span></td><td class="text-nowrap"><?php if ($condutorAtivo): ?><a class="btn btn-sm btn-info condutor-action" href="dados-condutor-pj.php?matcond=<?= urlencode((string) $mat) ?>" title="Dados do condutor"><i class="fa fa-eye"></i></a><a class="btn btn-sm btn-warning condutor-action" href="editar_condutorespj.php?matricula=<?= urlencode((string) $mat) ?>" title="Editar condutor"><i class="fa fa-pen-to-square"></i></a><?php else: ?><span class="text-muted" title="Ações disponíveis somente para condutores ativos">—</span><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const botaoExportar = document.getElementById('exportarNomesExcel');
    const tabela = document.getElementById('tabelaCondutoresPj');

    botaoExportar.addEventListener('click', function () {
        let linhas = Array.from(tabela.tBodies[0].rows);

        if (window.jQuery && jQuery.fn && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable(tabela)) {
            linhas = jQuery(tabela).DataTable().rows({search: 'applied'}).nodes().toArray();
        }

        const nomes = linhas
            .map(function (linha) { return linha.cells[1] ? linha.cells[1].textContent.trim() : ''; })
            .filter(function (nome) { return nome !== ''; });

        if (nomes.length === 0) {
            window.alert('Não há nomes para exportar.');
            return;
        }

        const escaparHtml = function (valor) {
            return valor.replace(/[&<>"']/g, function (caractere) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[caractere];
            });
        };
        const conteudo = '<html><head><meta charset="UTF-8"></head><body><table><thead><tr><th>Nome</th></tr></thead><tbody>'
            + nomes.map(function (nome) { return '<tr><td>' + escaparHtml(nome) + '</td></tr>'; }).join('')
            + '</tbody></table></body></html>';
        const url = URL.createObjectURL(new Blob(['\ufeff' + conteudo], {type: 'application/vnd.ms-excel;charset=utf-8'}));
        const link = document.createElement('a');
        link.href = url;
        link.download = 'nomes_condutores_' + new Date().toISOString().slice(0, 10) + '.xls';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    });
});
</script>
<?php renderRodapeAutofrota(); ?>
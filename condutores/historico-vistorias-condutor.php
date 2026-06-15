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

    header('Location: historico-vistorias-condutor.php' . ($parametros !== [] ? '?' . http_build_query($parametros) : ''));
    exit;
}

$condutor = $matcond !== '' ? buscarUmaLinha($conn, "SELECT nome FROM `{$databaseName}`.`tbfuncionario` WHERE matricula = ? LIMIT 1", 's', [$matcond]) : [];
$consulta = $matcond !== '' ? consultaPreparada($conn, "SELECT v.nome, v.placa, COALESCE(tv.tipo, v.tipo) AS tipo_vistoria, v.vistoriador, v.datavistoria FROM `{$databaseName}`.`tbvistoria` v LEFT JOIN `{$databaseName}`.`tbatipovist` tv ON tv.idtbatipovist = v.tipo WHERE v.matricula = ? ORDER BY v.datavistoria DESC", 's', [$matcond]) : ['erro' => '', 'linhas' => []];
renderCabecalhoAutofrota('Histórico de Checklist do Condutor');
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Histórico de Vistorias</h1><p class="text-muted mb-0">Condutor: <?= esc($condutor['nome'] ?? '') ?> (<?= esc($matcond) ?>)</p></div><a class="btn btn-secondary" href="dados-condutor.php?matcond=<?= urlencode($matcond) ?>">Voltar</a></div>
    <?php if ($matcond === ''): ?><div class="alert alert-warning">Informe a matrícula do condutor.</div><?php endif; ?>
    <?php if ($consulta['erro'] !== ''): ?><div class="alert alert-danger"><?= esc($consulta['erro']) ?></div><?php endif; ?>
    <div class="card"><div class="card-body table-responsive"><table id="historico-vistorias-table" class="table table-striped" data-datatable="1"><thead><tr><th>Condutor</th><th>Placa</th><th>Tipo da vistoria</th><th>Realizada por</th><th>Data de vistoria</th></tr></thead><tbody>
        <?php foreach ($consulta['linhas'] as $linha): ?><tr><td><?= esc($linha['nome'] ?? ($condutor['nome'] ?? '')) ?></td><td><?= esc($linha['placa'] ?? '') ?></td><td><?= esc($linha['tipo_vistoria'] ?? '') ?></td><td><?= esc($linha['vistoriador'] ?? '') ?></td><td><?= esc(formatarDataPortal($linha['datavistoria'] ?? '')) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn && jQuery.fn.DataTable) {
        var tabela = jQuery('#historico-vistorias-table');
        if (!tabela.data('dtInitialized')) {
            tabela.data('dtInitialized', true);
            tabela.DataTable({
                ordering: true,
                searching: true,
                paging: true,
                lengthChange: true,
                autoWidth: false,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
                order: [[4, 'desc']],
                dom: 'lfrtip',
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/pt-BR.json',
                    search: 'Buscar:',
                    lengthMenu: 'Exibir _MENU_ registros',
                    info: 'Mostrando _START_ até _END_ de _TOTAL_ registros',
                    infoEmpty: 'Mostrando 0 até 0 de 0 registros',
                    infoFiltered: '(filtrado de _MAX_ registros no total)',
                    zeroRecords: 'Nenhum registro encontrado',
                    paginate: { first: 'Primeiro', last: 'Último', next: 'Próximo', previous: 'Anterior' }
                }
            });
        }
    }
});
</script>
</body></html>
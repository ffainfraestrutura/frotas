<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/control/conecta.php';
require_once __DIR__ . '/includes/portal_helpers.php';
exigirLogin();

function descreverTipoManutencao(string $tipo): string
{
    if ($tipo === 'MP') {
        return 'Manutenção Preventiva';
    }
    if ($tipo === 'OS') {
        return 'Ordem de Serviço';
    }
    if ($tipo === 'MC') {
        return 'Manutenção Corretiva';
    }
    if ($tipo === 'SS') {
        return 'Sinistro';
    }

    return $tipo;
}

$placa = strtoupper(valorRequisicao(['placa']));
$consulta = $placa !== ''
    ? consultaPreparada(
        $conn,
        "SELECT idtbmanprev, placa, data, atualizadoem, dataagendamento, dataretirada, tipo, status
         FROM `{$databaseName}`.`tbmanprev`
         WHERE placa = ?
         ORDER BY data DESC, idtbmanprev DESC",
        's',
        [$placa]
    )
    : ['erro' => '', 'linhas' => []];
renderCabecalhoAutofrota('Histórico de Manutenção da Placa');
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Histórico de Manutenção</h1><p class="text-muted mb-0">Placa: <?= esc($placa) ?></p></div><button class="btn btn-secondary" onclick="history.back()">Voltar</button></div>
    <?php if ($placa === ''): ?><div class="alert alert-warning">Informe a placa.</div><?php endif; ?>
    <?php if ($consulta['erro'] !== ''): ?><div class="alert alert-danger"><?= esc($consulta['erro']) ?></div><?php endif; ?>
    <div class="card"><div class="card-body table-responsive"><table class="table table-striped" data-datatable="1"><thead><tr><th>Placa</th><th>Data de Cadastro</th><th>Atualizado em</th><th>Data de Agendamento</th><th>Data de Conclusão</th><th>Tipo</th><th>Status</th><th>Ordem de Serviço</th></tr></thead><tbody>
        <?php foreach ($consulta['linhas'] as $linha): ?>
            <tr>
                <td><?= esc($linha['placa'] ?? '') ?></td>
                <td><?= esc(formatarDataPortal($linha['data'] ?? '', 'd/m/Y H:i:s')) ?></td>
                <td><?= esc(formatarDataPortal($linha['atualizadoem'] ?? '', 'd/m/Y H:i:s')) ?></td>
                <td><?= esc(formatarDataPortal($linha['dataagendamento'] ?? '', 'd/m/Y')) ?></td>
                <td><?= esc(formatarDataPortal($linha['dataretirada'] ?? '', 'd/m/Y')) ?></td>
                <td><?= esc(descreverTipoManutencao((string) ($linha['tipo'] ?? ''))) ?></td>
                <td><?= esc($linha['status'] ?? '') ?></td>
                <td>
                    <form method="post" action="https://frotas.painel-telecom.com/pdf/fpdf/ordemdeservico.php" target="_blank" class="d-inline">
                        <input type="hidden" name="idtbmanprev" value="<?= esc($linha['idtbmanprev'] ?? '') ?>">
                        <button style="border: none; background-color: transparent;" type="submit" title="Ordem de Serviço">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody></table></div></div>
</div>
<?php renderRodapeAutofrota(); ?>

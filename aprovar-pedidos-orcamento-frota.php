<?php
require_once __DIR__ . '/includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? ($GLOBALS['conn'] ?? null);
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? ($GLOBALS['databaseName'] ?? 'bdautofrotas'));
$databaseCorp = (string) ($autofrotaSessao['databaseCorp'] ?? ($GLOBALS['databaseCorp'] ?? 'bdcorp'));
$matriculaLogada = (string) ($autofrotaSessao['matricula'] ?? $_SESSION['matricula'] ?? '');
$nomeLogado = (string) ($autofrotaSessao['usuario'] ?? $_SESSION['nome'] ?? '');
$nomeExibicao = autofrotaNomeExibicaoPorMatricula($conn, $databaseCorp, $matriculaLogada, $nomeLogado);
$perfilLogado = (string) ($autofrotaSessao['perfil'] ?? $_SESSION['perfil'] ?? '');
$matriculasAutorizadas = ['601004', '004607', '086272', '000000', '601000'];

if (!$conn instanceof mysqli) {
    exit('Conexão indisponível.');
}
if ($perfilLogado !== '4' || !in_array($matriculaLogada, $matriculasAutorizadas, true)) {
    http_response_code(403);
    exit('Acesso permitido apenas para frota autorizada.');
}

function moedaFrotaOrcamento($valor): string
{
    return number_format((float) ($valor ?? 0), 2, ',', '.');
}

$mensagem = (string) ($_SESSION['frota_orcamento_mensagem'] ?? '');
$tipoMensagem = (string) ($_SESSION['frota_orcamento_tipo'] ?? 'info');
unset($_SESSION['frota_orcamento_mensagem'], $_SESSION['frota_orcamento_tipo']);

$_SESSION['frota_orcamento_token'] = bin2hex(random_bytes(32));
$token = $_SESSION['frota_orcamento_token'];

$saldoFrotaLinha = buscarUmaLinha(
    $conn,
    "SELECT idtbsaldofrota, saldoatual FROM `{$databaseName}`.`tbsaldofrota` ORDER BY data_e_hora DESC LIMIT 1"
);
$saldoFrota = is_numeric($saldoFrotaLinha['saldoatual'] ?? null) ? (float) $saldoFrotaLinha['saldoatual'] : 0.0;
$dataHoje = date('Y-m-d');
$segunda = date('Y-m-d', strtotime('monday this week'));

$consultaPedidos = consultaPreparada(
    $conn,
    "SELECT p.idtbpedidosgerente, p.matricula, p.valor AS valor_pedido, p.justificativa, p.data,
            COALESCE(g.valor, d.valor, 0) AS saldo_atual,
            COALESCE(g.orcrecebido, d.orcrecebido, 0) AS orcrecebido,
            CASE WHEN g.matricula IS NOT NULL THEN 'Gerente' ELSE 'Diretor' END AS tipo_solicitante,
            u.nome,
            COALESCE(r.remanejpositivo, 0) AS remanejpositivo,
            COALESCE(r.remanejnegativo, 0) AS remanejnegativo,
            COALESCE(r.ffrota, 0) AS ffrota
       FROM `{$databaseName}`.`tbpedidosgerente` p
       LEFT JOIN `{$databaseCorp}`.`tbgerente` g ON g.matricula = p.matricula
       LEFT JOIN `{$databaseCorp}`.`tbdiretor` d ON d.matricula = p.matricula
       LEFT JOIN `{$databaseCorp}`.`tbusuario` u ON u.matricula = p.matricula
       LEFT JOIN (
            SELECT matr_autor,
                   SUM(CASE WHEN tipo = 1 THEN valor ELSE 0 END) AS remanejpositivo,
                   SUM(CASE WHEN tipo = 0 THEN valor ELSE 0 END) AS remanejnegativo,
                   SUM(CASE WHEN tipo = 1 AND perfil_autor = 4 THEN valor ELSE 0 END) AS ffrota
              FROM `{$databaseName}`.`tbremanejamento`
             WHERE data_e_hora BETWEEN ? AND ?
             GROUP BY matr_autor
       ) r ON r.matr_autor = p.matricula
      WHERE p.flag = 0 AND (g.matricula IS NOT NULL OR d.matricula IS NOT NULL)
      ORDER BY p.data ASC, u.nome ASC",
    'ss',
    ["{$segunda} 00:00:00", "{$dataHoje} 23:59:59"]
);
$erroTela = (string) ($consultaPedidos['erro'] ?? '');
$pedidos = $erroTela === '' ? $consultaPedidos['linhas'] : [];

renderCabecalhoAutofrota('Aprovar Pedidos de Orçamento');
?>
<div class="container-fluid px-4">
    <section class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <h1 class="h3 mb-1">Aprovar pedidos de orçamento de gerentes e diretores</h1>
                <p class="text-muted mb-0">Frota: <strong><?= esc($nomeExibicao . '(' . $matriculaLogada . ')') ?></strong></p>
            </div>
            <div class="border rounded-3 px-3 py-2 bg-light align-self-start">
                <div class="text-muted small">Fundo atual da frota</div>
                <div class="fw-bold text-primary">R$ <?= esc(moedaFrotaOrcamento($saldoFrota)) ?></div>
            </div>
        </div>
    </section>

    <?php if ($mensagem !== ''): ?>
        <div class="alert alert-<?= esc($tipoMensagem) ?> alert-dismissible fade show" role="alert">
            <?= esc($mensagem) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>
    <?php if ($erroTela !== ''): ?><div class="alert alert-danger">Erro ao buscar pedidos: <?= esc($erroTela) ?></div><?php endif; ?>

    <section class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-table me-2"></i>Solicitações:</div>
        <div class="card-body table-responsive">
            <table class="table table-striped table-hover align-middle" data-datatable="1">
                <thead>
                    <tr>
                        <th>Matrícula</th>
                        <th>Solicitante</th>
                        <th>Tipo</th>
                        <th>Saldo</th>
                        <th>Orç. complementar</th>
                        <th>Sld. remanejamento</th>
                        <th>Fd. frota</th>
                        <th>Data e hora</th>
                        <th>Justificativa</th>
                        <th>Novo orç.</th>
                        <th class="text-center">Decisão</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                        <?php $saldoRemanejamento = (float) $pedido['remanejpositivo'] - (float) $pedido['remanejnegativo']; ?>
                        <tr>
                            <td><?= esc($pedido['matricula']) ?></td>
                            <td><?= esc($pedido['nome'] ?? 'Sem nome') ?></td>
                            <td><?= esc($pedido['tipo_solicitante']) ?></td>
                            <td>R$ <?= esc(moedaFrotaOrcamento($pedido['saldo_atual'])) ?></td>
                            <td>R$ <?= esc(moedaFrotaOrcamento($pedido['orcrecebido'])) ?></td>
                            <td>R$ <?= esc(moedaFrotaOrcamento($saldoRemanejamento)) ?></td>
                            <td>R$ <?= esc(moedaFrotaOrcamento($pedido['ffrota'])) ?></td>
                            <td><?= esc(formatarDataPortal($pedido['data'])) ?></td>
                            <td class="small" style="max-width: 360px;"><?= esc($pedido['justificativa']) ?></td>
                            <td class="fw-semibold">R$ <?= esc(moedaFrotaOrcamento($pedido['valor_pedido'])) ?></td>
                            <td class="text-center text-nowrap">
                                <form method="post" action="control/aprovar-pedidos-orcamento-frota.php" class="d-inline">
                                    <input type="hidden" name="token" value="<?= esc($token) ?>">
                                    <input type="hidden" name="id" value="<?= esc($pedido['idtbpedidosgerente']) ?>">
                                    <input type="hidden" name="decisao" value="2">
                                    <button type="submit" class="btn btn-outline-success btn-sm" title="Aprovar"><i class="fas fa-check"></i></button>
                                </form>
                                <form method="post" action="control/aprovar-pedidos-orcamento-frota.php" class="d-inline">
                                    <input type="hidden" name="token" value="<?= esc($token) ?>">
                                    <input type="hidden" name="id" value="<?= esc($pedido['idtbpedidosgerente']) ?>">
                                    <input type="hidden" name="decisao" value="1">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Reprovar"><i class="fas fa-xmark"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php renderRodapeAutofrota(); ?>
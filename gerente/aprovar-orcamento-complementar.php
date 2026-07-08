<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? ($GLOBALS['conn'] ?? null);
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? ($GLOBALS['databaseName'] ?? 'bdautofrotas'));
$databaseCorp = (string) ($autofrotaSessao['databaseCorp'] ?? ($GLOBALS['databaseCorp'] ?? 'bdcorp'));
$matriculaLogada = (string) ($autofrotaSessao['matricula'] ?? $_SESSION['matricula'] ?? '');
$nomeLogado = (string) ($autofrotaSessao['usuario'] ?? $_SESSION['nome'] ?? '');
$nomeExibicao = autofrotaNomeExibicaoPorMatricula($conn, $databaseCorp, $matriculaLogada, $nomeLogado);
$perfilLogado = (string) ($autofrotaSessao['perfil'] ?? $_SESSION['perfil'] ?? '');

if (!$conn instanceof mysqli) {
    exit('Conexão indisponível.');
}
if ($perfilLogado !== '3') {
    http_response_code(403);
    exit('Acesso permitido apenas para perfil gerente.');
}

function moedaAprovacaoOrcamentoGerente($valor): string
{
    return number_format((float) ($valor ?? 0), 2, ',', '.');
}

$mensagem = (string) ($_SESSION['aprovar_orcamento_gerente_mensagem'] ?? '');
$tipoMensagem = (string) ($_SESSION['aprovar_orcamento_gerente_tipo'] ?? 'info');
unset($_SESSION['aprovar_orcamento_gerente_mensagem'], $_SESSION['aprovar_orcamento_gerente_tipo']);

$_SESSION['aprovar_orcamento_gerente_token'] = bin2hex(random_bytes(32));
$token = $_SESSION['aprovar_orcamento_gerente_token'];

$gerente = buscarUmaLinha(
    $conn,
    "SELECT idtbgerente, valor, orcrecebido FROM `{$databaseCorp}`.`tbgerente` WHERE matricula = ? LIMIT 1",
    's',
    [$matriculaLogada]
);
$saldoGerente = is_numeric($gerente['valor'] ?? null) ? (float) $gerente['valor'] : 0.0;
$erroTela = $gerente === [] ? 'Cadastro do gerente não encontrado.' : '';
$pedidos = [];

if ($gerente !== []) {
    $consultaPedidos = consultaPreparada(
        $conn,
        "SELECT
                p.idtbpedidoscoord,
                p.matricula,
                p.valor AS valor_pedido,
                p.justificativa,
                p.data,
                c.valor AS saldo_coordenador,
                c.orcrecebido,
                u.nome,
                f.codempresa,
                CASE WHEN f.codempresa = 2 THEN 'SP' WHEN f.codempresa = 1 THEN 'RJ' ELSE COALESCE(CAST(f.codempresa AS CHAR), '') END AS filial
           FROM `{$databaseName}`.`tbpedidoscoord` p
           INNER JOIN `{$databaseCorp}`.`tbcoord` c ON c.matricula = p.matricula
           LEFT JOIN `{$databaseCorp}`.`tbusuario` u ON u.matricula = p.matricula
           LEFT JOIN `{$databaseName}`.`tbfuncionario` f ON f.matricula = p.matricula
          WHERE p.flag = 0
            AND c.idtbgerente = ?
          ORDER BY u.nome ASC, p.data ASC",
        'i',
        [(int) $gerente['idtbgerente']]
    );
    if (($consultaPedidos['erro'] ?? '') !== '') {
        $erroTela = 'Erro ao buscar pedidos: ' . $consultaPedidos['erro'];
    } else {
        $pedidos = $consultaPedidos['linhas'];
    }
}

renderCabecalhoAutofrota('Aprovar Orçamento Complementar de Coordenadores');
?>
<div class="container-fluid px-4">
    <section class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <h1 class="h3 mb-1">Aprovar orçamento complementar de coordenadores</h1>
                <p class="text-muted mb-0">Gerente: <strong><?= esc($nomeExibicao . ' (' . $matriculaLogada . ')') ?></strong></p>
            </div>
            <div class="border rounded-3 px-3 py-2 bg-light align-self-start">
                <div class="text-muted small">Saldo Gerente</div>
                <div class="fw-bold text-primary">R$ <?= esc(moedaAprovacaoOrcamentoGerente($saldoGerente)) ?></div>
            </div>
        </div>
    </section>

    <?php if ($mensagem !== ''): ?>
        <div class="alert alert-<?= esc($tipoMensagem) ?> alert-dismissible fade show" role="alert">
            <?= esc($mensagem) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>
    <?php if ($erroTela !== ''): ?><div class="alert alert-danger"><?= esc($erroTela) ?></div><?php endif; ?>

    <section class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-table me-2"></i>Pedidos:</div>
        <div class="card-body table-responsive">
            <table class="table table-striped table-hover align-middle" data-datatable="1">
                <thead>
                    <tr>
                        <th>Matrícula</th>
                        <th>Coordenador</th>
                        <th>Saldo Orçamento</th>
                        <th>Orç. complementar</th>
                        <th>Filial</th>
                        <th>Data e hora</th>
                        <th>Justificativa</th>
                        <th>Valor pedido</th>
                        <th class="text-center">Histórico</th>
                        <th class="text-center">Decisão</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                        <tr>
                            <td><?= esc($pedido['matricula']) ?></td>
                            <td><?= esc($pedido['nome'] ?? 'Sem nome') ?></td>
                            <td>R$ <?= esc(moedaAprovacaoOrcamentoGerente($pedido['saldo_coordenador'])) ?></td>
                            <td>R$ <?= esc(moedaAprovacaoOrcamentoGerente($pedido['orcrecebido'])) ?></td>
                            <td><?= esc($pedido['filial'] ?? '') ?></td>
                            <td><?= esc(formatarDataPortal($pedido['data'])) ?></td>
                            <td class="small" style="max-width: 360px;"><?= esc($pedido['justificativa']) ?></td>
                            <td class="fw-semibold">R$ <?= esc(moedaAprovacaoOrcamentoGerente($pedido['valor_pedido'])) ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-secondary btn-sm" title="Histórico" disabled><i class="fa-solid fa-eye"></i></button>
                            </td>
                            <td class="text-center text-nowrap">
                                <form method="post" action="control/aprovar-orcamento-complementar.php" class="d-inline">
                                    <input type="hidden" name="token" value="<?= esc($token) ?>">
                                    <input type="hidden" name="id" value="<?= esc($pedido['idtbpedidoscoord']) ?>">
                                    <input type="hidden" name="decisao" value="2">
                                    <button type="submit" class="btn btn-outline-success btn-sm" title="Aprovar"><i class="fas fa-check"></i></button>
                                </form>
                                <form method="post" action="control/aprovar-orcamento-complementar.php" class="d-inline">
                                    <input type="hidden" name="token" value="<?= esc($token) ?>">
                                    <input type="hidden" name="id" value="<?= esc($pedido['idtbpedidoscoord']) ?>">
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
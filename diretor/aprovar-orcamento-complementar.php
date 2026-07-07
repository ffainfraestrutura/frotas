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
if ($perfilLogado !== '10') {
    http_response_code(403);
    exit('Acesso permitido apenas para perfil diretor.');
}

function moedaAprovacaoOrcamentoDiretor($valor): string
{
    return number_format((float) ($valor ?? 0), 2, ',', '.');
}

$mensagem = (string) ($_SESSION['aprovar_orcamento_diretor_mensagem'] ?? '');
$tipoMensagem = (string) ($_SESSION['aprovar_orcamento_diretor_tipo'] ?? 'info');
unset($_SESSION['aprovar_orcamento_diretor_mensagem'], $_SESSION['aprovar_orcamento_diretor_tipo']);

$_SESSION['aprovar_orcamento_diretor_token'] = bin2hex(random_bytes(32));
$token = $_SESSION['aprovar_orcamento_diretor_token'];

$diretor = buscarUmaLinha($conn, "SELECT id, valor, orcrecebido FROM `{$databaseCorp}`.`tbdiretor` WHERE matricula = ? LIMIT 1", 's', [$matriculaLogada]);
$saldoDiretor = is_numeric($diretor['valor'] ?? null) ? (float) $diretor['valor'] : 0.0;
$erroTela = $diretor === [] ? 'Cadastro do diretor não encontrado.' : '';
$pedidos = [];

if ($diretor !== []) {
    $consultaPedidos = consultaPreparada(
        $conn,
        "SELECT p.idtbpedidosdiretor, p.matricula, p.valor AS valor_pedido, p.justificativa, p.data,
                g.valor AS saldo_gerente, g.orcrecebido, u.nome
           FROM `{$databaseName}`.`tbpedidosdiretor` p
           INNER JOIN `{$databaseCorp}`.`tbgerente` g ON g.matricula = p.matricula
           LEFT JOIN `{$databaseCorp}`.`tbusuario` u ON u.matricula = p.matricula
                    WHERE p.flag = 0 AND g.idtbdiretor = ?
          ORDER BY p.data ASC, u.nome ASC",
        'i',
        [(int) $diretor['id']]
    );
    if (($consultaPedidos['erro'] ?? '') !== '') {
        $erroTela = 'Erro ao buscar pedidos: ' . $consultaPedidos['erro'];
    } else {
        $pedidos = $consultaPedidos['linhas'];
    }
}

renderCabecalhoAutofrota('Aprovar Orçamento Complementar');
?>
<div class="container-fluid px-4">
    <section class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <h1 class="h3 mb-1">Aprovar orçamento complementar</h1>
                <p class="text-muted mb-0">Diretor: <strong><?= esc($nomeExibicao . ' (' . $matriculaLogada . ')') ?></strong></p>
            </div>
            <div class="border rounded-3 px-3 py-2 bg-light align-self-start">
                <div class="text-muted small">Saldo atual do diretor</div>
                <div class="fw-bold text-primary">R$ <?= esc(moedaAprovacaoOrcamentoDiretor($saldoDiretor)) ?></div>
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
                        <th>Gerente</th>
                        <th>Saldo</th>
                        <th>Orç. recebido</th>
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
                            <td>R$ <?= esc(moedaAprovacaoOrcamentoDiretor($pedido['saldo_gerente'])) ?></td>
                            <td>R$ <?= esc(moedaAprovacaoOrcamentoDiretor($pedido['orcrecebido'])) ?></td>
                            <td><?= esc(formatarDataPortal($pedido['data'])) ?></td>
                            <td class="small" style="max-width: 360px;"><?= esc($pedido['justificativa']) ?></td>
                            <td class="fw-semibold">R$ <?= esc(moedaAprovacaoOrcamentoDiretor($pedido['valor_pedido'])) ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-secondary btn-sm" title="Histórico" disabled><i class="fa-solid fa-eye"></i></button>
                            </td>
                            <td class="text-center text-nowrap">
                                <form method="post" action="control/aprovar-orcamento-complementar.php" class="d-inline">
                                    <input type="hidden" name="token" value="<?= esc($token) ?>">
                                    <input type="hidden" name="id" value="<?= esc($pedido['idtbpedidosdiretor']) ?>">
                                    <input type="hidden" name="decisao" value="2">
                                    <button type="submit" class="btn btn-outline-success btn-sm" title="Aprovar"><i class="fas fa-check"></i></button>
                                </form>
                                <form method="post" action="control/aprovar-orcamento-complementar.php" class="d-inline">
                                    <input type="hidden" name="token" value="<?= esc($token) ?>">
                                    <input type="hidden" name="id" value="<?= esc($pedido['idtbpedidosdiretor']) ?>">
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
<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../control/conecta.php';
require_once __DIR__ . '/../includes/portal_helpers.php';
exigirLogin();

$placa = strtoupper(valorRequisicao(['placa']));
$matriculaRequisitada = trim(valorRequisicao(['matcond', 'matcondutor', 'matricula']));
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $placa !== '') {
    $parametros = ['placa' => $placa];
    if ($matriculaRequisitada !== '') {
        $parametros['matcond'] = $matriculaRequisitada;
    }

    header('Location: dadosplaca.php?' . http_build_query($parametros));
    exit;
}
$veiculo = [];
$condutor = [];
$matriculaCondutor = '';
$statusVeiculo = '';
$aplicacaoVeiculo = '';
$modeloVeiculo = '';
$marcaVeiculo = '';
$locadora = '';
$ultimoLog = [];
$documentos = [];
$erro = '';

if (!function_exists('valorLinha')) {
    function valorLinha(array $linha, array $colunas, string $padrao = ''): string
    {
        foreach ($colunas as $coluna) {
            if (array_key_exists($coluna, $linha) && trim((string) ($linha[$coluna] ?? '')) !== '') {
                return (string) $linha[$coluna];
            }
        }

        return $padrao;
    }
}
if (!function_exists('buscarPorIdAutofrotas')) {
    function buscarPorIdAutofrotas(mysqli $conn, string $databaseName, string $tabela, string $colunaId, string $id, array $colunasValor): string
    {
        if ($id === '') {
            return '';
        }

        $linha = buscarUmaLinha($conn, "SELECT * FROM `{$databaseName}`.`{$tabela}` WHERE `{$colunaId}` = ? LIMIT 1", 's', [$id]);
        return valorLinha($linha, $colunasValor);
    }
}

if (!function_exists('tabelaExisteAutofrotas')) {
    function tabelaExisteAutofrotas(mysqli $conn, string $databaseName, string $tabela): bool
    {
        $consulta = consultaPreparada(
            $conn,
            "SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ? LIMIT 1",
            'ss',
            [$databaseName, $tabela]
        );

        return !empty($consulta['linhas']);
    }
}

if ($placa !== '' && isset($conn) && $conn instanceof mysqli) {
    $veiculo = buscarUmaLinha($conn, "SELECT * FROM `{$databaseName}`.`tbveiculo` WHERE placa = ? LIMIT 1", 's', [$placa]);

    if ($veiculo !== []) {
        $matriculaCondutor = $matriculaRequisitada !== ''
            ? $matriculaRequisitada
            : valorLinha($veiculo, ['matcond', 'matcondutor']);
        if ($matriculaCondutor === '' && tabelaExisteAutofrotas($conn, $databaseName, 'tbcondutor')) {
            $vinculoCondutor = buscarUmaLinha(
                $conn,
                "SELECT matricula FROM `{$databaseName}`.`tbcondutor` WHERE placaassoc = ? AND ativo = '1' ORDER BY dataassoc DESC, idtbcondutor DESC LIMIT 1",
                's',
                [$placa]
            );
            $matriculaCondutor = valorLinha($vinculoCondutor, ['matricula']);
        }
        $condutor = $matriculaCondutor !== ''
            ? buscarUmaLinha($conn, "SELECT * FROM `{$databaseName}`.`tbfuncionario` WHERE matricula = ? LIMIT 1", 's', [$matriculaCondutor])
            : [];

        if (tabelaExisteAutofrotas($conn, $databaseName, 'tbveiculostatus')) {
            $statusVeiculo = buscarPorIdAutofrotas($conn, $databaseName, 'tbveiculostatus', 'idtbstatusveic', valorLinha($veiculo, ['statusvel']), ['status', 'descricao']);
        }
        if (tabelaExisteAutofrotas($conn, $databaseName, 'tbveiculoaplicacao')) {
            $aplicacaoVeiculo = buscarPorIdAutofrotas($conn, $databaseName, 'tbveiculoaplicacao', 'idtbaplicacaoveic', valorLinha($veiculo, ['aplicacao']), ['aplicacao', 'descricao']);
        }
        if (tabelaExisteAutofrotas($conn, $databaseName, 'tbveiculomodelo')) {
            $modeloVeiculo = buscarPorIdAutofrotas($conn, $databaseName, 'tbveiculomodelo', 'idtbmodeloveic', valorLinha($veiculo, ['modelo', 'versao']), ['modelo', 'descricao']);
        }
        if (tabelaExisteAutofrotas($conn, $databaseName, 'tbmarcaveic')) {
            $marcaVeiculo = buscarPorIdAutofrotas($conn, $databaseName, 'tbmarcaveic', 'idtbmarcaveic', valorLinha($veiculo, ['marca']), ['marca', 'descricao']);
        }
        if (tabelaExisteAutofrotas($conn, $databaseName, 'tbfornecedor')) {
            $locadora = buscarPorIdAutofrotas($conn, $databaseName, 'tbfornecedor', 'idtbfornecedor', valorLinha($veiculo, ['idlocador']), ['fantasia', 'razaosocial', 'nome']);
        }

        if (tabelaExisteAutofrotas($conn, $databaseName, 'tblog')) {
            $consultaLog = consultaPreparada(
                $conn,
                "SELECT * FROM `{$databaseName}`.`tblog` WHERE placa = ? AND tipo IN ('cadastro', 'edição', 'checklist') ORDER BY idtblog DESC LIMIT 1",
                's',
                [$placa]
            );
            $ultimoLog = $consultaLog['linhas'][0] ?? [];
            if ($consultaLog['erro'] !== '') {
                $erro = trim($erro . ' ' . $consultaLog['erro']);
            }
        }

        if (tabelaExisteAutofrotas($conn, $databaseName, 'tbveicdocs')) {
            $consultaDocs = consultaPreparada($conn, "SELECT * FROM `{$databaseName}`.`tbveicdocs` WHERE placa = ? LIMIT 1", 's', [$placa]);
            $documentos = $consultaDocs['linhas'][0] ?? [];
            if ($consultaDocs['erro'] !== '') {
                $erro = trim($erro . ' ' . $consultaDocs['erro']);
            }
        }
    }
}

$matriculaCondutor = $matriculaCondutor !== '' ? $matriculaCondutor : valorLinha($veiculo, ['matcond', 'matcondutor']);
$nomeCondutor = valorLinha($condutor, ['nome']);
$modeloExibicao = $modeloVeiculo !== '' ? $modeloVeiculo : valorLinha($veiculo, ['modelo', 'versao']);
$marcaExibicao = $marcaVeiculo !== '' ? $marcaVeiculo : valorLinha($veiculo, ['marca']);
$statusExibicao = valorLinha($veiculo, ['status']);
if ($statusExibicao === '0') {
    $statusExibicao = 'Inativo';
} elseif ($statusExibicao === '1') {
    $statusExibicao = 'Ativo';
}
$statusVeiculo = $statusVeiculo !== '' ? $statusVeiculo : valorLinha($veiculo, ['statusvel', 'situacao']);
$aplicacaoExibicao = $aplicacaoVeiculo !== '' ? $aplicacaoVeiculo : valorLinha($veiculo, ['aplicacao']);
$autorMovimentacao = valorLinha($ultimoLog, ['mat_autor']);
if (in_array($autorMovimentacao, ['003535', '003427'], true)) {
    $autorMovimentacao = 'EQUIPE TI';
} elseif ($autorMovimentacao !== '' && isset($conn) && $conn instanceof mysqli) {
    $autor = buscarUmaLinha($conn, "SELECT nome FROM `{$databaseName}`.`tbfuncionario` WHERE matricula = ? LIMIT 1", 's', [$autorMovimentacao]);
    $autorMovimentacao = valorLinha($autor, ['nome'], $autorMovimentacao);
}

renderCabecalhoAutofrota('Dados do Veículo');
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Dados do Veículo</h1>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-primary" href="../veiculos/inventario-veiculo.php"><i class="fa fa-car me-1"></i>Consultar Veículos</a>
            <button class="btn btn-secondary" type="button" onclick="window.location.href = 'listagem-condutor.php';"><i class="fa fa-arrow-left me-1"></i>Voltar</button>
        </div>
    </div>

    <?php if ($placa === ''): ?>
        <div class="alert alert-warning">Informe a placa.</div>
    <?php elseif ($veiculo === []): ?>
        <div class="alert alert-warning">Veículo não encontrado para a placa <?= esc($placa) ?>.</div>
    <?php else: ?>
        <?php if ($erro !== ''): ?><div class="alert alert-warning">Algumas informações complementares não puderam ser carregadas: <?= esc($erro) ?></div><?php endif; ?>

        <div class="card card-info mb-3">
            <div class="card-header fw-semibold">Identificação</div>
            <div class="card-body row g-3">
                <?php
                $camposIdentificacao = [
                    'Status' => $statusExibicao,
                    'Placa' => valorLinha($veiculo, ['placa'], $placa),
                    'UF Placa' => valorLinha($veiculo, ['uf']),
                    'Cor' => valorLinha($veiculo, ['cor']),
                    'Marca' => $marcaExibicao,
                    'Modelo' => $modeloExibicao,
                    'Versão' => valorLinha($veiculo, ['versao']),
                    'Status do veículo' => $statusVeiculo,
                    'Aplicação' => $aplicacaoExibicao,
                    'Tipo' => valorLinha($veiculo, ['tipo', 'tipovel']),
                    'Situação' => valorLinha($veiculo, ['situacao']),
                    'Condutor' => $nomeCondutor,
                    'Matrícula condutor' => $matriculaCondutor,
                    'Ano fabricação' => valorLinha($veiculo, ['anofabr', 'anofabricacao']),
                    'Ano modelo' => valorLinha($veiculo, ['anomodelo']),
                    'Zero KM' => valorLinha($veiculo, ['zerokm']),
                ];
                foreach ($camposIdentificacao as $label => $valor): ?>
                    <div class="col-md-3">
                        <label><?= esc($label) ?></label>
                        <input class="form-control" value="<?= esc($valor) ?>" readonly>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card card-info mb-3">
            <div class="card-header fw-semibold">Dados técnicos e documentação</div>
            <div class="card-body row g-3">
                <?php
                $camposTecnicos = [
                    'RENAVAM' => valorLinha($veiculo, ['renavam']),
                    'Chassi' => valorLinha($veiculo, ['chassi']),
                    'Número do motor' => valorLinha($veiculo, ['nummotor']),
                    'Combustível' => valorLinha($veiculo, ['combustivel']),
                    'Tanque' => valorLinha($veiculo, ['tanque']),
                    'Motorização' => valorLinha($veiculo, ['motorizacao']),
                    'Portas' => valorLinha($veiculo, ['nportas']),
                    'Passageiros' => valorLinha($veiculo, ['npassageiros']),
                    'Eixos' => valorLinha($veiculo, ['qtdeixos']),
                    'Pneus' => valorLinha($veiculo, ['qtdpneus']),
                    'Estepes' => valorLinha($veiculo, ['qtdestepe']),
                    'Aro' => valorLinha($veiculo, ['aro']),
                    'Calibragem' => valorLinha($veiculo, ['calibragem']),
                    'Velocidade máxima' => valorLinha($veiculo, ['velocmax']),
                    'GNV' => valorLinha($veiculo, ['gnv']),
                    'GPS' => valorLinha($veiculo, ['gps']),
                    'TAG pedágio' => valorLinha($veiculo, ['tagpedagio']),
                    'Airbag' => valorLinha($veiculo, ['airbag']),
                    'GPS empresa' => valorLinha($veiculo, ['gpsemp']),
                    'Rack' => valorLinha($veiculo, ['rack']),
                    'Blindagem' => valorLinha($veiculo, ['blindagem']),
                    'BO' => valorLinha($veiculo, ['bo']),
                ];
                foreach ($camposTecnicos as $label => $valor): ?>
                    <div class="col-md-3">
                        <label><?= esc($label) ?></label>
                        <input class="form-control" value="<?= esc($valor) ?>" readonly>
                    </div>
                <?php endforeach; ?>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <?php foreach (['crlv' => 'CRLV', 'crv' => 'CRV', 'cert_ipva' => 'IPVA'] as $campo => $label): if (!empty($documentos[$campo])): ?>
                        <a class="btn btn-outline-secondary" href="<?= esc($documentos[$campo]) ?>" target="_blank" rel="noopener"><i class="fa fa-file me-1"></i><?= esc($label) ?></a>
                    <?php endif; endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card card-info mb-3">
            <div class="card-header fw-semibold">Operação, posse e movimentação</div>
            <div class="card-body row g-3">
                <?php
                $camposOperacao = [
                    'Hodômetro inicial' => valorLinha($veiculo, ['hodometroinicial']),
                    'Hodômetro atual' => valorLinha($veiculo, ['hodometro']),
                    'Oficina' => valorLinha($veiculo, ['oficina']),
                    'Tipo de posse' => valorLinha($veiculo, ['tipoposse']),
                    'Locador' => $locadora,
                    'Contrato locação' => valorLinha($veiculo, ['ncontloc']),
                    'Disponível locação' => formatarDataPortal(valorLinha($veiculo, ['dtdisponivelloc']), 'd/m/Y'),
                    'Devolução locação' => formatarDataPortal(valorLinha($veiculo, ['dtdevolucaoloc']), 'd/m/Y'),
                    'Data entrega' => formatarDataPortal(valorLinha($veiculo, ['dtentrega']), 'd/m/Y'),
                    'Data devolução' => formatarDataPortal(valorLinha($veiculo, ['dtdevolucao']), 'd/m/Y'),
                    'Valor aquisição' => valorLinha($veiculo, ['valaquisicao']),
                    'Total KM mensal' => valorLinha($veiculo, ['totkmmensal']),
                    'Base gestão' => valorLinha($veiculo, ['basegestao']),
                    'Centro de custo' => valorLinha($veiculo, ['ccusto']),
                    'Unidade' => valorLinha($veiculo, ['unidade']),
                    'Última movimentação' => formatarDataPortal(valorLinha($ultimoLog, ['data_e_hora'], valorLinha($veiculo, ['datamovimentacao']))),
                    'Ação' => valorLinha($ultimoLog, ['acao']),
                    'Autor' => $autorMovimentacao,
                ];
                foreach ($camposOperacao as $label => $valor): ?>
                    <div class="col-md-3">
                        <label><?= esc($label) ?></label>
                        <input class="form-control" value="<?= esc($valor) ?>" readonly>
                    </div>
                <?php endforeach; ?>
                <div class="col-12">
                    <label>Observações</label>
                    <textarea class="form-control" rows="3" readonly><?= esc(valorLinha($veiculo, ['obsveiculo', 'observacao'])) ?></textarea>
                </div>
            </div>
        </div>

        <?php
        $parametrosContexto = ['placa' => $placa];
        if ($matriculaCondutor !== '') {
            $parametrosContexto['matcond'] = $matriculaCondutor;
        }
        $queryContexto = http_build_query($parametrosContexto);
        ?>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-success" href="historico-vistorias-condutor.php?<?= esc($queryContexto) ?>">Histórico de Vistorias</a>
            <a class="btn btn-secondary" href="historico-placas-condutor.php?<?= esc($queryContexto) ?>">Histórico de Condutores</a>
            <a class="btn btn-primary" href="../historicoplacamanutencao.php?<?= esc(http_build_query(['placa' => $placa])) ?>">Histórico de Manutenções</a>
            <?php if ($matriculaCondutor !== ''): ?>
                <a class="btn btn-info" href="dados-condutor.php?<?= esc(http_build_query(['matcond' => $matriculaCondutor])) ?>">Dados do Condutor</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php renderRodapeAutofrota(); ?>
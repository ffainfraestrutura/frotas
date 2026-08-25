<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');

$databaseCorp = trim((string) ($autofrotaSessao['databaseCorp'] ?? ($GLOBALS['databaseCorp'] ?? '')));
if ($databaseCorp === '') {
    $databaseCorp = 'bdcorp';
}

$matriculaCondutor = valorRequisicao(['matcond', 'matcondutor', 'matricula']);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $matriculaCondutor !== '') {
    header('Location: dados-condutor-pj.php?matcond=' . urlencode($matriculaCondutor));
    exit;
}

$condutor = [];
$cnh = [];
$usuario = [];
$veiculos = [];
$erro = '';

if (!function_exists('colunaValor')) {
    function colunaValor(array $linha, array $colunas, string $padrao = ''): string
    {
        foreach ($colunas as $coluna) {
            if (array_key_exists($coluna, $linha) && trim((string) ($linha[$coluna] ?? '')) !== '') {
                return (string) $linha[$coluna];
            }
        }

        return $padrao;
    }
}

if (!function_exists('normalizarDataInput')) {
    function normalizarDataInput(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return '';
        }

        return substr($valor, 0, 10);
    }
}

if ($matriculaCondutor !== '' && isset($conn) && $conn instanceof mysqli) {
    $condutor = buscarUmaLinha($conn, "SELECT * FROM `{$databaseName}`.`tbcondutor` WHERE matricula = ? AND UPPER(TRIM(status)) = 'ATIVO' ORDER BY idtbcondutor DESC LIMIT 1", 's', [$matriculaCondutor]);
    $cnh = buscarUmaLinha($conn, "SELECT * FROM `{$databaseName}`.`tbcnh` WHERE matricula = ? LIMIT 1", 's', [$matriculaCondutor]);
    $usuario = buscarUmaLinha($conn, "SELECT * FROM `{$databaseName}`.`tbusuario` WHERE matricula = ? LIMIT 1", 's', [$matriculaCondutor]);

    $consultaVeiculos = consultaPreparada($conn, "SELECT * FROM `{$databaseName}`.`tbveiculo` WHERE matcond = ? ORDER BY placa", 's', [$matriculaCondutor]);
    $veiculos = $consultaVeiculos['linhas'];
    $erro = $consultaVeiculos['erro'];
}

$filial = colunaValor($condutor, ['filial', 'nomefilial']);
$codempresa = colunaValor($condutor, ['codempresa']);
$codfilial = colunaValor($condutor, ['codfilial']);

if ($filial === '' && $codempresa !== '' && $codfilial !== '' && isset($conn) && $conn instanceof mysqli) {
    $filialLinha = buscarUmaLinha($conn, "SELECT nome FROM `{$databaseName}`.`tbfilial` WHERE codempresa = ? AND codfilial = ? LIMIT 1", 'ss', [$codempresa, $codfilial]);
    $filial = colunaValor($filialLinha, ['nome']);
}

$statusCondutor = colunaValor($condutor, ['status']);
$placaAssociada = '';
if (count($veiculos) > 0) {
    $placaAssociada = (string) ($veiculos[0]['placa'] ?? '');
}

$docCnh = colunaValor($cnh, ['doc2']);
if ($docCnh === '') {
    $docCnh = colunaValor($cnh, ['doc1']);
}

$docCnhLink = '';
if ($docCnh !== '') {
    $docCnhNormalizado = str_replace('\\', '/', trim($docCnh));
    if (!preg_match('~^(?:https?:)?//~i', $docCnhNormalizado)) {
        $docCnhNormalizado = ltrim($docCnhNormalizado, '/');
        $baseCnh = 'autofrota/condutores/docs/cnh/';

        if (strpos($docCnhNormalizado, $baseCnh) === 0) {
            $arquivoCnh = substr($docCnhNormalizado, strlen($baseCnh));
            while (strpos($arquivoCnh, 'docs/cnh/') === 0) {
                $arquivoCnh = substr($arquivoCnh, strlen('docs/cnh/'));
            }
            $docCnhNormalizado = $baseCnh . $arquivoCnh;
        } elseif (strpos($docCnhNormalizado, 'autofrota/condutores/') !== 0) {
            while (strpos($docCnhNormalizado, 'docs/cnh/') === 0) {
                $docCnhNormalizado = substr($docCnhNormalizado, strlen('docs/cnh/'));
            }
            $docCnhNormalizado = $baseCnh . $docCnhNormalizado;
        }

        $docCnhNormalizado = '/' . ltrim($docCnhNormalizado, '/');
    }
    $docCnhLink = $docCnhNormalizado;
}

renderCabecalhoAutofrota('Dados do Condutor');
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Dados do Condutor</h1>
        </div>
        <a class="btn btn-secondary" href="listar_condutorespj.php"><i class="fa fa-arrow-left me-1"></i>Voltar</a>
    </div>

    <?php if ($matriculaCondutor === ''): ?>
        <div class="alert alert-warning">Informe a matrícula do condutor.</div>
    <?php elseif ($condutor === []): ?>
        <div class="alert alert-warning">Condutor não encontrado para a matrícula <?= esc($matriculaCondutor) ?>.</div>
    <?php else: ?>
        <div class="card card-info mb-3">
            <div class="card-header fw-semibold">Informações cadastrais</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label>Nome</label>
                        <input class="form-control" value="<?= esc(colunaValor($condutor, ['nome'])) ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label>Data de Nasc.</label>
                        <input class="form-control" value="<?= esc(normalizarDataInput(colunaValor($condutor, ['dtnasc', 'data_nascimento']))) ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label>Matrícula</label>
                        <input class="form-control" value="<?= esc(colunaValor($condutor, ['matricula'], $matriculaCondutor)) ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label>Status</label>
                        <input class="form-control" value="<?= esc($statusCondutor) ?>" readonly>
                    </div>

                    <div class="col-md-3">
                        <label>Data de admissão</label>
                        <input class="form-control" value="<?= esc(normalizarDataInput(colunaValor($condutor, ['dtadmissao', 'data_admissao']))) ?>" readonly>
                    </div>
                    <?php if (strtolower($statusCondutor) === 'demitido'): ?>
                        <div class="col-md-3">
                            <label>Data de demissão</label>
                            <input class="form-control" value="<?= esc(normalizarDataInput(colunaValor($condutor, ['dtdemissao', 'data_demissao']))) ?>" readonly>
                        </div>
                    <?php endif; ?>

                    <div class="col-md-4">
                        <label>Cargo</label>
                        <input class="form-control" value="<?= esc(colunaValor($condutor, ['cargo'])) ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label>Filial</label>
                        <input class="form-control" value="<?= esc($filial) ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label>Centro de custo</label>
                        <input class="form-control" value="<?= esc(colunaValor($condutor, ['ccusto', 'centro_custo'])) ?>" readonly>
                    </div>

                    <div class="col-md-3">
                        <label>CPF</label>
                        <input class="form-control" value="<?= esc(colunaValor($condutor, ['cpf'])) ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label>RG</label>
                        <input class="form-control" value="<?= esc(colunaValor($condutor, ['rg'])) ?>" readonly>
                    </div>

                    <div class="col-md-4">
                        <label>Telefone</label>
                        <input class="form-control" value="<?= esc(colunaValor($usuario, ['telefone', 'celular'], colunaValor($condutor, ['tel_corp', 'numres']))) ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label>Email</label>
                        <input class="form-control" value="<?= esc(colunaValor($usuario, ['email', 'e_mail'], colunaValor($condutor, ['email']))) ?>" readonly>
                    </div>

                    <div class="col-md-3">
                        <label>CNH</label>
                        <input class="form-control" value="<?= esc(colunaValor($cnh, ['numcnh', 'cnh'])) ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label>Validade CNH</label>
                        <input class="form-control" value="<?= esc(normalizarDataInput(colunaValor($cnh, ['validade', 'validadecnh']))) ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label>Categoria CNH</label>
                        <input class="form-control" value="<?= esc(colunaValor($cnh, ['categoria'])) ?>" readonly>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <?php if ($docCnh !== ''): ?>
                            <a class="btn btn-outline-secondary w-100" href="<?= esc($docCnhLink) ?>" target="_blank" rel="noopener"><i class="fa fa-file me-1"></i>Visualizar CNH</a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary w-100" type="button" disabled>Sem arquivo CNH</button>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label>Endereço</label>
                        <input class="form-control" value="<?= esc(colunaValor($condutor, ['endereco'])) ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label>Bairro</label>
                        <input class="form-control" value="<?= esc(colunaValor($condutor, ['bairro'])) ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label>Cidade</label>
                        <input class="form-control" value="<?= esc(colunaValor($condutor, ['cidade'])) ?>" readonly>
                    </div>

                    <div class="col-md-4">
                        <label>CEP</label>
                        <input class="form-control" value="<?= esc(colunaValor($condutor, ['cep'])) ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label>Placa associada</label>
                        <input class="form-control" value="<?= esc($placaAssociada) ?>" readonly>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($erro !== ''): ?>
            <div class="alert alert-danger mt-2\"><?= esc($erro) ?></div>
        <?php endif; ?>

        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-success" href="historico-vistorias-condutor.php?matcond=<?= urlencode($matriculaCondutor) ?>">Histórico de Vistorias</a>
            <a class="btn btn-secondary" href="historico-placas-condutor.php?matcond=<?= urlencode($matriculaCondutor) ?>">Histórico de Placas</a>
        </div>
    <?php endif; ?>
</div>
<?php renderRodapeAutofrota(); ?>
<!--  -->
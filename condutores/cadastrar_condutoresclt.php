<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$matriculaLogada = (string) ($autofrotaSessao['matricula'] ?? '');
$perfilLogado = (string) ($autofrotaSessao['perfil'] ?? '');
$databaseCorp = trim((string) ($autofrotaSessao['databaseCorp'] ?? ($GLOBALS['databaseCorp'] ?? '')));
if ($databaseCorp === '') {
    $databaseCorp = 'bdcorp';
}

if (!isset($conn) && isset($con) && $con instanceof mysqli) {
    $conn = $con;
}
if (!isset($databaseName) && isset($database) && is_string($database) && $database !== '') {
    $databaseName = $database;
}

$proximaMatricula = '1620001';
$ccustos = ['erro' => '', 'linhas' => []];
$cargos = ['erro' => '', 'linhas' => []];
$projetos = ['erro' => '', 'linhas' => []];

if (isset($conn) && $conn instanceof mysqli && $databaseCorp !== '') {
    $linhaMax = buscarUmaLinha($conn, "SELECT MAX(CAST(matricula AS UNSIGNED)) AS max_mat FROM `{$databaseCorp}`.`tbfuncionario` WHERE idtbempresa = 2 AND matricula REGEXP '^16[0-9]{5}$'");
    if (!empty($linhaMax['max_mat'])) {
        $proximaMatricula = str_pad(((int) $linhaMax['max_mat']) + 1, 7, '0', STR_PAD_LEFT);
    }

    $ccustos = consultaPreparada($conn, "SELECT DISTINCT UPPER(TRIM(ccusto)) AS ccusto FROM `{$databaseCorp}`.`tbfuncionario` WHERE idtbempresa = 2 AND ccusto IS NOT NULL AND TRIM(ccusto) <> '' ORDER BY ccusto");
    $cargos = consultaPreparada($conn, "SELECT DISTINCT UPPER(TRIM(cargo)) AS cargo FROM `{$databaseCorp}`.`tbfuncionario` WHERE idtbempresa = 2 AND cargo IS NOT NULL AND TRIM(cargo) <> '' ORDER BY cargo");
    $projetos = consultaPreparada($conn, "SELECT DISTINCT UPPER(TRIM(projeto)) AS projeto FROM `{$databaseCorp}`.`tbfuncionario` WHERE idtbempresa = 2 AND projeto IS NOT NULL AND TRIM(projeto) <> '' ORDER BY projeto");
}

$mensagem = valorRequisicao(['msg']);
$ufs = $conn instanceof mysqli ? buscarUfsPortal($conn) : [];

function renderizarOpcoesCondutor(array $linhas, string $campo): void
{
    $exibidos = [];
    foreach ($linhas as $linha) {
        $valor = trim((string) ($linha[$campo] ?? ''));
        if ($valor === '' || is_numeric($valor) || preg_match('/^\d+$/', $valor)) {
            continue;
        }
        $chave = function_exists('mb_strtoupper') ? mb_strtoupper($valor, 'UTF-8') : strtoupper($valor);
        if (isset($exibidos[$chave])) {
            continue;
        }
        $exibidos[$chave] = true;
        echo '<option value="' . esc($chave) . '">' . esc($chave) . '</option>';
    }
}

renderCabecalhoAutofrota('Cadastrar Condutor');
?>
<style>
    .btn-collapse {
        background: none;
        border: none;
        cursor: pointer;
        transition: transform .3s ease;
    }
    .btn-collapse.rotated {
        transform: rotate(180deg);
    }
    .card-header {
        background-color: #f8f9fa;
        font-weight: 600;
    }
    .form-container {
        margin-top: 14px;
        margin-bottom: 110px;
        padding-bottom: 20px;
    }
    .char-counter {
        font-size: 11px;
        font-weight: 500;
        margin-top: 2px;
        transition: color .3s ease;
    }
    .char-counter.complete {
        color: #28a745;
    }
    .char-counter.incomplete {
        color: #dc3545;
    }
    .char-counter.neutral {
        color: #6c757d;
    }
    .action-buttons-fixed {
        position: fixed;
        right: 30px;
        bottom: 30px;
        z-index: 1050;
        display: flex;
        gap: 15px;
        padding: 12px 18px;
        background: rgba(255, 255, 255, .98);
        border: 2px solid rgba(0, 0, 0, .05);
        border-radius: 50px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, .25);
        backdrop-filter: blur(10px);
    }
    .action-buttons-fixed .btn {
        width: 44px;
        height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all .3s ease;
    }
    .action-buttons-fixed .btn:hover {
        transform: scale(1.1);
        box-shadow: 0 4px 12px rgba(0, 0, 0, .3);
    }
</style>
<div class="container-fluid">
    <div class="mb-3 d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h1 class="h3 mb-1">Cadastrar Condutor</h1>
            <p class="text-muted mb-0">Preencha as informações pessoais, documentos, endereço e dados contratuais do condutor.</p>
        </div>
        <a href="listar_condutorespj.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-list me-1"></i>Editar Condutores
        </a>
    </div>

    <?php if ($mensagem !== ''): ?>
        <div class="alert alert-info"><?= esc($mensagem) ?></div>
    <?php endif; ?>

    <div class="form-container">
        <form id="formCondutor" method="post" action="control/processarcondutor.php" class="mb-3" novalidate>
            <p style="font-size: 10px;"><span class="text-danger">*</span> Campos obrigatórios.</p>

            <div id="dadoscadastrais">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-user me-2"></i>Informações Pessoais</h6>
                        <button type="button" data-bs-toggle="collapse" data-bs-target="#collapseInformacoesPessoais" id="botaoInformacoesPessoais" onclick="girarBotao('botaoInformacoesPessoais')" class="btn-collapse" aria-label="Recolher informações pessoais">
                            <i class="fa-solid fa-chevron-up"></i>
                        </button>
                    </div>
                    <div class="card-body collapse show" id="collapseInformacoesPessoais">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label for="nome" class="form-label">Nome Completo:<span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm text-uppercase" id="nome" name="nome" required>
                            </div>
                            <div class="col-md-3">
                                <label for="dtnasc" class="form-label">Data de Nascimento:<span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm data-validation" id="dtnasc" name="dtnasc" required>
                            </div>
                            <div class="col-md-2">
                                <label for="telefone" class="form-label">Telefone:</label>
                                <input type="text" class="form-control form-control-sm" id="telefone" name="tel_corp" inputmode="numeric" maxlength="11" pattern="[0-9]*">
                                <div class="char-counter neutral" id="telefone-counter">0/11 dígitos</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Documentos</h6>
                        <button type="button" data-bs-toggle="collapse" data-bs-target="#collapseDocumentos" id="botaoDocumentos" onclick="girarBotao('botaoDocumentos')" class="btn-collapse" aria-label="Recolher documentos">
                            <i class="fa-solid fa-chevron-up"></i>
                        </button>
                    </div>
                    <div class="card-body collapse show" id="collapseDocumentos">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="cpf" class="form-label">CPF (somente números):<span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="cpf" name="cpf" pattern="[0-9]{8,11}" inputmode="numeric" maxlength="11" minlength="8" required>
                                <div class="char-counter incomplete" id="cpf-counter">0 dígitos (8-11)</div>
                            </div>
                            <div class="col-md-3">
                                <label for="rg" class="form-label">RG (somente números):</label>
                                <input type="text" class="form-control form-control-sm" id="rg" name="rg" pattern="[0-9]*" inputmode="numeric" maxlength="11" minlength="6">
                                <div class="char-counter neutral" id="rg-counter">0 dígitos (6-11)</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-address-book me-2"></i>Informações de Endereço</h6>
                        <button type="button" data-bs-toggle="collapse" data-bs-target="#collapseEndereco" id="botaoEndereco" onclick="girarBotao('botaoEndereco')" class="btn-collapse" aria-label="Recolher informações de endereço">
                            <i class="fa-solid fa-chevron-up"></i>
                        </button>
                    </div>
                    <div class="card-body collapse show" id="collapseEndereco">
                        <div class="row g-3 mb-3">
                            <div class="col-md-2">
                                <label for="cep" class="form-label">CEP:<span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="cep" name="cep" pattern="[0-9]*" inputmode="numeric" maxlength="8" required>
                                <div class="char-counter incomplete" id="cep-counter">0/8 dígitos</div>
                            </div>
                            <div class="col-md-5">
                                <label for="endereco" class="form-label">Endereço:<span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm text-uppercase" id="endereco" name="endereco" required>
                            </div>
                            <div class="col-md-3">
                                <label for="bairro" class="form-label">Bairro:<span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm text-uppercase" id="bairro" name="bairro" required>
                            </div>
                            <div class="col-md-2">
                                <label for="estado" class="form-label">Estado (UF):<span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" name="estado" id="estado" required>
                                    <option value="">Selecione</option>
                                    <?php foreach ($ufs as $uf): ?>
                                        <option value="<?= esc($uf) ?>"><?= esc($uf) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="cidade" class="form-label">Cidade:<span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm text-uppercase" id="cidade" name="cidade" required>
                            </div>
                            <div class="col-md-4">
                                <label for="email" class="form-label">E-mail:</label>
                                <input type="email" class="form-control form-control-sm" id="email" name="email" maxlength="99">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="dadoscontratuais" class="mt-3">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-briefcase me-2"></i>Dados Contratuais</h6>
                        <button type="button" data-bs-toggle="collapse" data-bs-target="#collapseDadosContratuais" id="botaoDadosContratuais" onclick="girarBotao('botaoDadosContratuais')" class="btn-collapse" aria-label="Recolher dados contratuais">
                            <i class="fa-solid fa-chevron-up"></i>
                        </button>
                    </div>
                    <div class="card-body collapse show" id="collapseDadosContratuais">
                        <div class="row g-3 mb-3">
                            <div class="col-md-2">
                                <label for="matricula" class="form-label">Matrícula:<span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="matricula" name="matricula" value="<?= esc($proximaMatricula) ?>" inputmode="numeric" minlength="7" maxlength="7" pattern="16[0-9]{5}" readonly required style="background-color: #e9ecef;">
                                <small class="text-muted">Auto-gerada (7 dígitos)</small>
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">Situação:</label>
                                <select class="form-select form-select-sm" name="status" id="status" disabled>
                                    <option value="ATIVO" selected>Ativo</option>
                                    <option value="INATIVO">Inativo</option>
                                    <option value="AFASTADO">Afastado</option>
                                    <option value="FERIAS">Férias</option>
                                </select>
                                <input type="hidden" name="status" value="ATIVO">
                            </div>
                            <div class="col-md-2">
                                <label for="dtadmissao" class="form-label">Data Admissão:<span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm data-validation" id="dtadmissao" name="dtadmissao" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="ccusto" class="form-label">Departamento/Centro de Custo:<span class="text-danger">*</span></label>
                                <input class="form-control form-control-sm text-uppercase" name="ccusto" id="ccusto" list="listaCcusto" required>
                                <datalist id="listaCcusto"><?php renderizarOpcoesCondutor($ccustos['linhas'], 'ccusto'); ?></datalist>
                            </div>
                            <div class="col-md-3">
                                <label for="cargo" class="form-label">Cargo:<span class="text-danger">*</span></label>
                                <input class="form-control form-control-sm text-uppercase" name="cargo" id="cargo" list="listaCargo" required>
                                <datalist id="listaCargo"><?php renderizarOpcoesCondutor($cargos['linhas'], 'cargo'); ?></datalist>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="projeto" class="form-label">Projeto:</label>
                                <input class="form-control form-control-sm text-uppercase" name="projeto" id="projeto" list="listaProjeto">
                                <datalist id="listaProjeto"><?php renderizarOpcoesCondutor($projetos['linhas'], 'projeto'); ?></datalist>
                            </div>
                            <div class="col-md-2">
                                <label for="uf_trabalho" class="form-label">UF de Trabalho:<span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" name="uf_trabalho" id="uf_trabalho" required>
                                    <option value="">Selecione</option>
                                    <?php foreach ($ufs as $uf): ?>
                                        <option value="<?= esc($uf) ?>"><?= esc($uf) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="action-buttons-fixed">
            <button class="btn btn-danger rounded-circle" type="button" onclick="window.history.back()" title="Voltar" aria-label="Voltar">
                <i class="fa-solid fa-arrow-left fa-xl"></i>
            </button>
            <button class="btn btn-success rounded-circle" type="submit" form="formCondutor" title="Salvar" aria-label="Salvar">
                <i class="fa-solid fa-floppy-disk fa-xl"></i>
            </button>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const onlyDigits = ['cpf', 'rg', 'cep', 'telefone'];
    onlyDigits.forEach(function (id) {
        const field = document.getElementById(id);
        if (!field) {
            return;
        }
        field.addEventListener('input', function () {
            field.value = field.value.replace(/\D/g, '');
        });
    });

    document.querySelectorAll('input[type="text"].text-uppercase').forEach(function (field) {
        field.addEventListener('input', function () {
            field.value = field.value.toUpperCase();
        });
    });

    function updateCharCounter(inputId, counterId, maxLength, isRequired) {
        const input = document.getElementById(inputId);
        const counter = document.getElementById(counterId);
        if (!input || !counter) {
            return;
        }

        input.addEventListener('input', function () {
            const currentLength = input.value.length;

            if (inputId === 'rg') {
                if (currentLength >= 6 && currentLength <= 11) {
                    counter.textContent = currentLength + ' dígitos (✓ válido)';
                    counter.className = 'char-counter complete';
                } else if (currentLength > 0) {
                    counter.textContent = currentLength + ' dígitos (6-11 necessários)';
                    counter.className = 'char-counter incomplete';
                } else {
                    counter.textContent = '0 dígitos (6-11)';
                    counter.className = 'char-counter neutral';
                }
                return;
            }

            if (inputId === 'cpf') {
                if (currentLength >= 8 && currentLength <= 11) {
                    counter.textContent = currentLength + ' dígitos (✓ válido)';
                    counter.className = 'char-counter complete';
                } else if (currentLength > 0) {
                    counter.textContent = currentLength + ' dígitos (8-11 necessários)';
                    counter.className = 'char-counter incomplete';
                } else {
                    counter.textContent = '0 dígitos (8-11)';
                    counter.className = 'char-counter incomplete';
                }
                return;
            }

            counter.textContent = currentLength + '/' + maxLength + ' dígitos';
            if (currentLength === maxLength) {
                counter.className = 'char-counter complete';
            } else if (currentLength > 0 || isRequired) {
                counter.className = isRequired ? 'char-counter incomplete' : 'char-counter neutral';
            } else {
                counter.className = 'char-counter neutral';
            }
        });
    }

    updateCharCounter('cpf', 'cpf-counter', 11, true);
    updateCharCounter('rg', 'rg-counter', 11, false);
    updateCharCounter('cep', 'cep-counter', 8, true);
    updateCharCounter('telefone', 'telefone-counter', 11, false);

    function limparFormularioCep() {
        document.getElementById('endereco').value = '';
        document.getElementById('bairro').value = '';
        document.getElementById('cidade').value = '';
        document.getElementById('estado').value = '';
    }

    document.getElementById('cep').addEventListener('blur', function () {
        const cep = this.value.replace(/\D/g, '');
        if (cep === '') {
            limparFormularioCep();
            return;
        }
        if (!/^[0-9]{8}$/.test(cep)) {
            limparFormularioCep();
            alert('Formato de CEP inválido.');
            return;
        }

        document.getElementById('endereco').value = '...';
        document.getElementById('bairro').value = '...';
        document.getElementById('cidade').value = '...';
        document.getElementById('estado').value = '';

        fetch('https://viacep.com.br/ws/' + cep + '/json/')
            .then(function (response) { return response.json(); })
            .then(function (dados) {
                if (dados.erro) {
                    limparFormularioCep();
                    alert('CEP não encontrado.');
                    return;
                }
                document.getElementById('endereco').value = (dados.logradouro || '').toUpperCase();
                document.getElementById('bairro').value = (dados.bairro || '').toUpperCase();
                document.getElementById('cidade').value = (dados.localidade || '').toUpperCase();
                document.getElementById('estado').value = dados.uf || '';
            })
            .catch(function () {
                limparFormularioCep();
                alert('Não foi possível consultar o CEP. Preencha o endereço manualmente.');
            });
    });

    document.querySelectorAll('.data-validation').forEach(function (field) {
        field.addEventListener('blur', function () {
            validarAno(field);
        });
    });

    document.getElementById('formCondutor').addEventListener('submit', function (event) {
        const cpf = document.getElementById('cpf').value.replace(/\D/g, '');
        const rg = document.getElementById('rg').value.replace(/\D/g, '');
        const cep = document.getElementById('cep').value.replace(/\D/g, '');

        if (!this.checkValidity()) {
            event.preventDefault();
            this.reportValidity();
            return;
        }

        if (cpf.length < 8 || cpf.length > 11) {
            event.preventDefault();
            alert('CPF deve conter entre 8 e 11 dígitos!');
            document.getElementById('cpf').focus();
            return;
        }

        if (/^(.)\1+$/.test(cpf)) {
            event.preventDefault();
            alert('CPF inválido! Não é permitido CPF com todos os dígitos iguais.');
            document.getElementById('cpf').focus();
            return;
        }

        if (rg.length > 0 && (rg.length < 6 || rg.length > 11)) {
            event.preventDefault();
            alert('RG deve conter entre 6 e 11 dígitos!');
            document.getElementById('rg').focus();
            return;
        }

        if (rg.length > 0 && /^(.)\1+$/.test(rg)) {
            event.preventDefault();
            alert('RG inválido! Não é permitido RG com todos os dígitos iguais.');
            document.getElementById('rg').focus();
            return;
        }

        if (cep.length !== 8) {
            event.preventDefault();
            alert('CEP deve conter exatamente 8 dígitos!');
            document.getElementById('cep').focus();
        }
    });
});

function girarBotao(btnId) {
    const btn = document.getElementById(btnId);
    if (btn) {
        btn.classList.toggle('rotated');
    }
}

function validarAno(input) {
    const inputDate = input.value;
    if (!inputDate) {
        return;
    }
    const year = parseInt(inputDate.split('-')[0], 10);
    if (isNaN(year) || year < 1000 || year > 9999) {
        alert('Por favor, insira um ano válido.');
        input.value = '';
    }
}
</script>
<?php renderRodapeAutofrota(); ?>
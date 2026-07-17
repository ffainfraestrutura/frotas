<?php
// View de Retirada de Saldo
date_default_timezone_set('America/Sao_Paulo');
session_start();
$nome = $_SESSION['nome'];
$usuariof = $_SESSION['usuario'];
$matricula = $_SESSION['matricula'];
$perfil = $_SESSION['perfil'];
$_SESSION['perfil'] = $perfil;
$_SESSION['matricula'] = $matricula;
$_SESSION['nome'] = $nome;
$_SESSION['usuario'] = $usuariof;
require_once '../../control/conecta.php';
require_once '../../includes/autofrota_common.php';

// Busca técnicos baseado no perfil do usuário logado
$tecnicos = [];

// ==============================================
// Busca o saldo ATUAL do coordenador
// Prioridade: historico_combustivel.saldo_atual (registro mais recente)
// Fallback: tbsaldo.valor
// ==============================================
function getSaldoCoordenador($conn, $matricula, $perfil)
{
    $tabela = match ($perfil) {
        '1' => 'tbsupervisor',
        '2' => 'tbcoord',
        '3' => 'tbgerente',
        '4' => 'tbcoord',
        '10' => 'tbdiretor',
    };

    // 1ª tentativa: historico_combustivel (registro mais recente)
    $sql_hist = "SELECT valor_atual 
                 FROM historico_combustivel 
                 WHERE matricula = ? 
                 ORDER BY id DESC 
                 LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql_hist);
    mysqli_stmt_bind_param($stmt, 's', $matricula);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row && isset($row['valor_atual']) && $row['valor_atual'] !== null) {
        return [
            'saldo' => floatval($row['valor_atual']),
            'fonte' => 'historico_combustivel'
        ];
    }

    // 2ª tentativa: fallback para tbsaldo.valor
    $sql_fallback = "SELECT valor 
                      FROM bdcorp.$tabela 
                      WHERE matricula = ? 
                      LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql_fallback);
    mysqli_stmt_bind_param($stmt, 's', $matricula);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row && isset($row['valor'])) {
        return [
            'saldo' => floatval($row['valor']),
            'fonte' => 'tbsaldo'
        ];
    }

    return [
        'saldo' => 0,
        'fonte' => 'indisponivel'
    ];
}

// Busca o saldo do coordenador logado (apenas perfil Coordenador)
$saldo_coordenador = 0;
$fonte_saldo_coordenador = '';
$dados_saldo_coord = getSaldoCoordenador($conn, $_SESSION['matricula'], $_SESSION['perfil']);
$saldo_coordenador = $dados_saldo_coord['saldo'];
$fonte_saldo_coordenador = $dados_saldo_coord['fonte'];


// Define a consulta baseada no perfil
switch ($_SESSION['perfil']) {
    case 0: // Técnico - Não pode retirar saldo
        break;

    case 2: // Coordenador - Busca apenas técnicos vinculados a ele
        $sql = "SELECT 
                    sup.idtbsupervisor,
                    u.nome,
                    u.matricula,
                    u.perfil,
                    vei.placa
                FROM
                    bdcorp.tbcoord coord
                    JOIN bdcorp.tbsupervisor sup ON sup.idtbcoordenador = coord.idtbcoordenador
                    JOIN bdcorp.tbusuario u ON sup.idtbsupervisor = u.idtbsupervisor
                    JOIN bdcorp.tbfuncionario tec ON u.matricula = tec.matricula
                    JOIN tbsaldo sal ON sal.matricula = u.matricula
                    LEFT JOIN tbveiculo vei ON vei.matcond = u.matricula
                WHERE
                    coord.matricula = ?
                    AND tec.status != 'demitido'
                    AND u.perfil = 0
                ORDER BY u.nome ASC";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $_SESSION['matricula']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $tecnicos = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        break;

    case 3: // Gerente - Busca técnicos de todos os coordenadores abaixo dele
        $sql = "SELECT 
                    sup.idtbsupervisor,
                    u.nome,
                    u.matricula,
                    u.perfil,
                    vei.placa,
                    coord.matricula as matricula_coordenador
                FROM
                    bdcorp.tbgerente ger
                    JOIN bdcorp.tbcoord coord ON coord.idtbgerente = ger.idtbgerente
                    JOIN bdcorp.tbsupervisor sup ON sup.idtbcoordenador = coord.idtbcoordenador
                    JOIN bdcorp.tbusuario u ON sup.idtbsupervisor = u.idtbsupervisor
                    JOIN bdcorp.tbfuncionario tec ON u.matricula = tec.matricula
                    JOIN tbsaldo sal ON sal.matricula = u.matricula
                    LEFT JOIN tbveiculo vei ON vei.matcond = u.matricula
                WHERE
                    ger.matricula = ?
                    AND tec.status != 'demitido'
                    AND u.perfil = 0
                ORDER BY u.nome ASC";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $_SESSION['matricula']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $tecnicos = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        break;

    case 10: // Diretor - Busca todos os técnicos
        $sql = "SELECT 
                    sup.idtbsupervisor,
                    u.nome,
                    u.matricula,
                    u.perfil,
                    vei.placa,
                    coord.matricula as matricula_coordenador
                FROM
                    bdcorp.tbdiretor dir
                    JOIN bdcorp.tbgerente ger ON ger.idtbdiretor = dir.id
                    JOIN bdcorp.tbcoord coord ON coord.idtbgerente = ger.idtbgerente
                    JOIN bdcorp.tbsupervisor sup ON sup.idtbcoordenador = coord.idtbcoordenador
                    JOIN bdcorp.tbusuario u ON sup.idtbsupervisor = u.idtbsupervisor
                    JOIN bdcorp.tbfuncionario tec ON u.matricula = tec.matricula
                    JOIN tbsaldo sal ON sal.matricula = u.matricula
                    LEFT JOIN tbveiculo vei ON vei.matcond = u.matricula
                WHERE
                    dir.matricula = ?
                    AND tec.status != 'demitido'
                    AND u.perfil = 0
                ORDER BY u.nome ASC";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $_SESSION['matricula']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $tecnicos = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        break;

    case 4:
        $sql = "SELECT 
                    sup.idtbsupervisor,
                    u.nome,
                    u.matricula,
                    u.perfil,
                    sal.totalextra,
                    sal.idtbsaldo,
                    sal.sldprjtd,
                    sal.orcsemanal,
                    vei.placa
                FROM
                    bdcorp.tbsupervisor sup
                    JOIN bdcorp.tbusuario u ON sup.idtbsupervisor = u.idtbsupervisor
                    JOIN bdcorp.tbfuncionario tec ON u.matricula = tec.matricula
                    JOIN tbsaldo sal ON sal.matricula = u.matricula
                    LEFT JOIN tbveiculo vei ON vei.matcond = u.matricula
                WHERE
                    tec.status != 'demitido'
                    AND u.perfil = 0
                ORDER BY u.nome ASC";

        $result = mysqli_query($conn, $sql);
        $tecnicos = mysqli_fetch_all($result, MYSQLI_ASSOC);
        break;
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Retirada de Saldo - Portal FFA</title>
    <link rel="icon" type="image/png" href="../src/images/favicon.png" />
    <link href="../src/css/styles.css" rel="stylesheet" />
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-maskmoney@3.0.2/dist/jquery.maskmoney.min.js"></script>

    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link href="src/css/styles.css" rel="stylesheet" />
    <style>
        html {
            zoom: 0.8;
        }

        .saldo-display {
            font-size: 1.1em;
            font-weight: bold;
        }

        .saldo-positivo {
            color: #28a745;
        }

        .saldo-negativo {
            color: #dc3545;
        }

        .saldo-zero {
            color: #6c757d;
        }

        .table-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .valor-retirada {
            max-width: 200px;
            display: inline-block;
        }

        .btn-retirar {
            min-width: 100px;
        }

        .acoes-coluna {
            min-width: 180px;
        }

        .row-selecionada {
            background-color: #fff3cd !important;
        }

        .info-saldo-total {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            margin-bottom: 20px;
        }

        .info-saldo-coordenador {
            background: #d4edda;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #28a745;
            margin-bottom: 20px;
        }

        /* Estilo da badge de placa */
        .badge-placa {
            background-color: #6c757d;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.72rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
        }

        .badge-placa i {
            font-size: 0.65rem;
            flex-shrink: 0;
        }

        /* Ajuste da coluna da placa */
        .table td:nth-child(4) {
            max-width: 120px;
            min-width: 70px;
            width: 120px;
        }

        /* Para telas menores */
        @media (max-width: 768px) {
            .badge-placa {
                font-size: 0.65rem;
                padding: 2px 6px;
            }

            .table td:nth-child(4) {
                max-width: 80px;
                min-width: 60px;
                width: 80px;
            }
        }

        .total-geral {
            font-size: 1.2em;
            font-weight: bold;
            color: #17a2b8;
        }

        .legenda-acoes {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }

        .fonte-dado {
            font-size: 0.7rem;
            color: #6c757d;
        }
    </style>
</head>

<body class="sb-nav-fixed">
    <?php autofrotaMenu(); ?>

    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid px-4">
                <h1 class="mt-4">Remoção de Saldo</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item active">Realize retiradas de saldo dos colaboradores</li>
                </ol>

                <?php if ($_SESSION['perfil'] != 4): ?>
                    <div class="info-saldo-coordenador">
                        <i class="fas fa-wallet"></i>
                        <strong>Saldo atual:</strong>
                        <span
                            class="saldo-display <?= $saldo_coordenador > 0 ? 'saldo-positivo' : ($saldo_coordenador < 0 ? 'saldo-negativo' : 'saldo-zero') ?>">
                            R$ <?= number_format($saldo_coordenador, 2, ',', '.') ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php
                if (isset($_GET['success'])) {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> ' . htmlspecialchars($_GET['success']) . '
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>';
                }

                if (isset($_GET['error'])) {
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($_GET['error']) . '
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>';
                }
                ?>

                <?php if ($_SESSION['perfil'] == 0): ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Acesso Restrito:</strong> Seu perfil (Técnico) não permite realizar retiradas de saldo.
                    </div>
                <?php else: ?>
                    <!-- Tabela de Técnicos -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-table me-2"></i>
                            Colaboradores Disponíveis para Retirada
                        </div>
                        <div class="card-body table-container">
                            <div class="table-responsive">
                                <table id="tecnicosTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Matrícula</th>
                                            <th>Nome</th>
                                            <th>Placa</th>
                                            <th>Saldo (R$)</th>
                                            <th>Histórico de Combustível</th>
                                            <th class="acoes-coluna">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($tecnicos)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">
                                                    <i class="fas fa-info-circle"></i> Nenhum colaborador encontrado para
                                                    retirada de saldo.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($tecnicos as $tecnico): ?>
                                                <tr id="row-<?= htmlspecialchars($tecnico['matricula']) ?>">
                                                    <td><strong><?= htmlspecialchars($tecnico['matricula']) ?></strong></td>
                                                    <td><?= htmlspecialchars($tecnico['nome']) ?></td>
                                                    <td>
                                                        <?php if (!empty($tecnico['placa'])): ?>
                                                            <span class="badge-placa"><i class="fas fa-car"></i>
                                                                <?= htmlspecialchars($tecnico['placa']) ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted"><i class="fas fa-minus-circle"></i></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="saldo-display">
                                                            <i class="fas fa-spinner fa-spin"></i>
                                                        </span>
                                                        <input type="hidden" class="saldo-atual" value="0">
                                                        <div class="fonte-dado"></div>
                                                    </td>
                                                    <td>
                                                        <a class="btn btn-outline-secondary btn-sm"
                                                            href="../historico_combustivel.php?matricula=<?= $tecnico['matricula'] ?>">
                                                            <i class="fa-solid fa-eye"></i>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <form action="../../control/remover_saldo.php" method="post"
                                                            class="form-retirada" onsubmit="return confirmarRetirada(this)">
                                                            <input type="hidden" name="matricula_tecnico"
                                                                value="<?= htmlspecialchars($tecnico['matricula']) ?>">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text">R$</span>
                                                                <input type="text" name="valor_retirada"
                                                                    class="form-control valor-retirada numerico" placeholder="0,00"
                                                                    required data-saldo="0">
                                                                <button type="submit" class="btn btn-danger btn-retirar" disabled>
                                                                    <i class="fas fa-minus-circle"></i> Retirar
                                                                </button>
                                                            </div>
                                                            <small class="text-muted disponivel-texto" style="font-size: 0.75rem;">
                                                                Disponível: R$ 0,00
                                                            </small>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../src/js/scripts.js"></script>
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

    <script>
        $(document).ready(function () {
            // Inicializar DataTable
            var table = $('#tecnicosTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
                },
                order: [[4, 'desc']],
                pageLength: 25,
                columnDefs: [
                    { targets: 4, type: 'num' },
                    { targets: 5, orderable: false }
                ]
            });

            // ==============================================
            // FUNÇÃO PARA BUSCAR SALDO ATUAL EM TEMPO REAL
            // ==============================================
            function buscarSaldoAtual(matricula, elemento) {
                if (!matricula) return;

                $(elemento).closest('tr').find('.saldo-display').html('<i class="fas fa-spinner fa-spin"></i>');

                $.ajax({
                    url: '../get_saldo.php',
                    type: 'POST',
                    data: { matricula: matricula },
                    dataType: 'json',
                    cache: false,
                    timeout: 10000,
                    success: function (response) {
                        if (response.success) {
                            const saldo = response.saldo_atual;
                            const saldoClass = saldo > 0 ? 'saldo-positivo' : (saldo < 0 ? 'saldo-negativo' : 'saldo-zero');

                            $(elemento).closest('tr').find('.saldo-display')
                                .html('<span class="saldo-display ' + saldoClass + '">R$ ' +
                                    saldo.toFixed(2).replace('.', ',') + '</span>');

                            $(elemento).closest('tr').find('.saldo-atual').val(saldo);
                            $(elemento).closest('tr').find('.valor-retirada').data('saldo', saldo);

                            $(elemento).closest('tr').find('.disponivel-texto')
                                .text('Disponível: R$ ' + saldo.toFixed(2).replace('.', ','));

                            const fonte = response.fonte || 'tbsaldo';

                            // Botão permanece desabilitado até validação do valor digitado
                            $(elemento).closest('tr').find('.btn-retirar').prop('disabled', true);

                        } else {
                            $(elemento).closest('tr').find('.saldo-display')
                                .html('<span class="text-danger">Erro ao carregar</span>');
                            console.error('get_saldo.php retornou success=false para matrícula ' + matricula + ':', response.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Erro na requisição get_saldo.php (matrícula ' + matricula + '):', status, error, xhr.responseText);
                        $(elemento).closest('tr').find('.saldo-display')
                            .html('<span class="text-danger">Erro na requisição</span>');
                    }
                });
            }

            // ==============================================
            // ATUALIZA SALDO AO CARREGAR A PÁGINA
            // (Executa antes do maskMoney para nunca ser bloqueado por erros de terceiros)
            // ==============================================
            $('tr').each(function () {
                const matricula = $(this).find('input[name="matricula_tecnico"]').val();
                if (matricula) {
                    buscarSaldoAtual(matricula, this);
                }
            });

            // ==============================================
            // Máscara para campos de valor (isolado em try/catch)
            // ==============================================
            try {
                if (typeof $.fn.maskMoney === 'function') {
                    $('.numerico').maskMoney({
                        prefix: '',
                        allowNegative: false,
                        thousands: '.',
                        decimal: ',',
                        affixesStay: false
                    });
                } else {
                    console.warn('maskMoney não carregado — campos de valor funcionarão sem máscara.');
                }
            } catch (e) {
                console.error('Falha ao inicializar maskMoney:', e);
            }

            // ==============================================
            // VALIDAÇÃO EM TEMPO REAL
            // ==============================================
            $('.valor-retirada').on('change keyup', function () {
                const valor = $(this).val();
                const saldo = parseFloat($(this).data('saldo')) || 0;
                const valorNum = parseFloat(valor.replace(/\./g, '').replace(',', '.'));
                const btn = $(this).closest('.input-group').find('.btn-retirar');
                const inputGroup = $(this).closest('.input-group');

                if (!valor || valor === '') {
                    btn.prop('disabled', true);
                    btn.removeClass('btn-success btn-danger');
                    $(this).css('border-color', '');
                    inputGroup.find('.input-group-text').css('background-color', '');
                    return;
                }

                if (isNaN(valorNum) || valorNum <= 0) {
                    btn.prop('disabled', true);
                    btn.removeClass('btn-success btn-danger');
                    $(this).css('border-color', '#dc3545');
                    inputGroup.find('.input-group-text').css('background-color', '#f8d7da');
                    return;
                }

                if (valorNum > saldo) {
                    btn.addClass('btn-danger').removeClass('btn-success');
                    btn.prop('disabled', true);
                    $(this).css('border-color', '#dc3545');
                    inputGroup.find('.input-group-text').css('background-color', '#f8d7da');
                } else {
                    btn.removeClass('btn-danger').addClass('btn-success');
                    btn.prop('disabled', false);
                    $(this).css('border-color', '#28a745');
                    inputGroup.find('.input-group-text').css('background-color', '#d4edda');
                }
            });

            // Destacar linha ao focar no input
            $('.valor-retirada').on('focus', function () {
                $(this).closest('tr').addClass('row-selecionada');
            }).on('blur', function () {
                $(this).closest('tr').removeClass('row-selecionada');
            });

            // ==============================================
            // ATUALIZAR SALDO AO MUDAR DE PÁGINA NA DATATABLE
            // ==============================================
            table.on('draw', function () {
                $('tr').each(function () {
                    const matricula = $(this).find('input[name="matricula_tecnico"]').val();
                    if (matricula && !$(this).find('.saldo-display').hasClass('saldo-positivo') &&
                        !$(this).find('.saldo-display').hasClass('saldo-negativo') &&
                        !$(this).find('.saldo-display').hasClass('saldo-zero')) {
                        buscarSaldoAtual(matricula, this);
                    }
                });
            });
        });

        // ==============================================
        // FUNÇÃO PARA CONFIRMAR RETIRADA
        // ==============================================
        window.confirmarRetirada = function (form) {
            const valor = $(form).find('input[name="valor_retirada"]').val();
            const nome = $(form).closest('tr').find('td:eq(1)').text().trim();
            const matricula = $(form).closest('tr').find('td:eq(0)').text().trim();
            const saldoAtual = parseFloat($(form).closest('tr').find('.saldo-atual').val()) || 0;

            if (!valor || valor === '0,00') {
                alert('Por favor, informe o valor a ser retirado.');
                return false;
            }

            const valorNum = parseFloat(valor.replace(/\./g, '').replace(',', '.'));
            if (isNaN(valorNum) || valorNum <= 0) {
                alert('O valor deve ser maior que zero.');
                return false;
            }

            if (valorNum > saldoAtual) {
                alert('Saldo insuficiente!\nSaldo atual: R$ ' + saldoAtual.toFixed(2).replace('.', ','));
                return false;
            }

            const novoSaldo = saldoAtual - valorNum;
            const msg = 'Confirmar retirada de R$ ' + valor + ' do saldo do colaborador ' + nome + ' (Matr: ' + matricula + ')?\n\n' +
                'Saldo atual: R$ ' + saldoAtual.toFixed(2).replace('.', ',') + '\n' +
                'Novo saldo: R$ ' + novoSaldo.toFixed(2).replace('.', ',') + '\n\n' +
                'O valor será adicionado ao saldo (orcrecebido) do coordenador responsável.\n' +
                'Histórico: acao=remocao | operacao=retirada';

            return confirm(msg);
        };
    </script>
</body>

</html>
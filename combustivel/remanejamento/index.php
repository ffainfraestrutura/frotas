<?php
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

// Busca técnicos para o select
$tecnicos = [];
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

// // Busca coordenadores para o select (apenas se perfil 4)
// $coordenadores = [];
// if ($_SESSION['perfil'] == 4) {
//     $sql_coord = "SELECT 
//                     u.matricula,
//                     u.nome
//                   FROM 
//                     tbusuario u
//                   WHERE 
//                     u.perfil = 2
//                   ORDER BY u.nome ASC";
    
//     $result_coord = mysqli_query($conn, $sql_coord);
//     $coordenadores = mysqli_fetch_all($result_coord, MYSQLI_ASSOC);
// }
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Remanejamento de Saldo - Portal FFA</title>
    <link rel="icon" type="image/png" href="../src/images/favicon.png" />
    <link href="../src/css/styles.css" rel="stylesheet" />
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="http://plentz.github.io/jquery-maskmoney/javascripts/jquery.maskMoney.min.js"></script>

    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link href="src/css/styles.css" rel="stylesheet" />
    <style>
        .saldo-info {
            font-size: 1.1em;
            font-weight: bold;
            color: #007bff;
            margin-top: 5px;
            min-height: 25px;
        }

        .form-section {
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .coordenador-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #ffc107;
            margin-bottom: 20px;
        }
        
        .coordenador-section label {
            font-weight: bold;
            color: #856404;
        }
    </style>
</head>

<body class="sb-nav-fixed">
    <?php autofrotaMenu(); ?>

    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid px-4">
                <h1 class="mt-4">Remanejamento de Saldo</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item active">Transfira saldo entre colaboradores</li>
                </ol>

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
                
                <div class="card mb-4">
                    <div class="card-header"><i class="fas fa-exchange-alt"></i> Realizar Transferência</div>
                    <div class="card-body">
                        <form action="../../control/remanejar_saldo.php" method="post" id="transferForm">
                            <input type="hidden" name="matricula_autor" id="matricula_autor" value="<?= $matricula ?>">                         
                            <div class="row">
                                <!-- Coluna de Origem -->
                                <div class="col-md-5">
                                    <div class="form-section">
                                        <h4><i class="fas fa-arrow-up text-danger"></i> Origem (Retirar de)</h4>
                                        <label for="matricula_origem" class="form-label">Colaborador:</label>
                                        <select name="matricula_origem" id="matricula_origem" class="form-select"
                                            required>
                                            <option value="">-- Selecione um colaborador --</option>
                                            <?php foreach ($tecnicos as $tecnico): ?>
                                                <option value="<?php echo htmlspecialchars($tecnico['matricula']); ?>">
                                                    <?php echo htmlspecialchars($tecnico['nome']); ?>
                                                    (<?php echo htmlspecialchars($tecnico['matricula']); ?>)
                                                    <?php if (!empty($tecnico['placa'])): ?>
                                                        - Placa: <?php echo htmlspecialchars($tecnico['placa']); ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="saldo-info" id="saldo_origem">Saldo: R$ 0,00</div>
                                        <input type="hidden" name="saldo_numerico_origem" id="saldo_numerico_origem"
                                            value="0">
                                        <input type="hidden" name="placa_origem" id="placa_origem" value="">
                                        <input type="hidden" name="codempresa_origem" id="codempresa_origem" value="">
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex flex-column align-items-center justify-content-center">
                                    <label for="valor" id="valor-label" class="form-label fw-bold">Valor a Transferir
                                        (R$)</label>
                                    <input type="text" name="valor" id="valor"
                                        class="form-control form-control-lg text-center numerico" 
                                        placeholder="0,00" required>
                                    <i id="arrow" class="fas fa-arrow-right fa-2x mt-3 text-success"></i>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-section">
                                        <h4><i class="fas fa-arrow-down text-success"></i> Destino (Adicionar para)</h4>
                                        <label for="matricula_destino" class="form-label">Colaborador:</label>
                                        <select name="matricula_destino" id="matricula_destino" class="form-select"
                                            required>
                                            <option value="">-- Selecione um colaborador --</option>
                                            <?php foreach ($tecnicos as $tecnico): ?>
                                                <option value="<?php echo htmlspecialchars($tecnico['matricula']); ?>">
                                                    <?php echo htmlspecialchars($tecnico['nome']); ?>
                                                    (<?php echo htmlspecialchars($tecnico['matricula']); ?>)
                                                    <?php if (!empty($tecnico['placa'])): ?>
                                                        - Placa: <?php echo htmlspecialchars($tecnico['placa']); ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="saldo-info" id="saldo_destino">Saldo: R$ 0,00</div>
                                        <input type="hidden" name="saldo_numerico_destino" id="saldo_numerico_destino"
                                            value="0">
                                        <input type="hidden" name="placa_destino" id="placa_destino" value="">
                                        <input type="hidden" name="codempresa_destino" id="codempresa_destino" value="">
                                    </div>
                                </div>
                            </div>
                            <p class="text-danger h5 text-center" id="error_text"></p>
                            <div class="text-center mt-4">
                                <button type="submit" id="transfer-button" class="btn btn-primary btn-lg"><i
                                        class="fas fa-check-circle"></i>
                                    Confirmar Transferência</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
        <?php // include "footer.php"; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../src/js/scripts.js"></script>
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js"></script>
    <script>
        $(document).ready(function () {
            let valorMaximo = null;
            const $valor = $("#valor");
            const $errorText = $("#error_text");
            const $valorLabel = $("#valor-label");
            const $transferButton = $("#transfer-button");
            const $arrow = $("#arrow");
            const $saldoOrigem = $("#saldo_numerico_origem");
            const $saldoDestino = $("#saldo_numerico_destino");
            const $matriculaOrigem = $("#matricula_origem");
            const $matriculaDestino = $("#matricula_destino");
            const $matriculaCoordenador = $("#matricula_coordenador");

            // botão inicia desabilitado
            $transferButton.prop("disabled", true);

            function validarFormulario() {
                const saldoOrigemVal = $saldoOrigem.val();
                const saldoDestinoVal = $saldoDestino.val();
                const valorDigitado = parseFloat(($valor.val() || "0").replace(",", "."));

                // Verifica se coordenador foi selecionado (apenas para perfil 4)
                const coordenadorValido = $matriculaCoordenador.length === 0 || $matriculaCoordenador.val() !== "";

                const origemValida = saldoOrigemVal !== "" && saldoOrigemVal !== null && !isNaN(saldoOrigemVal);
                const destinoValida = saldoDestinoVal !== "" && saldoDestinoVal !== null && !isNaN(saldoDestinoVal);
                const valorPositivo = valorDigitado > 0;
                const saldoSuficiente = valorMaximo !== null && valorDigitado <= valorMaximo;
                const origemDiferenteDestino = ($matriculaOrigem.val() && $matriculaDestino.val()) && $matriculaOrigem.val() !== $matriculaDestino.val();

                let erro = "";
                if (!coordenadorValido && $matriculaCoordenador.length > 0) {
                    erro = "Selecione um coordenador responsável";
                } else if (!origemDiferenteDestino && ($matriculaOrigem.val() && $matriculaDestino.val())) {
                    erro = "Origem e destino não podem ser iguais";
                } else if (!saldoSuficiente) {
                    erro = valorMaximo == null
                        ? "Erro: saldo de Origem não carregado"
                        : "Erro: valor a transferir maior que o saldo de Origem";
                }

                const invalido = !(origemValida && destinoValida && valorPositivo && saldoSuficiente && origemDiferenteDestino && coordenadorValido);

                $errorText.text(erro);
                $valor.css("border-color", invalido ? "#ff0000" : "");
                $valorLabel.css("color", invalido ? "#ff0000" : "");
                $("#arrow").removeClass('text-danger text-success');
                $("#arrow").addClass(invalido ? 'text-danger' : 'text-success');

                $transferButton.prop("disabled", invalido);
            }

            function atualizarSaldo(matricula, saldoId, saldoHiddenId, placaId, codEmpresaID) {
                if (matricula) {
                    if (saldoId === "saldo_origem") valorMaximo = null;
                    $.ajax({
                        url: '../get_saldo.php',
                        type: 'POST',
                        data: { matricula: matricula },
                        dataType: 'json',
                        cache: false,
                        success: function (response) {
                            let dados = {};

                            if (Array.isArray(response)) {
                                dados = response[0] || {};
                            } else if (typeof response === 'object') {
                                dados = response;
                            } else {
                                console.error('Formato não reconhecido:', response);
                                $('#' + saldoId).text('Erro: formato inválido');
                                validarFormulario();
                                return;
                            }

                            let saldo = dados.saldo_atual || dados.saldo_numerico || dados.saldo || 0;
                            saldo = parseFloat(saldo) || 0;

                            let saldoFormatado = 'R$ ' + saldo.toFixed(2).replace('.', ',');

                            $('#' + saldoId).text('Saldo: ' + saldoFormatado);
                            $('#' + saldoHiddenId).val(saldo);
                            $('#' + placaId).val(dados.placa || '');
                            $('#' + codEmpresaID).val(dados.codempresa || '');

                            if (saldoId === "saldo_origem") {
                                valorMaximo = saldo;
                            }

                            validarFormulario();
                        },
                        error: function (xhr, status, error) {
                            console.error('Erro na requisição:', error);
                            $('#' + saldoId).text('Erro na requisição');
                            $('#' + saldoHiddenId).val("");
                            $('#' + placaId).val("");
                            $('#' + codEmpresaID).val("");
                            if (saldoId === "saldo_origem") {
                                valorMaximo = null;
                            }
                            validarFormulario();
                        }
                    });
                } else {
                    $('#' + saldoId).text('Saldo: R$ 0,00');
                    $('#' + saldoHiddenId).val("");
                    $('#' + placaId).val("");
                    $('#' + codEmpresaID).val("");
                    if (saldoId === "saldo_origem") {
                        valorMaximo = null;
                    }
                    validarFormulario();
                }
            }

            // eventos
            $matriculaOrigem.on('change', function () {
                atualizarSaldo($(this).val(), 'saldo_origem', 'saldo_numerico_origem', 'placa_origem', 'codempresa_origem');
            });

            $matriculaDestino.on('change', function () {
                atualizarSaldo($(this).val(), 'saldo_destino', 'saldo_numerico_destino', 'placa_destino', 'codempresa_destino');
            });

            // Validação do coordenador (apenas se existir)
            if ($matriculaCoordenador.length > 0) {
                $matriculaCoordenador.on('change', validarFormulario);
            }

            $valor.on("input", validarFormulario);
        });
    </script>
</body>

</html>
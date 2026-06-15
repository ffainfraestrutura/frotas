<?php
require_once __DIR__ . '/../includes/autofrota_common.php';
$autofrotaSessao = autofrotaInit();
include "../func/funcoes.php";
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Função de debug
function debugPrint($mensagem, $dados = null)
{
    $logFile = '../debug_editar_multa_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMsg = "[$timestamp] " . $mensagem;
    if ($dados !== null) {
        $logMsg .= " - " . print_r($dados, true);
    }
    $logMsg .= PHP_EOL;
    file_put_contents($logFile, $logMsg, FILE_APPEND);
}

debugPrint("=== INÍCIO DA PÁGINA editarMulta.php ===");
debugPrint("GET recebido", $_GET);
debugPrint("SESSION", $_SESSION);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

debugPrint("ID processado: $id");

if (!$id) {
    debugPrint("ERRO: ID inválido ou não informado");
    echo "<div class='alert alert-danger'>ID inválido ou não informado.</div>";
    exit;
}

try {
    debugPrint("Preparando consulta SQL");

    $sql = "
            SELECT
                m.placa,
                m.autoinfracao,
                m.codigom,
                m.descricaoinfra,
                m.etapa,
                m.orgao,
                m.datalimitecond,
                m.valtotal,
                t.nome,
                t.matricula,
                t.gravidade,
                t.tramite,
                t.locadora,
                t.dtcons,
                t.dtinfra,
                t.parecer,
                t.parecerpor,
                t.parecerdp,
                t.parecerpordp,
                t.dtdesconto,
                t.idtbmovidatramite,
                f.status,
                f.ccusto,
                f.rg,
                c.numcnh AS cnh,
                f.cpf
            FROM tbmulta m
            LEFT JOIN tbmovidatramite t
                ON t.placa = m.placa
               AND t.autoinfra = m.autoinfracao
            LEFT JOIN bdaniel.tbfuncionario f ON f.matricula = t.matricula
            LEFT JOIN tbcnh c ON c.matricula = t.matricula
            WHERE t.idtbmovidatramite = ?";

    debugPrint("SQL: $sql");
    debugPrint("Parâmetro ID: $id");

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new Exception("Erro ao preparar statement: " . mysqli_error($conn));
    }

    debugPrint("Statement preparado com sucesso");

    mysqli_stmt_bind_param($stmt, 'i', $id);
    debugPrint("Parâmetro vinculado");

    $execResult = mysqli_stmt_execute($stmt);
    if (!$execResult) {
        throw new Exception("Erro ao executar query: " . mysqli_stmt_error($stmt));
    }

    debugPrint("Query executada com sucesso");

    $res = mysqli_stmt_get_result($stmt);
    if (!$res) {
        throw new Exception("Erro ao obter resultado: " . mysqli_error($conn));
    }

    $dados = mysqli_fetch_assoc($res);
    debugPrint("Dados obtidos:", $dados);

    if (!$dados) {
        throw new Exception("Nenhum dado encontrado para o ID: $id. Verifique se existe registro em tbmovidatramite com este ID.");
    }

    debugPrint("Processando dados da multa");
    $valorTotal = floatval($dados['valtotal']);
    $quantidadeParcelas = 1;
    $valorParcela = $quantidadeParcelas > 0 ? $valorTotal / $quantidadeParcelas : $valorTotal;

    debugPrint("Valor total: $valorTotal, Valor parcela: $valorParcela");

} catch (Exception $e) {
    debugPrint("EXCEÇÃO CAPTURADA: " . $e->getMessage());
    debugPrint("Stack trace: " . $e->getTraceAsString());

    error_log($e->getMessage());
    $dados = null;
    $erro = $e->getMessage();
}

function formatarData($data)
{
    if ($data && $data != '0000-00-00' && $data != '0000-00-00 00:00:00') {
        try {
            return date('d/m/Y', strtotime($data));
        } catch (Exception $e) {
            return 'Data inválida';
        }
    }
    return 'Não informada';
}

function formatarValor($valor)
{
    return 'R$ ' . number_format(floatval($valor), 2, ',', '.');
}

debugPrint("Verificando locadora para placa");
$placa = $dados['placa'] ?? '';

if ($placa) {
    $sql2 = "SELECT idlocador, tipoposse FROM tbveiculo WHERE placa = ?";
    debugPrint("SQL veículo: $sql2, Placa: $placa");

    $stmt2 = mysqli_prepare($conn, $sql2);
    if ($stmt2) {
        mysqli_stmt_bind_param($stmt2, 's', $placa);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);
        $row2 = mysqli_fetch_assoc($res2);
        debugPrint("Dados do veículo:", $row2);
    } else {
        debugPrint("Erro ao preparar consulta do veículo: " . mysqli_error($conn));
        $row2 = null;
    }
} else {
    debugPrint("Placa não disponível nos dados");
    $row2 = null;
}

$locadora = $row2['idlocador'] ?? null;
$tipoposse = $row2['tipoposse'] ?? '';

debugPrint("Locadora: $locadora, Tipo posse: $tipoposse");

if ($locadora == '2' || $locadora == '9' || $locadora == '16' || $locadora == '17' || $locadora == '19') {
    $recibo = 'recibomovida';
    $recibos = 'recibomovida2';
} elseif ($locadora == '3' || $locadora == '4' || $locadora == '14' || $locadora == '15') {
    $recibo = 'recibolocaliza';
    $recibos = 'recibolocaliza2';
} elseif ($locadora == '8') {
    $recibo = 'recibounidas';
    $recibos = 'recibounidas2';
} elseif ($locadora == '6') {
    $recibo = 'reciboleven';
    $recibos = 'reciboleven2';
} else {
    $recibo = 'semrecibo';
    $recibos = 'recibopdf2';
}

debugPrint("Recibos definidos: recibo=$recibo, recibos=$recibos");

// Matrícula do autor logado (sessão)
$mat_autor = $_SESSION['matricula'] ?? '';
debugPrint("Matrícula autor: $mat_autor");

debugPrint("=== FIM DO PROCESSAMENTO INICIAL ===");

// Verifica mensagem de upload
$uploadMensagem = '';
$uploadTipo = '';
if (isset($_GET['upload'])) {
    if ($_GET['upload'] == 'sucesso') {
        $uploadMensagem = 'Arquivo enviado com sucesso!';
        $uploadTipo = 'success';
    } elseif ($_GET['upload'] == 'erro') {
        $uploadMensagem = isset($_GET['msg']) ? urldecode($_GET['msg']) : 'Erro ao enviar o arquivo.';
        $uploadTipo = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Trâmite 02 - Imprimir Recibo DP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../src/autofrota-botoes.css">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }

            .card {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .btn {
                display: none !important;
            }

            body {
                padding: 0;
                margin: 0;
            }

            .container {
                max-width: 100% !important;
                padding: 0 !important;
            }
        }

        a {
            text-decoration: none;
            color: black;
        }

        .fa-arrow-left {
            background-color: #fff;
            border-radius: 50%;
        }

        .recibo-item {
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
        }

        .assinatura {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #000;
            width: 300px;
            text-align: center;
        }

        /* Debug panel */
        .debug-panel {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 12px;
            display: none;
        }

        .debug-panel.visible {
            display: block;
        }
        
        .btn-upload {
            background-color: #28a745;
            transition: all 0.3s ease;
        }
        
        .btn-upload:hover {
            background-color: #218838;
            transform: translateY(-1px);
        }
        
        .upload-card {
            border-left: 4px solid #28a745;
        }
    </style>
</head>

<body>
    <?php autofrotaMenu(); ?>
    <main class="container py-4" style="max-width: 1100px;">

        <!-- Mensagem de upload -->
        <?php if ($uploadMensagem): ?>
            <div class="alert alert-<?= $uploadTipo ?> alert-dismissible fade show mb-3" role="alert">
                <i class="fa-solid fa-<?= $uploadTipo == 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
                <?= htmlspecialchars($uploadMensagem) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Painel de Debug (visível apenas com ?debug=1) -->
        <?php if (isset($_GET['debug']) && $_GET['debug'] == 1): ?>
            <div class="debug-panel visible">
                <h6>Debug Information:</h6>
                <pre><?php
                echo "ID: $id\n";
                echo "Erro: " . ($erro ?? 'Nenhum') . "\n";
                echo "Dados: " . print_r($dados, true) . "\n";
                echo "Placa: $placa\n";
                echo "Locadora: $locadora\n";
                echo "Tipoposse: $tipoposse\n";
                echo "Recibo: $recibo\n";
                echo "Recibos: $recibos\n";
                ?></pre>
            </div>
        <?php endif; ?>

        <h1 class="h3 mb-1 d-flex align-items-center">
            <a href="./multasfrota.php" class="text-decoration-none me-2">
                <i class="fa-solid fa-arrow-left p-1"></i>
            </a>
            Imprimir Recibos
        </h1>

        <?php if (isset($erro)): ?>
            <div class="alert alert-danger">
                <strong>Erro:</strong> <?php echo htmlspecialchars($erro); ?>
                <?php if (isset($_GET['debug'])): ?>
                    <br><small>Verifique o arquivo de log em ../debug_editar_multa_*.log para mais detalhes.</small>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($dados): ?>
            <section class="card shadow-sm mb-4 no-print">
                <div class="card-body text-center">
                    <p class="mb-3">Para imprimir o recibo de desconto, clique no botão abaixo.</p>
                    <a class="btn btn-primary" target="_blank" href="pdf/fpdf/<?= $recibo ?>2.php?id=<?= $id ?>">Imprimir
                        recibos</a>
                </div>
            </section>

            <div id="reciboImpressao">
                <!-- Dados da Multa -->
                <section class="card shadow-sm mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Dados da Multa - Recibo de Desconto</h5>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Trâmite</th>
                                    <th>Placa do Veículo</th>
                                    <th>Tipo</th>
                                    <th>Nº do Auto</th>
                                    <th>Data da Infração</th>
                                    <th>Vencimento</th>
                                    <th>Valor Total</th>
                                    <th>Qtd Parcelas</th>
                                    <th>Valor das Parcelas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?php echo htmlspecialchars($dados['tramite'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($dados['placa']); ?></td>
                                    <td><?php echo htmlspecialchars($dados['gravidade'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($dados['autoinfracao']); ?></td>
                                    <td><?php echo formatarData($dados['dtinfra']); ?></td>
                                    <td><?php echo formatarData($dados['datalimitecond']); ?></td>
                                    <td class="fw-bold"><?php echo formatarValor($dados['valtotal']); ?></td>
                                    <td><?php echo $quantidadeParcelas; ?></td>
                                    <td><?php echo formatarValor($valorParcela); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Informações Complementares -->
                <section class="card shadow-sm mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Informações Complementares</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Órgão Autuador:</strong>
                                    <?php echo htmlspecialchars($dados['orgao'] ?? 'N/A'); ?></p>
                                <p><strong>Código da Multa:</strong>
                                    <?php echo htmlspecialchars($dados['codigom'] ?? 'N/A'); ?></p>
                                <p><strong>Gravidade:</strong> <?php echo htmlspecialchars($dados['gravidade'] ?? 'N/A'); ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Data do Desconto:</strong>
                                    <?php echo ($dados['dtdesconto'] == '0000-00-00 00:00:00') ? 'N/A' : formatarData($dados['dtdesconto']); ?>
                                </p>
                                <p><strong>Centro de Custo:</strong>
                                    <?php echo htmlspecialchars($dados['ccusto'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Botões de ação -->
                <div class="mt-3 d-flex justify-content-around align-items-start flex-wrap gap-3">

                    <div>
                        <a href="multasfrota.php">
                            <button class="btn btn-secondary">Voltar para página inicial</button>
                        </a>
                    </div>

                    <?php
                    if ($locadora != '1' && $tipoposse != 'PRÓPRIO') {
                        echo "<div>
                            <a class='btn btn-primary' href='pdf/fpdf/{$recibo}.php?id={$id}'>Imprimir Recibo Locadora</a>
                        </div>";
                    } elseif ($tipoposse == 'PRÓPRIO') {
                        echo "<div><a class='btn btn-primary'>Imprimir Termo PRF</a></div>
                              <div><a class='btn btn-primary'>Imprimir Termo DETRAN</a></div>";
                    } else {
                        echo "<div><a class='btn btn-primary'>Imprimir Termo PRF</a></div>
                              <div><a class='btn btn-primary'>Imprimir Termo DETRAN</a></div>";
                    }
                    ?>

                    <!-- Upload com PHP puro -->
                    <div class="upload-card">
                        <form method="post" action="../control/reciboUpload.php" enctype="multipart/form-data" class="card shadow-sm p-3">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="hidden" name="placa" value="<?= htmlspecialchars($dados['placa'] ?? '') ?>">
                            <input type="hidden" name="autoinfra" value="<?= htmlspecialchars($dados['autoinfracao'] ?? '') ?>">
                            <input type="hidden" name="mat_autor" value="<?= htmlspecialchars($mat_autor) ?>">
                            
                            <h6 class="mb-3">
                                <i class="fa-solid fa-upload me-2 text-success"></i>
                                Enviar Arquivo Assinado
                            </h6>
                            
                            <div class="mb-3">
                                <input type="file" name="arquivo" id="arquivo" class="form-control" 
                                       accept=".jpg,.jpeg,.png,.gif,.pdf" required>
                                <small class="text-muted mt-1 d-block">
                                    <i class="fa-solid fa-info-circle me-1"></i>
                                    Formatos permitidos: JPG, PNG, GIF, PDF. Máximo: 32MB
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-success btn-upload w-100">
                                <i class="fa-solid fa-cloud-arrow-up me-2"></i> Enviar Arquivo
                            </button>
                        </form>
                    </div>

                </div><!-- /botões -->

                <hr class="my-4">

                <div class="row mt-2">
                    <div class="col-md-12 text-center">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            Certifique-se de que todos os documentos foram assinados antes de enviar.
                        </small>
                    </div>
                </div>

                <!-- Dados do Condutor -->
                <section class="card shadow-sm mt-3">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Dados do Condutor</h5>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nome do Condutor</th>
                                    <th>Matrícula</th>
                                    <th>Status na Empresa</th>
                                    <th>CNH</th>
                                    <th>RG</th>
                                    <th>CPF</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?php echo htmlspecialchars($dados['nome'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($dados['matricula'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($dados['status'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($dados['cnh'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($dados['rg'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($dados['cpf'] ?? 'N/A'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

            </div><!-- /reciboImpressao -->

        <?php else: ?>
            <div class="alert alert-warning">
                Nenhum dado encontrado para o ID informado.
                <?php if (isset($_GET['debug'])): ?>
                    <br><small>Verifique se o ID <?php echo $id; ?> existe na tabela tbmovidatramite.</small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // Fechar alertas automaticamente após 5 segundos
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
    </script>

</body>
</html>
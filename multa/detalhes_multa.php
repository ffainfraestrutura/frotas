<?php
require_once __DIR__ . '/../includes/autofrota_common.php';
$autofrotaSessao = autofrotaInit();
include "../func/funcoes.php";
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$id = $_GET['id'];

try {
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
                m.datahoracadastro,
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
                t.dt_envio_dp,
                t.recibo,
                t.reciboass,
                t.tdp,
                t.tfin,
                t.status,
                f.status as func_status,
                f.ccusto,
                f.rg,
                c.numcnh AS cnh,
                f.cpf
            FROM tbmulta m
            LEFT JOIN tbmovidatramite t
                ON t.placa = m.placa
               AND t.autoinfra = m.autoinfracao
            LEFT JOIN tbfuncionario f ON f.matricula = t.matricula
            LEFT JOIN tbcnh c ON c.matricula = t.matricula
            WHERE t.idtbmovidatramite = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $dados = mysqli_fetch_assoc($res);

    if (!$dados) {
        throw new Exception("Nenhum dado encontrado para o ID: $id");
    }


    // Calcular parcelas
    $valorTotal = floatval($dados['valtotal']);
    $quantidadeParcelas = 1;
    $valorParcela = $quantidadeParcelas > 0 ? $valorTotal / $quantidadeParcelas : $valorTotal;

} catch (Exception $e) {
    error_log($e->getMessage());
    $dados = null;
    $erro = $e->getMessage();
}

// Buscar TODOS os pareceres do histórico
$historicoPareceres = [];

$sqlPareceres = "
    SELECT 
        id,
        parecer,
        matricula,
        data_parecer
    FROM tbmulta_parecer 
    WHERE id_multa = ? 
    ORDER BY data_parecer ASC
";

$stmtPareceres = mysqli_prepare($conn, $sqlPareceres);
mysqli_stmt_bind_param($stmtPareceres, 'i', $id);
mysqli_stmt_execute($stmtPareceres);
$resPareceres = mysqli_stmt_get_result($stmtPareceres);

while ($row = mysqli_fetch_assoc($resPareceres)) {
    $historicoPareceres[] = $row;
}

// Ordenar todos os pareceres por data (mais antigo primeiro)
usort($historicoPareceres, function ($a, $b) {
    return strtotime($a['data_parecer']) - strtotime($b['data_parecer']);
});

// Função para formatar data
function formatarData($data)
{
    if ($data && $data != '0000-00-00' && $data != '0000-00-00 00:00:00') {
        return date('d/m/Y H:i:s', strtotime($data));
    }
    return 'Não informada';
}

function formatarDataSimples($data)
{
    if ($data && $data != '0000-00-00') {
        return date('d/m/Y', strtotime($data));
    }
    return 'Não informada';
}

// Função para formatar valor monetário
function formatarValor($valor)
{
    return 'R$ ' . number_format(floatval($valor), 2, ',', '.');
}

// Função para obter badge de status
function getStatusBadge($status)
{
    $badges = [
        'Aguardando recibo' => '<span class="badge bg-warning text-dark" style="font-size: 0.7rem;">Aguardando recibo</span>',
        'Recibo anexado' => '<span class="badge bg-info" style="font-size: 0.7rem;">Recibo anexado</span>',
        'Validar Recibo' => '<span class="badge bg-primary" style="font-size: 0.7rem;">Validar Recibo</span>',
        'Fazer Pagamento' => '<span class="badge bg-success" style="font-size: 0.7rem;">Fazer Pagamento</span>',
        'Finalizado Frota' => '<span class="badge bg-secondary" style="font-size: 0.7rem;">Finalizado Frota</span>',
        'Ativo' => '<span class="badge bg-success" style="font-size: 0.7rem;">Ativo</span>',
        'Inativo' => '<span class="badge bg-danger" style="font-size: 0.7rem;">Inativo</span>',
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary" style="font-size: 0.7rem;">' . htmlspecialchars($status) . '</span>';
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Histórico Completo - Multa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../src/autofrota-botoes.css">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body {
            font-size: 0.85rem;
        }

        h1.h3 {
            font-size: 1.2rem !important;
        }

        h5 {
            font-size: 0.95rem !important;
        }

        .card {
            margin-bottom: 0.75rem !important;
        }

        .card-header {
            padding: 0.5rem 1rem !important;
        }

        .card-body {
            padding: 0.75rem !important;
        }

        .table {
            font-size: 0.8rem;
            margin-bottom: 0;
        }

        .table td,
        .table th {
            padding: 0.4rem 0.5rem;
            vertical-align: middle;
        }

        .btn {
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
        }

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
                font-size: 0.75rem;
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

        .timeline {
            position: relative;
            padding-left: 20px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 1px;
            background: #dee2e6;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 15px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -17px;
            top: 12px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #007bff;
            border: 1px solid #fff;
            box-shadow: 0 0 0 1px #007bff;
        }

        .timeline-item.success::before {
            background: #28a745;
            box-shadow: 0 0 0 1px #28a745;
        }

        .timeline-item.warning::before {
            background: #ffc107;
            box-shadow: 0 0 0 1px #ffc107;
        }

        .timeline-item.info::before {
            background: #17a2b8;
            box-shadow: 0 0 0 1px #17a2b8;
        }

        .timeline-item .border {
            padding: 0.6rem !important;
        }

        .timeline-item p {
            margin-bottom: 0.25rem;
            font-size: 0.8rem;
        }

        .timeline-item .bg-white {
            padding: 0.5rem !important;
            font-size: 0.8rem;
        }

        .badge-status {
            font-size: 0.7rem;
            padding: 3px 8px;
        }

        .parecer-card:hover {
            transform: translateX(3px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .data-destaque {
            font-size: 0.75rem;
            color: #666;
        }

        i {
            font-size: 0.8rem;
        }

        .container {
            max-width: 1000px !important;
        }

        .ms-4 {
            margin-left: 1rem !important;
        }
    </style>
</head>

<body>
    <?php autofrotaMenu(); ?>
    <main class="container py-3" style="max-width: 1000px;">
        <h1 class="h3 mb-2 d-flex align-items-center">
            <a href="./multasfrota.php" class="text-decoration-none me-2">
                <i class="fa-solid fa-arrow-left p-1"></i>
            </a>
            Histórico Completo da Multa
        </h1>

        <?php if (isset($erro)): ?>
            <div class="alert alert-danger alert-sm">Erro: <?php echo htmlspecialchars($erro); ?></div>
        <?php endif; ?>

        <?php if ($dados): ?>
            <div id="reciboImpressao">
                <!-- Dados da Multa -->
                <section class="card shadow-sm">
                    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Dados da Multa</h5>
                        <i class="fa-solid fa-file-invoice fs-6"></i>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Trâmite</th>
                                    <th>Placa</th>
                                    <th>Tipo</th>
                                    <th>Nº do Auto</th>
                                    <th>Data Infração</th>
                                    <th>Vencimento</th>
                                    <th>Valor Total</th>
                                    <th>Qtd Parc</th>
                                    <th>Valor Parcela</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?php echo htmlspecialchars($dados['tramite'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($dados['placa']); ?></td>
                                    <td><?php echo htmlspecialchars($dados['gravidade'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($dados['autoinfracao']); ?></td>
                                    <td><?php echo formatarDataSimples($dados['dtinfra']); ?></td>
                                    <td><?php echo formatarDataSimples($dados['datalimitecond']); ?></td>
                                    <td class="fw-bold"><?php echo formatarValor($dados['valtotal']); ?></td>
                                    <td><?php echo $quantidadeParcelas; ?></td>
                                    <td><?php echo formatarValor($valorParcela); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- LINHA DO TEMPO COMPLETA -->
                <section class="card shadow-sm">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-timeline me-2"></i>
                            Linha do Tempo
                        </h5>
                        <i class="fa-solid fa-clock fs-6"></i>
                    </div>
                    <div class="card-body">
                        <div class="timeline">

                            <!-- 1. DATA DE CRIAÇÃO DO PROCESSO -->
                            <?php if (!empty($dados['datahoracadastro']) && $dados['datahoracadastro'] != '0000-00-00 00:00:00'): ?>
                                <div class="timeline-item success">
                                    <div class="border rounded p-2 mb-2 bg-light">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <div>
                                                <i class="fa-solid fa-plus-circle text-success me-1"></i>
                                                <strong class="text-success">Cadastro da multa</strong>
                                            </div>
                                            <span class="text-muted small">
                                                <i class="fa-regular fa-calendar me-1"></i>
                                                <?php echo formatarData($dados['datahoracadastro']); ?>
                                            </span>
                                        </div>
                                        <div class="ms-4">
                                            <p class="mb-0 small">
                                                <strong>Criado por:</strong>
                                                <?php echo htmlspecialchars($dados['parecerpor'] ?? 'Sistema'); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- 2. TODOS OS PARECERES DO HISTÓRICO -->
                            <?php foreach ($historicoPareceres as $index => $parecer): ?>
                                <div class="timeline-item">
                                    <div class="border rounded p-2 mb-2 bg-light parecer-card">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <i class="fa-regular fa-comment-dots me-1 text-primary"></i>
                                                <strong>Parecer</strong>
                                            </div>
                                            <span class="text-muted small">
                                                <i class="fa-regular fa-calendar me-1"></i>
                                                <?php echo formatarData($parecer['data_parecer']); ?>
                                            </span>
                                        </div>

                                        <div class="ms-4">
                                            <p class="mb-1 small">
                                                <i class="fa-regular fa-user me-1"></i>
                                                <strong>Responsável:</strong>
                                                <?php echo htmlspecialchars($parecer['matricula']); ?>
                                            </p>
                                            <div class="bg-white p-2 rounded mt-1 small">
                                                <i class="fa-solid fa-quote-left me-1 text-muted"></i>
                                                <?php echo nl2br(htmlspecialchars($parecer['parecer'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- 3. DATA DE ENVIO PARA O DP -->
                            <?php if (!empty($dados['dt_envio_dp']) && $dados['dt_envio_dp'] != '0000-00-00 00:00:00'): ?>
                                <div class="timeline-item info">
                                    <div class="border rounded p-2 mb-2 bg-light">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <div>
                                                <i class="fa-solid fa-paper-plane text-info me-1"></i>
                                                <strong class="text-info">ENVIADO PARA DP</strong>
                                            </div>
                                            <span class="text-muted small">
                                                <i class="fa-regular fa-calendar me-1"></i>
                                                <?php echo formatarData($dados['dt_envio_dp']); ?>
                                            </span>
                                        </div>
                                        <div class="ms-4">
                                            <?php if (!empty($dados['recibo'])): ?>
                                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                                    <a href="<?php echo $dados['recibo']; ?>" target="_blank"
                                                        class="btn btn-sm btn-success"
                                                        style="font-size: 0.7rem; padding: 0.2rem 0.5rem;">
                                                        <i class="fa-regular fa-file-pdf me-1"></i> Ver Recibo
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-warning btn-substituir-recibo"
                                                        style="font-size: 0.7rem; padding: 0.2rem 0.5rem;"
                                                        data-id="<?php echo $id; ?>"
                                                        data-placa="<?php echo htmlspecialchars($dados['placa']); ?>"
                                                        data-autoinfra="<?php echo htmlspecialchars($dados['autoinfracao']); ?>">
                                                        <i class="fa-solid fa-arrows-rotate me-1"></i> Substituir Recibo
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-primary btn-substituir-recibo"
                                                    style="font-size: 0.7rem; padding: 0.2rem 0.5rem;" data-id="<?php echo $id; ?>"
                                                    data-placa="<?php echo htmlspecialchars($dados['placa']); ?>"
                                                    data-autoinfra="<?php echo htmlspecialchars($dados['autoinfracao']); ?>">
                                                    <i class="fa-solid fa-upload me-1"></i> Anexar Recibo
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- Dados do Condutor -->
                <section class="card shadow-sm mt-2">
                    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Dados do Condutor</h5>
                        <i class="fa-solid fa-user-check fs-6"></i>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nome</th>
                                    <th>Matrícula</th>
                                    <th>Status</th>
                                    <th>CNH</th>
                                    <th>RG</th>
                                    <th>CPF</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?php echo htmlspecialchars($dados['nome'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($dados['matricula'] ?? 'N/A'); ?></td>
                                    <td><?php echo getStatusBadge($dados['func_status'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($dados['cnh'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($dados['rg'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($dados['cpf'] ?? 'N/A'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

            </div>

            <!-- Botões de Ação -->
            <div class="mt-3 d-flex justify-content-end gap-2 no-print">
                <button onclick="window.print()" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-print me-1"></i>Imprimir
                </button>
                <a href="./multasfrota.php" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i>Voltar
                </a>
            </div>

        <?php else: ?>
            <div class="alert alert-warning alert-sm">
                <i class="fa-solid fa-exclamation-triangle me-2"></i>
                Nenhum dado encontrado para o ID informado.
            </div>
        <?php endif; ?>

        <!-- Modal para substituir recibo -->
        <div class="modal fade" id="modalSubstituirRecibo" tabindex="-1" aria-labelledby="modalSubstituirReciboLabel"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title" id="modalSubstituirReciboLabel">
                            <i class="fa-solid fa-arrows-rotate me-2"></i>Substituir Recibo
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="formSubstituirRecibo" enctype="multipart/form-data" method="POST"
                        action="../control/reciboUpload.php">
                        <div class="modal-body">
                            <input type="hidden" name="id" id="substituir_id">
                            <input type="hidden" name="placa" id="substituir_placa">
                            <input type="hidden" name="autoinfra" id="substituir_autoinfra">
                            <input type="hidden" name="mat_autor" value="<?php echo $_SESSION['matricula'] ?? ''; ?>">

                            <div class="mb-3">
                                <label for="arquivo_recibo" class="form-label">Selecione o novo arquivo</label>
                                <input type="file" class="form-control" id="arquivo_recibo" name="arquivo"
                                    accept=".jpg,.jpeg,.png,.gif,.pdf" required>
                                <div class="form-text text-muted small">
                                    <i class="fa-solid fa-info-circle me-1"></i>
                                    Formatos permitidos: JPG, JPEG, PNG, GIF, PDF. O arquivo antigo será substituído.
                                </div>
                            </div>

                            <div class="alert alert-info small">
                                <i class="fa-solid fa-clock me-1"></i>
                                <strong>Atenção:</strong> Ao substituir o recibo, o histórico de envio será atualizado
                                com a nova data e hora.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm"
                                data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-warning btn-sm">
                                <i class="fa-solid fa-upload me-1"></i> Substituir Recibo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function () {
            // Botão para substituir recibo
            $('.btn-substituir-recibo').on('click', function () {
                var id = $(this).data('id');
                var placa = $(this).data('placa');
                var autoinfra = $(this).data('autoinfra');

                $('#substituir_id').val(id);
                $('#substituir_placa').val(placa);
                $('#substituir_autoinfra').val(autoinfra);

                $('#modalSubstituirRecibo').modal('show');
            });

            // Reset do formulário quando o modal fechar
            $('#modalSubstituirRecibo').on('hidden.bs.modal', function () {
                $('#formSubstituirRecibo')[0].reset();
            });
        });
    </script>
</body>

</html>

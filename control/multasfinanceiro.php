<?php
require_once __DIR__ . '/../includes/autofrota_common.php';
$autofrotaSessao = autofrotaInit();
include "../func/funcoes.php";

$conn = $autofrotaSessao['conn'] ?? null;

$dataInicial = trim((string) ($_POST['data_inicial'] ?? ''));
$dataFinal = trim((string) ($_POST['data_final'] ?? ''));
$incluirFinalizadas = trim((string) ($_POST['incluir_finalizadas'] ?? 'nao'));
$hoje = new DateTimeImmutable('now');

// Definir o departamento com base nos dados da sessão
$perfil = $_SESSION['perfil'] ?? '';
$tipo = $_SESSION['tipo'] ?? '';

if ($perfil == '5') {
    $depart = 'Financeiro';
} elseif ($tipo == '1') {
    $depart = 'Supervisor';
} elseif ($tipo == '4') {
    $depart = 'Frotas';
} elseif ($tipo == '6') {
    $depart = 'DP';
} elseif ($tipo == '7') {
    $depart = 'Financeiro';
} elseif ($tipo == '8') {
    $depart = 'Controladoria';
} else {
    $depart = '';
}

function formatarData(?string $valor): string
{
    if (empty($valor) || $valor === '0000-00-00' || $valor === '0000-00-00 00:00:00') {
        return '-';
    }
    $data = date_create($valor);
    return $data ? date_format($data, 'd/m/Y') : (string) $valor;
}

function calcularDiasVencimento(string $dataVencimento, DateTimeImmutable $hoje): array
{
    if (empty($dataVencimento) || $dataVencimento === '0000-00-00' || $dataVencimento === '0000-00-00 00:00:00') {
        return ['texto' => 'Indeterminado', 'classe' => ''];
    }

    $dtVenc = date_create($dataVencimento);
    if (!$dtVenc) {
        return ['texto' => 'Indeterminado', 'classe' => ''];
    }

    $dtVencImmutable = DateTimeImmutable::createFromMutable($dtVenc);
    
    if ($hoje > $dtVencImmutable) {
        $intervalo = $hoje->diff($dtVencImmutable);
        return ['texto' => 'Venceu há ' . $intervalo->format('%a') . ' dia(s)', 'classe' => 'situacao-vencido'];
    } else {
        $intervalo = $hoje->diff($dtVencImmutable);
        return ['texto' => 'Vence em ' . $intervalo->format('%a') . ' dia(s)', 'classe' => 'situacao-aberto'];
    }
}

// Buscar multas
$linhasMultas = [];

if ($conn instanceof mysqli) {
    mysqli_set_charset($conn, 'utf8mb4');

    if ($incluirFinalizadas === 'sim') {
        $filtroDp = "1=1";
    } else {
        $filtroDp = "mt.tdp <> 'DP Finalizado'";
    }

    if (!empty($dataInicial) && !empty($dataFinal)) {
        $sql = "
            SELECT 
                mt.idtbmovidatramite,
                mt.placa,
                mt.autoinfra,
                mt.dtinfra,
                mt.dtvenc,
                mt.valor,
                mt.tdp,
                mt.gravidade,
                mt.recibo,
                mt.datahoracadastro,
                f.nome,
                f.matricula,
                f.status as status_condutor
            FROM tbmovidatramite mt
            JOIN tbmulta m ON m.idtbmulta = mt.idmulta
            JOIN bdaniel.tbfuncionario f ON f.matricula = mt.matricula
            WHERE {$filtroDp}
                AND mt.placa <> ''
                AND mt.placa <> 'ABC1234'
                AND mt.placa <> 'ABC1245'
                AND mt.recibo <> ''
                AND f.status = 'Ativo'
                AND YEAR(mt.datahoracadastro) = YEAR(CURDATE())
                AND mt.datahoracadastro BETWEEN ? AND ?
            ORDER BY mt.datahoracadastro DESC
        ";

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            $dataInicioDt = $dataInicial . ' 00:00:00';
            $dataFinalDt = $dataFinal . ' 23:59:59';
            mysqli_stmt_bind_param($stmt, 'ss', $dataInicioDt, $dataFinalDt);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
        }
    } else {
        $sql = "
            SELECT 
                mt.idtbmovidatramite,
                mt.placa,
                mt.autoinfra,
                mt.dtinfra,
                mt.dtvenc,
                mt.valor,
                mt.tdp,
                mt.gravidade,
                mt.recibo,
                f.nome,
                f.matricula,
                f.status as status_condutor
            FROM tbmovidatramite mt
            JOIN tbmulta m ON m.idtbmulta = mt.idmulta
            JOIN bdaniel.tbfuncionario f ON f.matricula = mt.matricula
            WHERE {$filtroDp}
                AND mt.placa <> ''
                AND mt.placa <> 'ABC1234'
                AND mt.placa <> 'ABC1245'
                AND mt.recibo <> ''
                AND f.status = 'Ativo'
            ORDER BY mt.datahoracadastro DESC
        ";

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
        }
    }

    if (isset($res) && $res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $id = (string) ($row['idtbmovidatramite'] ?? '');
            $placa = (string) ($row['placa'] ?? '');
            $gravidade = (string) ($row['gravidade'] ?? '');
            $autoinfra = (string) ($row['autoinfra'] ?? '');
            $datainfra = (string) ($row['dtinfra'] ?? '');
            $dtvenc = (string) ($row['dtvenc'] ?? '');
            $valor = (string) ($row['valor'] ?? '');
            $tdp = (string) ($row['tdp'] ?? '');
            $nomecond = (string) ($row['nome'] ?? '');
            $matcond = (string) ($row['matricula'] ?? '');
            $statusCondutor = (string) ($row['status_condutor'] ?? '');

            if ($tdp === 'Validar recibo') {
                $tdp = 'Validar Recibo';
            }

            $valorFormatado = 'R$ ' . number_format((float) str_replace(',', '.', $valor), 2, ',', '.');
            $datainfraFormatada = formatarData($datainfra);
            $dtvencFormatada = formatarData($dtvenc);
            $situacaoVencimento = calcularDiasVencimento($dtvenc, $hoje);

            $badgeStatusCondutor = $statusCondutor === 'Ativo' 
                ? '<span class="badge bg-success" style="font-size: 0.7rem;">Ativo</span>' 
                : '<span class="badge bg-secondary" style="font-size: 0.7rem;">' . htmlspecialchars($statusCondutor) . '</span>';

            $botaoTramite = '';
            if ($id !== '') {
                $classeBotao = $tdp === 'Validar Recibo' ? 'btn-success' : 'btn-primary';
                $botaoTramite = sprintf(
                    '<form method="post" action="./validar_recibo.php" class="d-inline">
                        <input type="hidden" name="id" value="%s">
                        <button type="submit" class="btn %s btn-sm" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;">%s</button>
                    </form>',
                    htmlspecialchars($id),
                    $classeBotao,
                    htmlspecialchars($tdp)
                );
            }

            $linhasMultas[] = [
                'matricula' => $matcond,
                'nome_condutor' => $nomecond,
                'status_condutor' => $badgeStatusCondutor,
                'placa' => $placa,
                'gravidade' => $gravidade,
                'auto_infracao' => $autoinfra,
                'data_infracao' => $datainfraFormatada,
                'data_vencimento' => $dtvencFormatada,
                'situacao_vencimento' => $situacaoVencimento['texto'],
                'classe_vencimento' => $situacaoVencimento['classe'],
                'valor' => $valorFormatado,
                'tramite' => $botaoTramite,
            ];
        }
        mysqli_free_result($res);
        if (isset($stmt)) mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Listar Multas DP - FFA Infraestrutura</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../src/autofrota-botoes.css">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body {
            font-size: 0.85rem;
            background-color: #f8f9fc;
        }

        h1.h3 {
            font-size: 1.2rem !important;
        }

        h6 {
            font-size: 0.9rem !important;
        }

        .card {
            margin-bottom: 1rem;
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }

        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 0.75rem 1rem;
        }

        .table {
            font-size: 0.8rem;
            margin-bottom: 0;
        }

        .table td,
        .table th {
            padding: 0.6rem 0.5rem;
            vertical-align: middle;
        }

        .btn {
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
        }

        .filter-area {
            background: #fff;
            padding: 1rem;
            border-radius: 0.35rem;
            margin-bottom: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .form-control,
        .form-select {
            font-size: 0.8rem;
            padding: 0.3rem 0.5rem;
        }

        .form-check-label {
            font-size: 0.8rem;
        }

        .alert-info {
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
        }

        .btn-excel {
            background-color: #1F724C;
            color: white;
        }

        .btn-excel:hover {
            background-color: #145a3c;
            color: white;
        }

        .situacao-vencido {
            color: #fff;
            background-color: #733030;
            display: inline-block;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 0.7rem;
        }

        .situacao-aberto {
            color: #fff;
            background-color: #255573;
            display: inline-block;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 0.7rem;
        }
    </style>
</head>

<body>
    <?php autofrotaMenu(); ?>

    <main class="container-fluid px-4 py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h3 mb-0">Multas</h1>
                <?php if ($depart !== ''): ?>
                    <h6 class="text-muted mt-1"><?php echo htmlspecialchars($depart); ?></h6>
                <?php endif; ?>
            </div>
            <div>
                <button class="btn btn-excel btn-sm" onclick="exportarExcel()">
                    <i class="fa-solid fa-file-excel me-1"></i> Exportar para Excel
                </button>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-area">
            <form method="post" action="#">
                <div class="row align-items-end">
                    <div class="col-md-4">
                        <label class="form-label" for="data_inicial">Data Inicial (cadastro)</label>
                        <input class="form-control" type="date" name="data_inicial" id="data_inicial" value="<?php echo htmlspecialchars($dataInicial); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="data_final">Data Final (cadastro)</label>
                        <input class="form-control" type="date" name="data_final" id="data_final" value="<?php echo htmlspecialchars($dataFinal); ?>">
                    </div>
                    <div class="col-md-2">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="incluir_finalizadas" id="incluir_finalizadas" value="sim" <?php echo $incluirFinalizadas === 'sim' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="incluir_finalizadas">
                                Incluir finalizadas
                            </label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-success w-100" type="submit">
                            <i class="fa-solid fa-filter me-1"></i> Filtrar
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php if (!empty($dataInicial) && !empty($dataFinal)): ?>
            <div class="alert alert-info">
                <i class="fa-solid fa-info-circle me-2"></i>
                Período filtrado: <strong><?php echo date('d/m/Y', strtotime($dataInicial)); ?></strong> a <strong><?php echo date('d/m/Y', strtotime($dataFinal)); ?></strong>
                <?php if ($incluirFinalizadas === 'sim'): ?>
                    <span class="badge bg-secondary ms-2">Incluindo finalizadas</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Tabela -->
        <div class="card">
            <div class="card-header">
                <i class="fa-solid fa-list me-1"></i>
                Lista de Multas
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tabelaMultas" class="table table-bordered table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Matrícula condutor</th>
                                <th>Nome condutor</th>
                                <th>Situação condutor</th>
                                <th>Placa</th>
                                <th>Gravidade</th>
                                <th>Nº do Auto</th>
                                <th>Data Infração</th>
                                <th>Data Vencimento</th>
                                <th>Situação</th>
                                <th>Valor</th>
                                <th>Trâmite</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($linhasMultas as $multa): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($multa['matricula']); ?></td>
                                    <td><?php echo htmlspecialchars($multa['nome_condutor']); ?></td>
                                    <td><?php echo $multa['status_condutor']; ?></td>
                                    <td><?php echo htmlspecialchars($multa['placa']); ?></td>
                                    <td><?php echo htmlspecialchars($multa['gravidade']); ?></td>
                                    <td><?php echo htmlspecialchars($multa['auto_infracao']); ?></td>
                                    <td><?php echo htmlspecialchars($multa['data_infracao']); ?></td>
                                    <td><?php echo htmlspecialchars($multa['data_vencimento']); ?></td>
                                    <td><span class="<?php echo $multa['classe_vencimento']; ?>"><?php echo htmlspecialchars($multa['situacao_vencimento']); ?></span></td>
                                    <td><?php echo htmlspecialchars($multa['valor']); ?></td>
                                    <td><?php echo $multa['tramite']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($linhasMultas)): ?>
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-4">
                                        <i class="fa-solid fa-inbox me-2"></i> Nenhuma multa encontrada
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th>Matrícula condutor</th>
                                <th>Nome condutor</th>
                                <th>Situação condutor</th>
                                <th>Placa</th>
                                <th>Gravidade</th>
                                <th>Nº do Auto</th>
                                <th>Data Infração</th>
                                <th>Data Vencimento</th>
                                <th>Situação</th>
                                <th>Valor</th>
                                <th>Trâmite</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#tabelaMultas').DataTable({
                "order": [[6, 'asc']],
                "pageLength": 25,
                "language": {
                    "decimal": "",
                    "emptyTable": "Nada para exibir",
                    "info": "Mostrando de _START_ até _END_ de _TOTAL_ registros",
                    "infoEmpty": "Exibindo página 0 de 0 de 0 registros",
                    "infoFiltered": "(filtrado do total de _MAX_ registros)",
                    "infoPostFix": "",
                    "thousands": ",",
                    "lengthMenu": "Exibir _MENU_ registros",
                    "loadingRecords": "Carregando...",
                    "processing": "Processando...",
                    "search": "Buscar:",
                    "zeroRecords": "Nenhum resultado encontrado",
                    "paginate": {
                        "first": "Primeira",
                        "last": "Última",
                        "next": "Próxima",
                        "previous": "Anterior"
                    }
                }
            });
        });

        function exportarExcel() {
            const tabela = document.getElementById('tabelaMultas');
            const linhas = tabela.querySelectorAll('tr');
            const dados = [];
            
            for (let i = 0; i < linhas.length; i++) {
                const celulas = linhas[i].querySelectorAll('td, th');
                const linhaDados = [];
                for (let j = 0; j < celulas.length; j++) {
                    let texto = celulas[j].innerText || '';
                    linhaDados.push(texto);
                }
                if (linhaDados.length > 0) {
                    dados.push(linhaDados);
                }
            }

            if (dados.length <= 1) {
                alert('Nenhum dado para exportar!');
                return;
            }

            const ws = XLSX.utils.aoa_to_sheet(dados);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Multas DP");
            
            const dataAtual = new Date();
            const nomeArquivo = `multas_dp_${dataAtual.getFullYear()}-${String(dataAtual.getMonth() + 1).padStart(2, '0')}-${String(dataAtual.getDate()).padStart(2, '0')}.xlsx`;
            
            XLSX.writeFile(wb, nomeArquivo);
            alert(`Arquivo exportado: ${nomeArquivo}\n(${dados.length - 1} linhas)`);
        }
    </script>
</body>

</html>
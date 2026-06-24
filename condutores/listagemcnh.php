<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$matriculaLogada = $autofrotaSessao['matricula'];
$perfilLogado = $autofrotaSessao['perfil'];
$unidades = ['RJ', 'PR', 'SP', 'TODOS'];
$colaboradores = [];

function buscarEstadoFuncionarioCnh(mysqli $conn, string $databaseName, string $matricula): string
{
    if ($matricula === '') {
        return '';
    }

    $sql = "
        SELECT estado
        FROM `{$databaseName}`.`tbfuncionario`
        WHERE matricula = ?
          AND status = 'Ativo'
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return '';
    }

    mysqli_stmt_bind_param($stmt, 's', $matricula);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $funcionario = $resultado ? mysqli_fetch_assoc($resultado) : null;
    mysqli_stmt_close($stmt);

    return (string) ($funcionario['estado'] ?? '');
}

$estadoFuncionario = '';
if (isset($conn) && $conn instanceof mysqli) {
    $estadoFuncionario = buscarEstadoFuncionarioCnh($conn, $databaseName, $matriculaLogada);
}

$unidadePostada = (string) ($_POST['unidade'] ?? '');
$unidadeSelecionada = in_array($unidadePostada, $unidades, true) ? $unidadePostada : ($estadoFuncionario ?: 'RJ');

if (!in_array($unidadeSelecionada, $unidades, true)) {
    $unidadeSelecionada = 'RJ';
}

$sqlCnh = "
    SELECT
        cn.matricula,
        f.nome,
        f.estado
    FROM `{$databaseName}`.`tbcnh` cn
    INNER JOIN `{$databaseName}`.`tbfuncionario` f
        ON f.matricula = cn.matricula
    WHERE f.status = 'Ativo'
";

if ($unidadeSelecionada !== 'TODOS') {
    $sqlCnh .= '      AND f.estado = ?' . PHP_EOL;
}

$sqlCnh .= '    ORDER BY f.nome ASC';

if (isset($conn) && $conn instanceof mysqli) {
    $stmtCnh = mysqli_prepare($conn, $sqlCnh);

    if ($stmtCnh) {
        if ($unidadeSelecionada !== 'TODOS') {
            mysqli_stmt_bind_param($stmtCnh, 's', $unidadeSelecionada);
        }

        mysqli_stmt_execute($stmtCnh);
        $resultadoCnh = mysqli_stmt_get_result($stmtCnh);

        if ($resultadoCnh) {
            while ($colaborador = mysqli_fetch_assoc($resultadoCnh)) {
                $colaboradores[] = [
                    'matricula' => (string) ($colaborador['matricula'] ?? ''),
                    'nome' => (string) ($colaborador['nome'] ?? ''),
                    'estado' => (string) ($colaborador['estado'] ?? ''),
                ];
            }

            mysqli_free_result($resultadoCnh);
        }

        mysqli_stmt_close($stmtCnh);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="AutoFrota" />
    <meta name="author" content="FFA" />
    <title>AutoFrota - Listagem decolaboradores com CNH cadastrada</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body { background: #ffffff; color: #212529; font-size: 14px; }
        .page-title { font-size: 24px; font-weight: 600; margin: 0 0 10px; }
        .notice { font-style: italic; font-size: 14px; margin-bottom: 12px; }
        .filter-area { border-bottom: 1px solid #dee2e6; padding-bottom: 18px; margin-bottom: 16px; }
        .content-container { width: 80%; }
        .btn-icon { border: none; background-color: transparent; color: #212529; padding: 0; }
        @media (max-width: 992px) { .content-container { width: 100%; } }
    </style>
</head>
<body class="sb-nav-fixed vsc-initialized sb-sidenav-toggled">
    <?php autofrotaMenu(); ?>

        <div id="layoutSidenav_content">
            <main style="width: 100%;" class="mb-2 py-3">
                <div class="container-fluid px-4 content-container">
                    <h1 class="page-title">CNHs Cadastradas</h1>

                    <section class="filter-area">
                        <form action="listagemcnh.php" method="post">
                            <div class="d-flex justify-content-start align-items-end">
                                <div>
                                    <label class="form-label fw-bold" for="unidade">Selecione unidade do colaborador:</label>
                                    <select class="form-select" name="unidade" id="unidade">
                                        <?php foreach ($unidades as $unidade): ?>
                                            <option value="<?= htmlspecialchars($unidade) ?>" <?= $unidadeSelecionada === $unidade ? 'selected' : '' ?>><?= htmlspecialchars($unidade) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="ms-3">
                                    <button type="submit" class="btn btn-success">Filtrar</button>
                                </div>
                            </div>
                        </form>
                    </section>

                    <div class="mt-3 alert alert-info" role="alert">
                        <strong>Filtro Aplicado:</strong> Exibindo funcionários do estado: <strong><?= htmlspecialchars($unidadeSelecionada === 'TODOS' ? 'TODOS OS ESTADOS' : $unidadeSelecionada) ?></strong>
                    </div>

                    <div class="mt-3 d-flex justify-content-left">
                        <a class="btn btn-success" href="funcionarios-semcnh.php">Cadastrar CNH</a>
                    </div>

                    <div style="width: 100%;" class="mt-4 m-auto">
                        <table id="tabela1" class="table table-striped" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Matrícula</th>
                                    <th>Nome</th>
                                    <th>Unidade do Colaborador</th>
                                    <th>Editar CNH</th>
                                    <th>Anexar documentos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($colaboradores as $colaborador): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($colaborador['matricula']) ?></td>
                                        <td><?= htmlspecialchars($colaborador['nome']) ?></td>
                                        <td><?= htmlspecialchars($colaborador['estado']) ?></td>
                                        <td>
                                            <form method="post" action="editarcnh.php">
                                                <input type="hidden" name="matricula" value="<?= htmlspecialchars($colaborador['matricula']) ?>">
                                                <input type="hidden" name="matr_autor" value="<?= htmlspecialchars($matriculaLogada) ?>">
                                                <button class="btn-icon" type="submit" title="Editar CNH">
                                                    <span class="material-symbols-outlined">edit_note</span>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="text-center">
                                             <form method="post" action="enviardocscondutor.php">
                                                <input type="hidden" name="matcondutor" value="<?= htmlspecialchars($colaborador['matricula']) ?>">
                                                <input type="hidden" name="matr_autor" value="<?= htmlspecialchars($matriculaLogada) ?>">
                                                <input type="hidden" name="perf_autor" value="<?= htmlspecialchars($perfilLogado) ?>">
                                                <button class="btn-icon" type="submit" title="Anexar documentos">
                                                    <span class="material-symbols-outlined">attach_file</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Matrícula</th>
                                    <th>Nome</th>
                                    <th>Unidade do Colaborador</th>
                                    <th>Editar CNH</th>
                                    <th>Anexar documentos</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mt-5 text-rigth d-flex justify-content-between">
                        <div>
                            <button class="btn btn-secondary" type="button" onclick="window.location.href = 'listagem-condutor.php';">Voltar</button>
                        </div>

                        <div>
                            <a class="btn btn-secondary text-rigth" href="listagem-condutor.php">Condutores</a>
                        </div>
                    </div>
                </div>
            </main>

            <footer class="py-4 bg-light mt-auto">
                <div class="container-fluid px-4">
                    <div class="d-flex align-items-center justify-content-between small">
                        <div class="text-muted">Copyright &copy; FFA Infraestrutura</div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.3/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', function(event) {
            event.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
        });

        $(document).ready(function() {
            var width = $(window).width();
            var dataTableOptions = {
                order: [[1, 'asc']],
                language: {
                    decimal: '',
                    emptyTable: 'Nada para exibir',
                    info: 'Mostrando de _START_ até _END_ de _TOTAL_ registros',
                    infoEmpty: 'Exibindo página 0 de 0 de 0 registros',
                    infoFiltered: '(filtrado do total de _MAX_ registros)',
                    thousands: ',',
                    lengthMenu: 'Exibir _MENU_ registros',
                    loadingRecords: 'Carregando...',
                    processing: 'Processando...',
                    search: 'Buscar:',
                    zeroRecords: 'Nenhum resultado encontrado',
                    paginate: { first: 'Primeira', last: 'Última', next: 'Próxima', previous: 'Anterior' }
                }
            };

            if (width <= 768) {
                dataTableOptions.responsive = true;
            }

            $('#tabela1').DataTable(dataTableOptions);
        });
    </script>
</body>
</html>

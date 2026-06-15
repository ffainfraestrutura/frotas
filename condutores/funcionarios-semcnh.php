<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();

header('Content-Type: text/html; charset=utf-8');

$matriculaLogada = $autofrotaSessao['matricula'];
$unidades = ['RJ', 'PR', 'SP', 'TODOS'];
$funcionarios = [];

function buscarEstadoFuncionario(mysqli $conn, string $databaseName, string $matricula): string
{
    if ($matricula === '') {
        return '';
    }

    $sql = "
        SELECT estado
        FROM `{$databaseName}`.`tbfuncionario`
        WHERE matricula = ?
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

    return $funcionario['estado'] ?? '';
}

function buscarFuncionariosSemCnh(mysqli $conn, string $databaseName, string $unidadeSelecionada): array
{
    $funcionarios = [];
    $sql = "
        SELECT f.idtbfuncionario, f.matricula, f.nome, f.ccusto, f.estado
        FROM `{$databaseName}`.`tbfuncionario` AS f
        LEFT JOIN `{$databaseName}`.`tbcnh` AS c
            ON c.matricula = f.matricula
        WHERE f.status <> 'Demitido'
          AND f.matricula <> '999999'
          AND c.matricula IS NULL
    ";

    if ($unidadeSelecionada !== 'TODOS') {
        $sql .= "\n          AND f.estado = ?";
    }

    $sql .= "\n        ORDER BY f.nome ASC";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return $funcionarios;
    }
     
    if ($unidadeSelecionada !== 'TODOS') {
        mysqli_stmt_bind_param($stmt, 's', $unidadeSelecionada);
    }

    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);

    if ($resultado) {
        while ($funcionario = mysqli_fetch_assoc($resultado)) {
            $funcionarios[] = $funcionario;
        }

        mysqli_free_result($resultado);
    }

    mysqli_stmt_close($stmt);

    return $funcionarios;
}

$estadoFuncionario = '';
if (isset($conn) && $conn instanceof mysqli) {
    $estadoFuncionario = buscarEstadoFuncionario($conn, $databaseName, (string) $matriculaLogada);
}

$unidadePostada = $_POST['unidade'] ?? '';
$unidadeSelecionada = in_array($unidadePostada, $unidades, true) ? $unidadePostada : ($estadoFuncionario ?: 'TODOS');

if (!in_array($unidadeSelecionada, $unidades, true)) {
    $unidadeSelecionada = 'TODOS';
}

if (isset($conn) && $conn instanceof mysqli) {
    $funcionarios = buscarFuncionariosSemCnh($conn, $databaseName, $unidadeSelecionada);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="FFA" />
    <meta name="author" content="FFA" />
    <title>Listagem Funcionários</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.css">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body { background: #ffffff; color: #212529; font-size: 14px; }
        .content-container { width: 80%; }
        .filter-area { margin-top: 24px; }
        .register-action { padding-left: 65px; }
        .icon-button { border: none; background-color: transparent; }
        @media (max-width: 992px) { .content-container { width: 100%; } }
    </style>
</head>
<body class="sb-nav-fixed vsc-initialized sb-sidenav-toggled">
    <?php autofrotaMenu(); ?>

    <div id="layoutSidenav_content">
        <main style="width: 100%;" class="mb-2">
            <div class="container-fluid px-4 content-container">
                <h1 class="mt-2">Colaboradores sem CNH</h1>

                <section class="filter-area col-6">
                    <form action="./funcionarios.php" method="post">
                        <div class="d-flex justify-content-start align-items-end">
                            <div>
                                <label class="form-label fw-bold" for="unidade">Selecione unidade do colaborador:</label>
                                <select class="form-select" name="unidade" id="unidade">
                                    <?php foreach ($unidades as $unidade): ?>
                                        <option value="<?= htmlspecialchars($unidade) ?>" <?= $unidadeSelecionada === $unidade ? 'selected' : '' ?>><?= htmlspecialchars($unidade) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <button type="submit" class="mt-1 ms-3 btn btn-success">Filtrar</button>
                            </div>
                        </div>
                    </form>
                </section>

                <div class="mt-3 alert alert-info" role="alert">
                    <strong>Filtro Aplicado:</strong> Exibindo funcionários do estado: <strong><?= htmlspecialchars($unidadeSelecionada === 'TODOS' ? 'TODOS OS ESTADOS' : $unidadeSelecionada) ?></strong>
                </div>

                <div style="width: 100%;" class="mt-4 m-auto">
                    <table id="tabela1" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Matrícula</th>
                                <th>Nome</th>
                                <th>Centro de Custo</th>
                                <th>Unidade</th>
                                <th>Cadastrar CNH</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($funcionarios as $funcionario): ?>
                                <tr>
                                    <td><?= htmlspecialchars($funcionario['matricula'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($funcionario['nome'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($funcionario['ccusto'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($funcionario['estado'] ?? '') ?></td>
                                    <td class="register-action">
                                        <form method="post" action="cadastrocnh.php">
                                            <input type="hidden" name="matricula" value="<?= htmlspecialchars($funcionario['matricula'] ?? '') ?>">
                                            <input type="hidden" name="matr_autor" value="<?= htmlspecialchars((string) $matriculaLogada) ?>">
                                            <button class="icon-button" type="submit" title="Cadastrar CNH">
                                                <span class="material-symbols-outlined">problem</span>
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
                                <th>Centro de Custo</th>
                                <th>Unidade</th>
                                <th>Cadastrar CNH</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-3">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href = 'listagem-condutor.php';">Voltar</button>
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

<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.3/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', function(event) {
        event.preventDefault();
        document.body.classList.toggle('sb-sidenav-toggled');
    });

    var width = $(window).width();
    if (width <= 768) {
        $(document).ready(function() {
            $('#tabela1').DataTable({
                responsive: true,
                order: [[1, 'asc']]
            });
        });
    } else {
        $(document).ready(function() {
            $('#tabela1').DataTable({
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
            });
        });
    }
</script>
</body>
</html>
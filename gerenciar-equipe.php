<?php
require_once __DIR__ . '/includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$usuarioLogado = (string) ($autofrotaSessao['usuario'] ?? $_SESSION['usuario'] ?? 'Usuário');
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');
if ($databaseName === '') {
    $databaseName = 'bdautofrotas';
}

/** @var mysqli|null $conn */
$conn = $autofrotaSessao['conn'] ?? null;

function escEquipe(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

function consultaAssoc(mysqli $conn, string $sql, string &$erro = ''): array
{
    $dados = [];
    $resultado = mysqli_query($conn, $sql);

    if ($resultado === false) {
        $erro = mysqli_error($conn);
        return $dados;
    }

    while ($linha = mysqli_fetch_assoc($resultado)) {
        $dados[] = $linha;
    }

    mysqli_free_result($resultado);
    return $dados;
}

$gerenteSelecionado = isset($_GET['gerente']) ? (int) $_GET['gerente'] : 0;

$gerentes = [];
$coordenadores = [];
$supervisores = [];
$tecnicos = [];
$erroConsulta = '';

if ($conn instanceof mysqli) {
    $sqlGerentes = "
        SELECT DISTINCT g.idtbgerente, u.matricula, u.nome, '' AS ccusto
        FROM {$databaseName}.tbequipe_gerente g
        INNER JOIN {$databaseName}.tbusuario u ON u.matricula = g.matricula
        ORDER BY u.nome
    ";
    $gerentes = consultaAssoc($conn, $sqlGerentes, $erroConsulta);

    if ($gerenteSelecionado > 0) {
        $sqlCoordenadores = "
            SELECT c.idtbcoordenador, u.matricula, u.nome, 'Coordenador' AS cargo, ug.nome AS lider
            FROM {$databaseName}.tbequipe_coordenador c
            INNER JOIN {$databaseName}.tbusuario u ON u.matricula = c.matricula
            LEFT JOIN {$databaseName}.tbequipe_gerente g ON g.idtbgerente = c.idtbgerente
            LEFT JOIN {$databaseName}.tbusuario ug ON ug.matricula = g.matricula
            WHERE c.idtbgerente = {$gerenteSelecionado}
            ORDER BY u.nome
        ";
        $coordenadores = consultaAssoc($conn, $sqlCoordenadores, $erroConsulta);

        $sqlSupervisores = "
            SELECT s.idtbsupervisor, u.matricula, u.nome, 'Supervisor' AS cargo, uc.nome AS lider
            FROM {$databaseName}.tbequipe_supervisor s
            INNER JOIN {$databaseName}.tbusuario u ON u.matricula = s.matricula
            INNER JOIN {$databaseName}.tbequipe_coordenador c ON c.idtbcoordenador = s.idtbcoordenador
            LEFT JOIN {$databaseName}.tbusuario uc ON uc.matricula = c.matricula
            WHERE c.idtbgerente = {$gerenteSelecionado}
            ORDER BY u.nome
        ";
        $supervisores = consultaAssoc($conn, $sqlSupervisores, $erroConsulta);

        $sqlTecnicos = "
            SELECT DISTINCT t.matricula, t.nome, 'Técnico' AS cargo, us.nome AS lider
                                                FROM {$databaseName}.tbusuario t
                                                INNER JOIN {$databaseName}.tbequipe_supervisor s ON s.idtbsupervisor = t.idtbsupervisor
                                                INNER JOIN {$databaseName}.tbequipe_coordenador c ON c.idtbcoordenador = s.idtbcoordenador
                                                LEFT JOIN {$databaseName}.tbusuario us ON us.matricula = s.matricula
            WHERE c.idtbgerente = {$gerenteSelecionado}
              AND t.perfil = 0
            ORDER BY t.nome
        ";
        $tecnicos = consultaAssoc($conn, $sqlTecnicos, $erroConsulta);
    }
} else {
    $erroConsulta = 'Conexão com banco não disponível.';
}

$totalCoordenadores = count($coordenadores);
$totalSupervisores = count($supervisores);
$totalTecnicos = count($tecnicos);
$mostrarHierarquia = ($gerenteSelecionado > 0);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="AutoFrota" />
    <meta name="author" content="FFA" />
    <title>AutoFrota - Hierarquia da Frota</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body { background: #f3f6fc; color: #212529; font-size: 14px; }
        #layoutSidenav_content { padding: 14px 12px 0; }
        .page-wrapper { max-width: 1400px; margin: 0 auto; }
        .hero-card, .content-card { border: 1px solid #e5e7eb; border-radius: 14px; box-shadow: 0 10px 28px rgba(15, 23, 42, .06); }
        .hero-card { background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%); }
        .metric-card { border: 1px solid #e9ecef; border-radius: 12px; background: #fff; }
        .metric-icon { width: 42px; height: 42px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; background: #e7f1ff; color: #0d6efd; }
        .table thead th { background: #f8fafc; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
    </style>
</head>
<body class="sb-nav-fixed">
    <?php autofrotaMenu(); ?>

    <div id="layoutSidenav_content">
        <main class="page-wrapper py-2">
            <section class="hero-card p-4 mb-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                    <div>
                        <h1 class="h3 mb-2">Visualizar Hierarquia</h1>
                    </div>
                    <div class="text-lg-end text-muted small">Usuário: <?= escEquipe($usuarioLogado) ?></div>
                </div>
            </section>

            <section class="content-card card mb-4">
                <div class="card-header bg-white fw-semibold"><i class="fas fa-filter me-2"></i>Selecionar Gerente</div>
                <div class="card-body">
                    <form class="row g-3 align-items-end" action="" method="get">
                        <div class="col-12 col-lg-7">
                            <label class="form-label" for="gerente">Gerente</label>
                            <select id="gerente" name="gerente" class="form-select">
                                <option value="">Selecione um gerente...</option>
                                <?php foreach ($gerentes as $gerente): ?>
                                    <?php
                                    $idGerente = (int) ($gerente['idtbgerente'] ?? 0);
                                    $nome = (string) ($gerente['nome'] ?? 'Sem nome');
                                    $mat = (string) ($gerente['matricula'] ?? '-');
                                    $ccusto = (string) ($gerente['ccusto'] ?? '');
                                    $texto = $nome . ' (Mat: ' . $mat . ')';
                                    if ($ccusto !== '') {
                                        $texto .= ' - ' . $ccusto;
                                    }
                                    ?>
                                    <option value="<?= $idGerente ?>" <?= $idGerente === $gerenteSelecionado ? 'selected' : '' ?>><?= escEquipe($texto) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-lg-5 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search me-2"></i>Visualizar Hierarquia</button>
                            <button type="button" class="btn btn-success" onclick="exportarHierarquiaExcel(this)" data-export-url="control/gerarexcelequipe.php"><i class="fas fa-file-excel me-2"></i>Exportar Excel</button>
                        </div>
                    </form>

                    <?php if ($erroConsulta !== ''): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <strong>Atenção:</strong> <?= escEquipe($erroConsulta) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="row g-3 mb-4">
                <div class="col-12 col-md-4"><div class="metric-card p-3"><span class="metric-icon mb-2"><i class="fas fa-user-tie"></i></span><h2 class="h5 mb-0"><?= $totalCoordenadores ?> Coordenadores</h2><small class="text-muted">Vinculados ao gerente selecionado</small></div></div>
                <div class="col-12 col-md-4"><div class="metric-card p-3"><span class="metric-icon mb-2"><i class="fas fa-users"></i></span><h2 class="h5 mb-0"><?= $totalSupervisores ?> Supervisores</h2><small class="text-muted">Distribuídos por coordenação</small></div></div>
                <div class="col-12 col-md-4"><div class="metric-card p-3"><span class="metric-icon mb-2"><i class="fas fa-hard-hat"></i></span><h2 class="h5 mb-0"><?= $totalTecnicos ?> Técnicos</h2><small class="text-muted">Associados aos supervisores</small></div></div>
            </section>

            <?php if (!$mostrarHierarquia): ?>
                <section class="content-card card mb-4">
                    <div class="card-body">
                        <div class="alert alert-info mb-0">Selecione um gerente para visualizar a hierarquia.</div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($mostrarHierarquia): ?>
                <?php $tabelas = [['Lista de Coordenadores', 'fa-user-tie', $coordenadores, 'Gerente'], ['Lista de Supervisores', 'fa-users', $supervisores, 'Coordenador'], ['Lista de Técnicos', 'fa-hard-hat', $tecnicos, 'Supervisor']]; ?>
                <?php foreach ($tabelas as [$titulo, $icone, $linhas, $liderTitulo]): ?>
                    <section class="content-card card mb-4">
                        <div class="card-header bg-white fw-semibold"><i class="fas <?= escEquipe($icone) ?> me-2"></i><?= escEquipe($titulo) ?></div>
                        <div class="card-body table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0 hierarquia-table">
                                <thead><tr><th>Matrícula</th><th>Nome</th><th>Cargo</th><th><?= escEquipe($liderTitulo) ?></th></tr></thead>
                                <tbody>
                                <?php if (empty($linhas)): ?>
                                    <tr><td colspan="4" class="text-center text-muted">Nenhum registro encontrado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($linhas as $linha): ?>
                                        <tr>
                                            <td><?= escEquipe((string) ($linha['matricula'] ?? '')) ?></td>
                                            <td><?= escEquipe((string) ($linha['nome'] ?? '')) ?></td>
                                            <td><?= escEquipe((string) ($linha['cargo'] ?? '')) ?></td>
                                            <td><?= escEquipe((string) ($linha['lider'] ?? '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function () {
            if ($.fn.DataTable) {
                $('.hierarquia-table').DataTable({
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
                    order: [[1, 'asc']],
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
                    }
                });
            }
        });

        document.getElementById('sidebarToggle')?.addEventListener('click', function (e) {
            e.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
        });
    </script>

    <script>
        function exportarHierarquiaExcel(botao) {
            const btnExportar = botao || document.querySelector('[data-export-url]');
            if (!btnExportar) {
                return;
            }

            const textoOriginal = btnExportar.innerHTML;
            btnExportar.disabled = true;
            btnExportar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Gerando Excel...';

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = btnExportar.dataset.exportUrl || 'control/gerarexcelequipe.php';

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'action';
            input.value = 'exportar_hierarquia_completa';
            form.appendChild(input);

            document.body.appendChild(form);
            form.submit();
            form.remove();

            window.setTimeout(function () {
                btnExportar.disabled = false;
                btnExportar.innerHTML = textoOriginal;
            }, 3000);
        }
    </script>
</body>
</html>
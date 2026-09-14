<?php
require_once __DIR__ . '/../includes/autofrota_common.php';
$autofrotaSessao = autofrotaInit();

$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');
$perfilLogado = trim((string) ($autofrotaSessao['perfil'] ?? $_SESSION['perfil'] ?? ''));
$veiculos = [];
$vistorias = [];
$erroVeiculos = '';
$erroVistorias = '';

if ($perfilLogado !== '4') {
    http_response_code(403);
    exit('Acesso permitido apenas para perfil 4.');
}

$placa = strtoupper(trim((string) ($_POST['placa'] ?? '')));
$placa = preg_replace('/[^A-Z0-9]/', '', $placa) ?? '';

if ($conn instanceof mysqli && preg_match('/^[a-zA-Z0-9_]+$/', $databaseName) === 1) {
    $sqlVeiculos = "SELECT DISTINCT placa FROM `{$databaseName}`.`tbveiculo` WHERE placa IS NOT NULL AND placa <> '' ORDER BY placa";
    $resultadoVeiculos = mysqli_query($conn, $sqlVeiculos);

    if ($resultadoVeiculos instanceof mysqli_result) {
        while ($veiculo = mysqli_fetch_assoc($resultadoVeiculos)) {
            $veiculos[] = $veiculo;
        }
        mysqli_free_result($resultadoVeiculos);
    } else {
        $erroVeiculos = 'Não foi possível carregar as placas. Tente novamente.';
    }

    if ($placa !== '') {
        $sqlVistorias = "SELECT v.idtbvistoria, v.datavistoria, v.placa, v.assinadocond,
                                v.tipo, v.nome, COALESCE(tv.tipo, v.tipo) AS tipo_vistoria,
                                EXISTS(
                                    SELECT 1
                                      FROM `{$databaseName}`.`tbvistoriafotos` vf
                                     WHERE vf.idtbvistoria = v.idtbvistoria
                                       AND vf.idtbvistfotos NOT IN (40, 41, 42, 43, 44, 45, 47)
                                       AND (COALESCE(vf.frontal, '') <> '' OR COALESCE(vf.traseira, '') <> ''
                                         OR COALESCE(vf.direita, '') <> '' OR COALESCE(vf.esquerda, '') <> ''
                                         OR COALESCE(vf.bateria, '') <> '' OR COALESCE(vf.painel, '') <> ''
                                         OR COALESCE(vf.selfie, '') <> '' OR COALESCE(vf.cnh, '') <> ''
                                         OR COALESCE(vf.extra1, '') <> '' OR COALESCE(vf.extra2, '') <> ''
                                         OR COALESCE(vf.extra3, '') <> '' OR COALESCE(vf.extra4, '') <> ''
                                         OR COALESCE(vf.extra5, '') <> '')
                                ) AS possui_fotos
                           FROM `{$databaseName}`.`tbvistoria` v
                      LEFT JOIN `{$databaseName}`.`tbatipovist` tv ON tv.idtbatipovist = v.tipo
                          WHERE REPLACE(REPLACE(UPPER(v.placa), '-', ''), ' ', '') = ?
                            AND v.placa <> ''
                            AND REPLACE(REPLACE(UPPER(v.placa), '-', ''), ' ', '') NOT IN ('ABC1234', 'ABC1245', 'ABC1122')
                            AND (v.idtbvistoria < 2995 OR v.idtbvistoria > 3015)
                            AND v.idtbvistoria NOT IN (3028, 3031, 3032, 4719, 4718)
                            AND v.statusreg = 1
                       ORDER BY v.datavistoria DESC";
        $stmtVistorias = mysqli_prepare($conn, $sqlVistorias);

        if ($stmtVistorias) {
            mysqli_stmt_bind_param($stmtVistorias, 's', $placa);
            if (mysqli_stmt_execute($stmtVistorias)) {
                $resultadoVistorias = mysqli_stmt_get_result($stmtVistorias);
                if ($resultadoVistorias instanceof mysqli_result) {
                    while ($vistoria = mysqli_fetch_assoc($resultadoVistorias)) {
                        $vistorias[] = $vistoria;
                    }
                    mysqli_free_result($resultadoVistorias);
                }
            } else {
                $erroVistorias = 'Não foi possível consultar as vistorias. Tente novamente.';
            }
            mysqli_stmt_close($stmtVistorias);
        } else {
            $erroVistorias = 'Não foi possível preparar a consulta das vistorias.';
        }
    }
} else {
    $erroVeiculos = 'Não foi possível conectar à base de veículos.';
    if ($placa !== '') {
        $erroVistorias = 'Não foi possível conectar à base de vistorias.';
    }
}

$formatarData = static function (string $data): string {
    $timestamp = strtotime($data);
    return $timestamp !== false ? date('d/m/Y H:i:s', $timestamp) : $data;
};
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="Relatórios de vistoria por placa" />
    <meta name="author" content="FFA" />
    <title>Relatórios por Placa - AutoFrota</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body {
            background: #f5f7fb;
            color: #212529;
            font-size: 14px;
        }

        .report-page {
            max-width: 1280px;
            margin: 0 auto;
            padding: 12px 16px 32px;
        }

        .page-heading h1 {
            font-size: 28px;
            font-weight: 700;
        }

        .page-heading p {
            color: #64748b;
            font-size: 15px;
        }

        .report-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
        }

        .filter-card {
            padding: 24px;
        }

        .form-label {
            font-weight: 600;
        }

        .filter-row {
            align-items: flex-start;
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
        }

        .filter-field {
            width: 100%;
        }

        .filter-action {
            align-self: flex-end;
            display: flex;
            justify-content: flex-end;
            width: 100%;
            margin-top: 0;
            margin-bottom: 0;
        }

        .filter-action .btn {
            min-height: calc(2.5rem + 2px);
            white-space: nowrap;
        }

        .filter-field .select2-container {
            width: 100% !important;
        }

        .required-mark {
            color: #dc3545;
        }

        .table-card {
            overflow: hidden;
        }

        .table-card .table {
            margin-bottom: 0;
        }

        .table-card thead th {
            background: #f8fafc;
            border-bottom-color: #e2e8f0;
            color: #475569;
            font-size: 12px;
            letter-spacing: .03em;
            padding: 14px 16px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .empty-state {
            padding: 56px 24px !important;
            text-align: center;
        }

        .empty-state-icon {
            align-items: center;
            background: #e7f1ff;
            border-radius: 50%;
            color: #0d6efd;
            display: inline-flex;
            font-size: 24px;
            height: 52px;
            justify-content: center;
            margin-bottom: 14px;
            width: 52px;
        }
    </style>
</head>

<body>
    <?php autofrotaMenu(); ?>

    <main class="report-page">
        <header class="page-heading mb-4">
            <h1 class="mb-2">Relatórios por Placa</h1>
            <!-- <p class="mb-0">Insira a placa do veículo para visualizar seus relatórios de vistoria.</p> -->
        </header>

        <section class="report-card filter-card mb-4" aria-labelledby="titulo-filtros">
            <h2 class="h5 mb-3" id="titulo-filtros">Consultar veículo</h2>
            <form method="post" action="relatorio-vistoria-por-placa.php">
                <div class="filter-row">
                    <div class="filter-field">
                        <label class="form-label" for="placa">Placa <span class="required-mark" aria-hidden="true">*</span></label>
                        <select name="placa" id="placa" class="form-select" required>
                            <option value="" selected disabled>Selecione uma opção</option>
                            <?php foreach ($veiculos as $veiculo): ?>
                                <?php
                                $placaExibicao = strtoupper(trim((string) ($veiculo['placa'] ?? '')));
                                $placaSelecionada = preg_replace('/[^A-Z0-9]/', '', $placaExibicao) ?? '';
                                ?>
                                <option value="<?= htmlspecialchars($placaSelecionada, ENT_QUOTES, 'UTF-8') ?>" <?= $placa !== '' && $placa === $placaSelecionada ? 'selected' : '' ?>><?= htmlspecialchars($placaExibicao, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" id="placa-ajuda">Selecione a placa para consultar o histórico de vistorias.</div>
                    </div>
                    <div class="filter-action">
                        <button class="btn btn-success px-4" type="submit">
                            <i class="fas fa-magnifying-glass me-2"></i>Visualizar relatórios
                        </button>
                    </div>
                </div>
                <p class="small text-danger mb-0 mt-3">* Campo obrigatório.</p>
            </form>

            <?php if ($erroVeiculos !== ''): ?>
                <div class="mt-3 alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-1"></i><?= htmlspecialchars($erroVeiculos, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
        </section>

        <section aria-labelledby="titulo-resultados">
            <div class="d-flex flex-column align-items-end gap-2 mb-3">
                <h2 class="h5 mb-0 w-100" id="titulo-resultados">Relatórios de vistoria</h2>
                <form action="control/exportar-vistorias-placa.php" method="post">
                    <input type="hidden" name="placa" value="<?= htmlspecialchars($placa, ENT_QUOTES, 'UTF-8') ?>">
                    <button class="btn btn-outline-success" type="submit" <?= $placa === '' ? 'disabled' : '' ?>>
                        <i class="fas fa-file-excel me-2"></i>Gerar relatório Excel
                    </button>
                </form>
            </div>

            <div class="report-card table-card table-responsive">
                <table class="table table-hover align-middle" aria-describedby="mensagem-resultados">
                    <thead>
                        <tr>
                            <th scope="col">Relatório</th>
                            <th scope="col">Data</th>
                            <th scope="col">Tipo de vistoria</th>
                            <th scope="col">Condutor</th>
                            <th scope="col" class="text-center">Assinado pelo condutor?</th>
                            <th scope="col"><span class="visually-hidden">PDF</span></th>
                            <th scope="col"><span class="visually-hidden">Fotos</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vistorias as $vistoria): ?>
                            <?php
                            $idVistoria = (int) ($vistoria['idtbvistoria'] ?? 0);
                            $placaVistoria = strtoupper(trim((string) ($vistoria['placa'] ?? '')));
                            $dataVistoria = (string) ($vistoria['datavistoria'] ?? '');
                            ?>
                            <tr>
                                <td>Placa <?= htmlspecialchars($placaVistoria, ENT_QUOTES, 'UTF-8') ?></td>
                                <td data-order="<?= htmlspecialchars((string) strtotime($dataVistoria), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($formatarData($dataVistoria), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($vistoria['tipo_vistoria'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($vistoria['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-center"><?= (string) ($vistoria['assinadocond'] ?? '') === '1' ? 'Sim' : 'Não' ?></td>
                                <td><a class="btn btn-success btn-sm text-nowrap" href="verrelatorio.php?id=<?= $idVistoria ?>" target="_blank" rel="noopener"><i class="fas fa-file-pdf me-1"></i>Visualizar relatório</a></td>
                                <td>
                                    <a class="btn btn-primary btn-sm text-nowrap" href="verfotos.php?id=<?= $idVistoria ?>" target="_blank" rel="noopener"><i class="fas fa-images me-1"></i>Ver fotos</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$vistorias): ?>
                        <tr>
                            <td class="empty-state" colspan="7" id="mensagem-resultados">
                                <span class="empty-state-icon"><i class="fas fa-clipboard-list"></i></span>
                                <h3 class="h6 mb-2">Nenhum relatório para exibir</h3>
                                <p class="text-muted mb-0"><?= $placa === '' ? 'Informe uma placa acima para iniciar a consulta.' : 'Nenhuma vistoria salva foi encontrada para a placa informada.' ?></p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($erroVistorias !== ''): ?>
                <div class="mt-3 alert alert-danger" role="alert"><i class="fas fa-exclamation-triangle me-1"></i><?= htmlspecialchars($erroVistorias, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </section>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(function () {
            $('#placa').select2({
                theme: 'bootstrap-5',
                placeholder: 'Digite ou selecione uma placa',
                width: '100%',
                language: {
                    noResults: function () { return 'Nenhuma placa encontrada'; },
                    searching: function () { return 'Buscando...'; }
                }
            });
        });
    </script>
</body>

</html>
<?php
require_once __DIR__ . '/../includes/autofrota_common.php';
$autofrotaSessao = autofrotaInit();

$perfilLogado = trim((string) ($autofrotaSessao['perfil'] ?? $_SESSION['perfil'] ?? ''));
if ($perfilLogado !== '4') {
    http_response_code(403);
    exit('Acesso permitido apenas para perfil 4.');
}

$placa = strtoupper(trim((string) ($_POST['placa'] ?? '')));
$placa = preg_replace('/[^A-Z0-9]/', '', $placa) ?? '';
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
            <p class="mb-0">Insira a placa do veículo para visualizar seus relatórios de vistoria.</p>
        </header>

        <section class="report-card filter-card mb-4" aria-labelledby="titulo-filtros">
            <h2 class="h5 mb-3" id="titulo-filtros">Consultar veículo</h2>
            <form method="post" action="gerarrelatorio.php">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-6 col-lg-5">
                        <label class="form-label" for="placa">Placa <span class="required-mark" aria-hidden="true">*</span></label>
                        <input
                            class="form-control text-uppercase"
                            id="placa"
                            name="placa"
                            type="text"
                            maxlength="8"
                            placeholder="Ex.: ABC1D23"
                            value="<?= htmlspecialchars($placa) ?>"
                            autocomplete="off"
                            aria-describedby="placa-ajuda"
                            required
                        >
                        <div class="form-text" id="placa-ajuda">Digite a placa no formato antigo ou Mercosul.</div>
                    </div>
                    <div class="col-12 col-md-auto">
                        <button class="btn btn-success px-4" type="submit">
                            <i class="fas fa-magnifying-glass me-2"></i>Visualizar relatórios
                        </button>
                    </div>
                </div>
                <p class="small text-danger mb-0 mt-3">* Campo obrigatório.</p>
            </form>
        </section>

        <section aria-labelledby="titulo-resultados">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h5 mb-0" id="titulo-resultados">Relatórios de vistoria</h2>
                <button class="btn btn-outline-success" type="button" disabled title="Disponível após a integração dos dados">
                    <i class="fas fa-file-excel me-2"></i>Gerar relatório Excel
                </button>
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
                        <tr>
                            <td class="empty-state" colspan="7" id="mensagem-resultados">
                                <span class="empty-state-icon"><i class="fas fa-clipboard-list"></i></span>
                                <h3 class="h6 mb-2">Nenhum relatório para exibir</h3>
                                <p class="text-muted mb-0">Informe uma placa acima para iniciar a consulta.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('placa')?.addEventListener('input', function () {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
        });
    </script>
</body>

</html>
<?php
require_once __DIR__ . '/includes/autofrota_common.php';
$autofrotaSessao = autofrotaInit();

$perfilLogado = (string) ($autofrotaSessao['perfil'] ?? $_SESSION['perfil'] ?? '0');
$matriculaLogada = trim((string) ($autofrotaSessao['matricula'] ?? $_SESSION['matricula'] ?? ''));

if ($perfilLogado === '0') {
    header('Location: tecnico.php');
    exit;
}

if ($perfilLogado === '2') {
    header('Location: coordenador/aprovacao-cotas.php');
    exit;
}

if ($perfilLogado === '3') {
    header('Location: gerente/aprovacao-cotas.php');
    exit;
}

if ($perfilLogado === '10') {
    header('Location: diretor/aprovar-orcamento-complementar.php');
    exit;
}

if (!function_exists('autofrotaNomePorMatricula')) {
    function autofrotaNomePorMatricula($matricula)
    {
        $matricula = trim((string) $matricula);

        if ($matricula === '') {
            return '';
        }

        $conexoes = array(
            $GLOBALS['connBdFrota'] ?? null,
            $GLOBALS['conn'] ?? null,
            $GLOBALS['mysqli'] ?? null,
            $GLOBALS['conexao'] ?? null,
        );

        $bancoAtual = '';
        if (isset($GLOBALS['databaseName']) && is_string($GLOBALS['databaseName']) && $GLOBALS['databaseName'] !== '') {
            $bancoAtual = $GLOBALS['databaseName'];
        } elseif (isset($GLOBALS['database']) && is_string($GLOBALS['database']) && $GLOBALS['database'] !== '') {
            $bancoAtual = $GLOBALS['database'];
        }

        $usarSchema = preg_match('/^[a-zA-Z0-9_]+$/', $bancoAtual) === 1;
        $sql = $usarSchema
            ? 'SELECT nome FROM `' . $bancoAtual . '`.tbfuncionario WHERE matricula = ? LIMIT 1'
            : 'SELECT nome FROM tbfuncionario WHERE matricula = ? LIMIT 1';

        foreach ($conexoes as $conexao) {
            if ($conexao instanceof mysqli) {
                $stmt = mysqli_prepare($conexao, $sql);

                if (!$stmt) {
                    continue;
                }

                mysqli_stmt_bind_param($stmt, 's', $matricula);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $nome);

                $nomeEncontrado = '';
                if (mysqli_stmt_fetch($stmt)) {
                    $nomeEncontrado = trim((string) $nome);
                }

                mysqli_stmt_close($stmt);

                if ($nomeEncontrado !== '') {
                    return $nomeEncontrado;
                }
            }
        }

        return '';
    }
}

if ($perfilLogado === '0' || $perfilLogado === '') {
    http_response_code(403);
    exit('Sem permissão.');
}

$usuarioLogado = autofrotaNomePorMatricula($matriculaLogada);
if ($usuarioLogado === '') {
    $usuarioLogado = (string) ($autofrotaSessao['usuario'] ?? $_SESSION['usuario'] ?? 'Usuário');
}
$linksPortal = [
    [
        'titulo' => 'Condutores',
        'descricao' => 'Consulte e filtre a listagem de condutores.',
        'url' => 'condutores/listar_condutorespj.php',
        'icone' => 'fa-id-card',
    ],
    [
        'titulo' => 'Veículos Cadastrados',
        'descricao' => 'Acesse os veículos cadastrados e seus filtros de gestão.',
        'url' => 'veiculos/listagem-veiculo.php',
        'icone' => 'fa-car',
    ],
    [
        'titulo' => 'Registros de Manutenção',
        'descricao' => 'Visualize e filtre registros de manutenção da frota.',
        'url' => 'manutencoes/listagem-manutencao.php',
        'icone' => 'fa-screwdriver-wrench',
    ],
    [
        'titulo' => 'Multas da Frota',
        'descricao' => 'Acompanhe, consulte e filtre as multas registradas para a frota.',
        'url' => 'multa/multasfrota.php',
        'icone' => 'fa-file-invoice-dollar',
    ],
    [
        'titulo' => 'Cadastrar',
        'icone' => 'fa-square-plus',
        'links' => [
            [
                'titulo' => 'Condutor',
                'url' => 'condutores/cadastrar_condutorespj.php',
            ],
            [
                'titulo' => 'Veículo',
                'url' => 'veiculos/cadastroveiculo.php',
            ],
            [
                'titulo' => 'Manutenção',
                'url' => 'manutencoes/solicitar-manutencao-preventiva.php',
            ],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="AutoFrota" />
    <meta name="author" content="FFA" />
    <title>AutoFrota - Início</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body {
            background: linear-gradient(180deg, #f3f6fc 0%, #eef2f8 100%);
            color: #212529;
            font-size: 14px;
        }

        #layoutSidenav_content {
            padding: 14px 12px 0;
        }

        .page-wrapper {
            max-width: 1280px;
            margin: 0 auto;
        }

        .welcome-hero {
            background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, .08);
        }

        .welcome-hero h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .welcome-hero p {
            font-size: 16px;
            margin-bottom: 0;
            color: #495057;
        }

        .portal-card {
            height: 100%;
            backdrop-filter: blur(2px);
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            transition: transform .15s ease, box-shadow .15s ease;
            text-decoration: none;
            color: inherit;
        }

        .portal-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(15, 23, 42, .08);
            color: inherit;
        }

        .portal-icon {
            box-shadow: inset 0 0 0 1px rgba(13, 110, 253, .14);
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #e7f1ff;
            color: #0d6efd;
            font-size: 20px;
            margin-bottom: 14px;
        }

        .portal-links {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .portal-links a {
            font-weight: 600;
            text-decoration: none;
        }
    </style>
</head>

<body class="sb-nav-fixed">
    <?php autofrotaMenu(); ?>

    <div id="layoutSidenav_content">
        <main class="page-wrapper py-2">
            <section class="welcome-hero mb-4">
                <br>
                <h1>Seja bem-vindo, <?= htmlspecialchars($usuarioLogado) ?>, ao Portal AutoFrota.</h1>
                <p>Confira os módulos disponíveis e acesse rapidamente os principais recursos de
                    operação da AutoFrota.</p>
            </section>

            <section class="row g-3" aria-label="Links disponíveis no AutoFrota">
                <?php foreach ($linksPortal as $link): ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <?php if (isset($link['links'])): ?>
                            <div class="card portal-card">
                                <div class="card-body">
                                    <span class="portal-icon"><i class="fas <?= htmlspecialchars($link['icone']) ?>"></i></span>
                                    <h2 class="h5 card-title mb-2"><?= htmlspecialchars($link['titulo']) ?></h2>
                                    <nav class="portal-links" aria-label="Opções de cadastro">
                                        <?php foreach ($link['links'] as $cadastro): ?>
                                            <a class="btn btn-outline-primary" href="<?= htmlspecialchars($cadastro['url']) ?>">
                                                Cadastrar <?= htmlspecialchars($cadastro['titulo']) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </nav>
                                </div>
                            </div>
                        <?php else: ?>
                            <a class="card portal-card" href="<?= htmlspecialchars($link['url']) ?>">
                                <div class="card-body">
                                    <span class="portal-icon"><i class="fas <?= htmlspecialchars($link['icone']) ?>"></i></span>
                                    <h2 class="h5 card-title mb-2"><?= htmlspecialchars($link['titulo']) ?></h2>
                                    <p class="card-text text-muted"><?= htmlspecialchars($link['descricao']) ?></p>
                                </div>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </section>
        </main>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', function (event) {
            event.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
        });
    </script>
</body>

</html>
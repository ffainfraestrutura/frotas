<?php
$usuarioMenu = $_SESSION['usuario'] ?? $_SESSION['nome'] ?? 'Usuário';
$perfilMenu = (string) ($_SESSION['perfil'] ?? '');
$matriculaMenu = (string) ($_SESSION['matricula'] ?? '');
$databaseNameMenu = (string) ($GLOBALS['databaseName'] ?? $GLOBALS['database'] ?? 'bdautofrotas');
$connMenu = $GLOBALS['conn'] ?? $GLOBALS['con'] ?? null;

$homeMenu = $perfilMenu === '0' ? 'tecnico.php' : 'index.php';
$homeMenuTitulo = $perfilMenu === '0' ? 'Solicitar combustível' : 'Início';
$homeMenuIcone = $perfilMenu === '0' ? 'fa-gas-pump' : 'fa-house';

if ($connMenu instanceof mysqli && $matriculaMenu !== '') {
    $sqlUsuarioMenu = "SELECT nome FROM `{$databaseNameMenu}`.`tbusuario` WHERE matricula = ? LIMIT 1";
    $stmtUsuarioMenu = mysqli_prepare($connMenu, $sqlUsuarioMenu);
    if ($stmtUsuarioMenu) {
        mysqli_stmt_bind_param($stmtUsuarioMenu, 's', $matriculaMenu);
        if (mysqli_stmt_execute($stmtUsuarioMenu)) {
            $resultadoUsuarioMenu = mysqli_stmt_get_result($stmtUsuarioMenu);
            if ($resultadoUsuarioMenu instanceof mysqli_result) {
                $linhaUsuarioMenu = mysqli_fetch_assoc($resultadoUsuarioMenu) ?: [];
                $nomeBancoMenu = trim((string) ($linhaUsuarioMenu['nome'] ?? ''));
                if ($nomeBancoMenu !== '') {
                    $usuarioMenu = $nomeBancoMenu;
                }
                mysqli_free_result($resultadoUsuarioMenu);
            }
        }
        mysqli_stmt_close($stmtUsuarioMenu);
    }
}

$baseAutofrotaUrl = 'https://ffasip.ddns.net:4747/autofrota/';
$paginaAtual = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '';
$paginaAtual = preg_replace('#^/+#', '/', $paginaAtual);
$paginaAtual = str_starts_with($paginaAtual, '/autofrota/') ? $paginaAtual : '/autofrota/' . ltrim($paginaAtual, '/');
$paginaAtual = rtrim($baseAutofrotaUrl, '/') . $paginaAtual;

function menuSuperiorLink(string $caminho, string $baseAutofrotaUrl): string
{
    return rtrim($baseAutofrotaUrl, '/') . '/' . ltrim($caminho, '/');
}

function menuSuperiorAtivo(string $caminho, string $paginaAtual, string $baseAutofrotaUrl): string
{
    return menuSuperiorLink($caminho, $baseAutofrotaUrl) === $paginaAtual ? ' active' : '';
}

function menuSuperiorAtivoSecao(string $prefixo, string $paginaAtual, string $baseAutofrotaUrl): string
{
    $urlPrefixo = rtrim(menuSuperiorLink($prefixo, $baseAutofrotaUrl), '/') . '/';

    return str_starts_with($paginaAtual, $urlPrefixo) ? ' active' : '';
}
?>
<style>
    body.autofrota-top-simple {
        padding-top: 80px;
        background: #f5f7fb;
    }

    .af-top-simple {
        z-index: 1039;
        margin-bottom: 12px;
    }

    .af-top-simple .navbar-brand {
        font-weight: 700;
    }

    .af-top-simple .nav-link.active {
        font-weight: 700;
        color: #fff !important;
    }

    .af-page-wrapper {
        width: 100%;
        padding: 0 12px 16px;
    }

    .af-top-simple .dropdown-item i {
        width: 18px;
    }

    .af-user-greeting {
        font-weight: 600;
        white-space: nowrap;
    }

    .af-user-dropdown .dropdown-toggle::after {
        margin-left: 0.45rem;
    }

    .af-user-dropdown .dropdown-menu {
        min-width: 210px;
    }
</style>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top af-top-simple" aria-label="Menu superior AutoFrota">
    <div class="container-fluid px-3">
        <a class="navbar-brand" href="<?= menuSuperiorLink($homeMenu, $baseAutofrotaUrl) ?>"
            target="_self">AutoFrota</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#afMenuSuperior"
            aria-controls="afMenuSuperior" aria-expanded="false" aria-label="Alternar navegação">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="afMenuSuperior">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <li class="nav-item">
                    <a class="nav-link<?= menuSuperiorAtivo($homeMenu, $paginaAtual, $baseAutofrotaUrl) ?>"
                        href="<?= menuSuperiorLink($homeMenu, $baseAutofrotaUrl) ?>" target="_self">
                        <i class="fas <?= $homeMenuIcone ?> me-1"></i><?= htmlspecialchars($homeMenuTitulo) ?>
                    </a>
                </li>

                <?php if ($perfilMenu === '2'): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= menuSuperiorAtivo('coordenador/aprovacao-cotas.php', $paginaAtual, $baseAutofrotaUrl) ?>"
                            href="<?= menuSuperiorLink('coordenador/aprovacao-cotas.php', $baseAutofrotaUrl) ?>" target="_self">
                            <i class="fas fa-check-double me-1"></i>Aprovação de cotas
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($perfilMenu === '4'): ?>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="afMenuCondutores" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-id-card me-1"></i>Condutores
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="afMenuCondutores">
                              <li><a class="dropdown-item"
                                    href="<?= menuSuperiorLink('condutores/cadastrar_condutorespj.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Cadastrar condutores</a></li>
                            <li><a class="dropdown-item"
                                    href="<?= menuSuperiorLink('condutores/listar_condutorespj.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Listagem de condutores</a></li>
                            <li><a class="dropdown-item"
                                    href="<?= menuSuperiorLink('condutores/listagem-condutor.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Listagem geral de condutores com veículos</a></li>
                            <li><a class="dropdown-item"
                                    href="<?= menuSuperiorLink('condutores/funcionarios-semcnh.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Condutores sem CNH</a></li>
                            <li><a class="dropdown-item"
                                    href="<?= menuSuperiorLink('condutores/listagemcnh.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Condutores com CNH</a></li>
                            <li><a class="dropdown-item"
                                    href="<?= menuSuperiorLink('condutores/importarcnh.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Importar CNH</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="afMenuVeiculos" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-car me-1"></i>Veículos
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="afMenuVeiculos">
                            <li><a class="dropdown-item"
                                    href="<?= menuSuperiorLink('veiculos/listagem-veiculo.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Listagem de veículos</a></li>
                            <li><a class="dropdown-item"
                                    href="<?= menuSuperiorLink('veiculos/cadastroveiculo.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Cadastro de veículo</a></li>
                            <li><a class="dropdown-item"
                                    href="<?= menuSuperiorLink('veiculos/inventario-veiculo.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Inventário de veículos</a></li>
                            <li><a class="dropdown-item"
                                    href="<?= menuSuperiorLink('veiculos/importar-hodometro.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Importar hodômetro</a></li>
                        </ul>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link<?= menuSuperiorAtivoSecao('checklist', $paginaAtual, $baseAutofrotaUrl) ?>"
                            href="<?= menuSuperiorLink('#', $baseAutofrotaUrl) ?>" target="_self">
                            <i class="fas fa-clipboard-check me-1"></i>Checklist
                        </a>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="afMenuManutencoes" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-screwdriver-wrench me-1"></i>Manutenções
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="afMenuManutencoes">
                            <li><a class="dropdown-item"
                                    href="<?= menuSuperiorLink('manutencoes/listagem-manutencao.php', $baseAutofrotaUrl) ?>"
                                    target="_self" onclick="window.location.href=this.href; return false;">Listagem de
                                    manutenções</a></li>
                            <li><a class="dropdown-item"
                                    href="<?= menuSuperiorLink('manutencoes/importar-manutencao.php', $baseAutofrotaUrl) ?>"
                                    target="_self" onclick="window.location.href=this.href; return false;">Importar
                                    manutenção</a></li>
                            <li><a class="dropdown-item"
                                    href="<?= menuSuperiorLink('manutencoes/solicitar-manutencao-preventiva.php', $baseAutofrotaUrl) ?>"
                                    target="_self" onclick="window.location.href=this.href; return false;">Adicionar
                                    manutenção</a></li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if ($perfilMenu === '4'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="afMenuMultas" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-file-circle-exclamation me-1"></i>Multas
                    </a>
                        <ul class="dropdown-menu" aria-labelledby="afMenuMultas">
                            <li><a class="dropdown-item"
                                    href="<?= menuSuperiorLink('multa/multasfrota.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Listar Multas</a></li>
                            <li><a class="dropdown-item"
                                    href="<?= menuSuperiorLink('multa/cadastromulta.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Lançamento e consulta de multas</a></li>
                            <li><a class="dropdown-item"
                                    href="<?= menuSuperiorLink('multa/importarmultasnovas.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Importar multas novas</a></li>
                        </ul>
                </li>
                <?php endif; ?>
            </ul>

            <div class="d-flex align-items-center gap-3 text-white small af-user-dropdown">
                <span class="af-user-greeting">Seja bem-vindo(a), <?= htmlspecialchars($usuarioMenu) ?></span>

                <div class="dropdown">
                    <a class="btn btn-outline-light btn-sm dropdown-toggle" href="#" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false" aria-label="Abrir menu do usuário">
                        <i class="fas fa-user"></i>
                    </a>

                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text"><?= htmlspecialchars($usuarioMenu) ?></span></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item"
                                href="<?= menuSuperiorLink('control/logout.php', $baseAutofrotaUrl) ?>" target="_self">
                                <i class="fas fa-right-from-bracket me-2"></i>Sair
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
    (function () {
        var body = document.body;
        var nav = document.querySelector('.af-top-simple');

        if (!body || !nav) {
            return;
        }

        function aplicarOffsetMenu() {
            var alturaNav = Math.ceil(nav.getBoundingClientRect().height);
            body.style.paddingTop = (alturaNav + 12) + 'px';
        }

        function forcarMesmaAba(event) {
            var link = event.target.closest('a[href]');

            if (!link || !nav.contains(link)) {
                return;
            }

            var href = link.getAttribute('href') || '';

            if (href === '' || href === '#' || href.indexOf('javascript:') === 0 || link.hasAttribute('data-bs-toggle')) {
                return;
            }

            if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            window.location.assign(link.href);
        }

        body.classList.add('autofrota-top-simple');
        aplicarOffsetMenu();
        window.addEventListener('resize', aplicarOffsetMenu);
        nav.addEventListener('click', forcarMesmaAba, true);
    })();
</script>
<!--  -->
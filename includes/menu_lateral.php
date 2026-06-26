<?php
$usuarioMenu = $_SESSION['nome'] ?? $_SESSION['usuario'] ?? 'Usuário';
$baseAutofrotaUrl = 'https://ffasip.ddns.net:4747/autofrota/';
$paginaAtual = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '';
$paginaAtual = preg_replace('#^/+#', '/', $paginaAtual);
$paginaAtual = str_starts_with($paginaAtual, '/autofrota/') ? $paginaAtual : '/autofrota/' . ltrim($paginaAtual, '/');
$paginaAtual = rtrim($baseAutofrotaUrl, '/') . $paginaAtual;

function menuLink(string $caminho, string $baseAutofrotaUrl): string {
    return rtrim($baseAutofrotaUrl, '/') . '/' . ltrim($caminho, '/');
}

function menuAtivo(string $caminho, string $paginaAtual, string $baseAutofrotaUrl): string {
    return menuLink($caminho, $baseAutofrotaUrl) === $paginaAtual ? ' active' : '';
}
?>
<style>
    body.sb-nav-fixed { padding-top: 56px; }
    .sb-topnav { height: 56px; z-index: 1039; }
    .autofrota-logo {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #0d6efd, #20c997);
        color: #ffffff;
        font-weight: 700;
        letter-spacing: .5px;
        box-shadow: 0 0 0 2px rgba(255, 255, 255, .15);
    }
    #layoutSidenav { display: flex; min-height: calc(100vh - 56px); }
    #layoutSidenav_nav {
        flex-basis: 225px;
        flex-shrink: 0;
        transition: transform .15s ease-in-out;
        z-index: 1038;
    }
    #layoutSidenav_content {
        position: relative;
        display: flex;
        flex-direction: column;
        min-width: 0;
        flex-grow: 1;
        min-height: calc(100vh - 56px);
        margin-left: 0;
    }
    body.sb-sidenav-toggled #layoutSidenav_nav { transform: translateX(-225px); }
    body.sb-sidenav-toggled #layoutSidenav_content { margin-left: -225px; }
    .sb-sidenav {
        display: flex;
        flex-direction: column;
        height: 100%;
        flex-wrap: nowrap;
        background: #212529;
        color: rgba(255, 255, 255, .75);
    }
    .sb-sidenav .sb-sidenav-menu { flex-grow: 1; overflow-y: auto; }
    .sb-sidenav .nav { flex-direction: column; flex-wrap: nowrap; }
    .sb-sidenav .nav-link {
        display: flex;
        align-items: center;
        position: relative;
        color: rgba(255, 255, 255, .72);
        padding: .75rem 1rem;
        text-decoration: none;
    }
    .sb-sidenav .nav-link:hover,
    .sb-sidenav .nav-link.active { color: #ffffff; background: rgba(255, 255, 255, .08); }
    .sb-sidenav .sb-nav-link-icon { color: rgba(255, 255, 255, .35); margin-right: .5rem; width: 1rem; }
    .sb-sidenav .sb-sidenav-collapse-arrow { margin-left: auto; transition: transform .15s ease; }
    .sb-sidenav .nav-link:not(.collapsed) .sb-sidenav-collapse-arrow { transform: rotate(-180deg); }
    .sb-sidenav-menu-nested { margin-left: 1.5rem; flex-direction: column; }
    .sb-sidenav-menu-nested .nav-link { padding-top: .5rem; padding-bottom: .5rem; font-size: .92rem; }
    .sb-sidenav-footer { padding: .75rem; background: rgba(0, 0, 0, .25); }
    .sb-sidenav-footer .small { color: rgba(255, 255, 255, .5); }
    .page-wrapper { width: 100%; padding: 0 12px 16px; }
    @media (max-width: 991.98px) {
        #layoutSidenav_nav { position: fixed; top: 56px; bottom: 0; transform: translateX(-225px); }
        body:not(.sb-sidenav-toggled) #layoutSidenav_nav { transform: translateX(-225px); }
        body.sb-sidenav-toggled #layoutSidenav_nav { transform: translateX(0); }
        body.sb-sidenav-toggled #layoutSidenav_content { margin-left: 0; }
    }
</style>
<nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark fixed-top">
    <a class="navbar-brand ps-3 d-flex align-items-center gap-2" href="<?= menuLink('index.php', $baseAutofrotaUrl) ?>" aria-label="AutoFrota">
        <span class="autofrota-logo">AF</span>
        <span>AutoFrota</span>
    </a>
    <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0 text-white" id="sidebarToggle" type="button" aria-label="Abrir ou fechar menu">
        <i class="fas fa-bars"></i>
    </button>
    <div class="d-none d-md-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0"></div>
    <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?= htmlspecialchars($usuarioMenu) ?>">
                <i class="fas fa-user fa-fw"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                <li><span class="dropdown-item-text small text-muted"><?= htmlspecialchars($usuarioMenu) ?></span></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= menuLink('control/logout.php', $baseAutofrotaUrl) ?>">Logout</a></li>
            </ul>
        </li>
    </ul>
</nav>
<div id="layoutSidenav">
    <div id="layoutSidenav_nav">
        <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion" aria-label="Menu AutoFrota">
            <div class="sb-sidenav-menu">
                <div class="nav">
                    <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseTelaInicial" aria-expanded="false" aria-controls="collapseTelaInicial">
                        <div class="sb-nav-link-icon"><i class="fas fa-home"></i></div>
                        Tela Inicial
                        <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                    </a>
                    <div class="collapse" id="collapseTelaInicial" data-bs-parent="#sidenavAccordion">
                        <nav class="sb-sidenav-menu-nested nav">
                            <a class="nav-link<?= menuAtivo('index.php', $paginaAtual, $baseAutofrotaUrl) ?>" href="<?= menuLink('index.php', $baseAutofrotaUrl) ?>">Início</a>
                        </nav>
                    </div>

                    <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseGestao" aria-expanded="false" aria-controls="collapseGestao">
                        <div class="sb-nav-link-icon"><i class="fas fa-columns"></i></div>
                        Gestão
                        <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                    </a>
                    <div class="collapse" id="collapseGestao" data-bs-parent="#sidenavAccordion">
                        <nav class="sb-sidenav-menu-nested nav">
                            <a class="nav-link<?= menuAtivo('listagemgeralcondutores.php', $paginaAtual, $baseAutofrotaUrl) ?>" href="<?= menuLink('listagemgeralcondutores.php', $baseAutofrotaUrl) ?>">Condutores </a>
                            <a class="nav-link<?= menuAtivo('/veiculos/listagem-veiculo.php', $paginaAtual, $baseAutofrotaUrl) ?>" href="<?= menuLink('/veiculos/listagem-veiculo.php', $baseAutofrotaUrl) ?>">Veículos Cadastrados</a>
                            <a class="nav-link<?= menuAtivo('manutencoes/listagem-manutencao.php', $paginaAtual, $baseAutofrotaUrl) ?>" href="<?= menuLink('manutencoes/listagem-manutencao.php', $baseAutofrotaUrl) ?>">Registros de Manutenção</a>
                        </nav>
                    </div>

                    <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseMultas" aria-expanded="false" aria-controls="collapseMultas">
                        <div class="sb-nav-link-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                        Multas
                        <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                    </a>
                    <div class="collapse" id="collapseMultas" data-bs-parent="#sidenavAccordion">
                        <nav class="sb-sidenav-menu-nested nav">
                            <a class="nav-link<?= menuAtivo('multa/cadastromulta.php', $paginaAtual, $baseAutofrotaUrl) ?>" href="<?= menuLink('multa/cadastromulta.php', $baseAutofrotaUrl) ?>">Cadastro de Multas</a>
                        </nav>
                    </div>
                </div>
            </div>
            <div class="sb-sidenav-footer">
                <div class="small">Usuário:</div>
                <?= htmlspecialchars($usuarioMenu) ?>
            </div>
        </nav>
    </div>
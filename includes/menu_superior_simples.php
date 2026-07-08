<?php
$usuarioMenu = $_SESSION['nome'] ?? $_SESSION['usuario'] ?? 'Usuário';
$baseAutofrotaUrl = '';
$paginaAtual = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$paginaAtual = preg_replace('#^/+#', '/', $paginaAtual);

function menuSuperiorLink(string $caminho, string $baseAutofrotaUrl): string
{
    return '/' . ltrim($caminho, '/');
}

function menuSuperiorAtivo(string $caminho, string $paginaAtual, string $baseAutofrotaUrl): string
{
    return menuSuperiorLink($caminho, $baseAutofrotaUrl) === $paginaAtual ? ' active' : '';
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
        <a class="navbar-brand" href="<?= menuSuperiorLink('index.php', $baseAutofrotaUrl) ?>"
            target="_self">AutoFrota</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#afMenuSuperior"
            aria-controls="afMenuSuperior" aria-expanded="false" aria-label="Alternar navegação">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="afMenuSuperior">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <?php if ($_SESSION['perfil'] == 1): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="afMenuCombustivelSupervisor" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-gas-pump"></i> Combustível
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="afMenuCombustivelSupervisor">
                            <li>
                                <a class="dropdown-item"
                                    href="<?= menuSuperiorLink('supervisor/solicitar-cota-extra.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Solicitar Cota Extra
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if ($_SESSION['perfil'] == 2): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="afMenuCombustivel" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-gas-pump"></i> Combustível
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="afMenuCombustivel">
                            <li>
                                <a class="dropdown-item"
                                    href="<?= menuSuperiorLink('combustivel/remanejamento/index.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Remanejamento
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item"
                                    href="<?= menuSuperiorLink('coordenador/solicitar-orcamento-complementar.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Solicitar Orçamento Complementar
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item"
                                    href="<?= menuSuperiorLink('coordenador/aprovacao-cotas.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Aprovar Cotas Extra de Colaboradores
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item"
                                    href="<?= menuSuperiorLink('coordenador/aprovacao-cotas-supervisor.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Aprovar Cotas Extra de Supervisores
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
                
       <?php if ($_SESSION['perfil'] == 3): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="afMenuGerenteCombustivel" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-gas-pump"></i> Combustível
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="afMenuGerenteCombustivel">
                            <li>
                                <a class="dropdown-item"
                                    href="<?= menuSuperiorLink('gerente/solicitacao-orcamento-diretor.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Pedir Orçamento ao Diretor
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item"
                                    href="<?= menuSuperiorLink('gerente/aprovar-orcamento-complementar.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Aprovar Orçamento de Coordenadores
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item"
                                    href="<?= menuSuperiorLink('gerente/aprovacao-cotas.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Aprovar Cota Escalonada
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if ($_SESSION['perfil'] == 4): ?>

                    <li class="nav-item">
                        <a class="nav-link<?= menuSuperiorAtivo('index.php', $paginaAtual, $baseAutofrotaUrl) ?>"
                            href="<?= menuSuperiorLink('index.php', $baseAutofrotaUrl) ?>" target="_self">
                            <i class="fas fa-house me-1"></i>Início
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= menuSuperiorAtivo('aprovar-pedidos-orcamento-frota.php', $paginaAtual, $baseAutofrotaUrl) ?>"
                            href="<?= menuSuperiorLink('aprovar-pedidos-orcamento-frota.php', $baseAutofrotaUrl) ?>" target="_self">
                            <i class="fas fa-clipboard-check me-1"></i>Aprovar Pedidos Frota
                        </a>
                    </li>
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

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="afMenuCombustivel" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-gas-pump"></i> Combustível
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="afMenuCombustivel">
                            <li>
                                <a class="dropdown-item"
                                    href="<?= menuSuperiorLink('combustivel/remanejamento/index.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Remanejamento
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if ($_SESSION['perfil'] == 4 || $_SESSION['perfil'] == 12): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="afMenuMultas" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-file-circle-exclamation me-1"></i>Multas
                        </a>
                        <?php if ($_SESSION['perfil'] == 4): ?>
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
                        <?php elseif ($_SESSION['perfil'] == 12): ?>
                            <ul class="dropdown-menu" aria-labelledby="afMenuMultas">
                                <li><a class="dropdown-item"
                                        href="<?= menuSuperiorLink('multa/multasdp.php', $baseAutofrotaUrl) ?>"
                                        target="_self">Listar Multas</a></li>
                                <li><a class="dropdown-item"
                                        href="<?= menuSuperiorLink('multa/relatorio_geral.php', $baseAutofrotaUrl) ?>"
                                        target="_self">Relatorio Geral de Multas</a></li>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endif; ?>

                <?php if ($_SESSION['perfil'] == 10): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="afMenuDiretorCombustivel" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-gas-pump"></i> Combustível
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="afMenuDiretorCombustivel">
                            <li>
                                <a class="dropdown-item"
                                    href="<?= menuSuperiorLink('diretor/pedir-orcamento.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Pedir Orçamento
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item"
                                    href="<?= menuSuperiorLink('diretor/aprovar-orcamento-complementar.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Aprovar Orçamento Complementar
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item"
                                    href="<?= menuSuperiorLink('combustivel/remanejamento/index.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Remanejar Saldo
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item"
                                    href="<?= menuSuperiorLink('combustivel/retirada/index.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Retirar Saldo
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item"
                                    href="<?= menuSuperiorLink('combustivel/historico_combustivel.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Histórico de Combustível
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if ($_SESSION['perfil'] == 3): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="afMenuCombustivel" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-gas-pump"></i> Combustível
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="afMenuCombustivel">
                            <li class="nav-item">
                                <a class="nav-link<?= menuSuperiorAtivo('gerente/aprovacao-cotas.php', $paginaAtual, $baseAutofrotaUrl) ?>"
                                    href="<?= menuSuperiorLink('gerente/aprovacao-cotas.php', $baseAutofrotaUrl) ?>"
                                    target="_self">
                                    <i class="fas fa-check-double me-1"></i>Aprovação de cotas escalonadas
                                </a>
                            </li>
                        </ul>
                        <ul class="dropdown-menu" aria-labelledby="afMenuCombustivel">
                            <li>
                                <a class="dropdown-item"
                                    href="<?= menuSuperiorLink('combustivel/remanejamento/index.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Remanejar Saldo
                                </a>
                            </li>
                        </ul>
                        <ul class="dropdown-menu" aria-labelledby="afMenuCombustivel">
                            <li>
                                <a class="dropdown-item"
                                    href="<?= menuSuperiorLink('combustivel/retirada/index.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Remover Saldo
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if ($_SESSION['perfil'] == 10): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="afMenuCombustivel" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-gas-pump"></i> Combustível
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="afMenuCombustivel">
                            <li>
                                <a class="dropdown-item"
                                    href="<?= menuSuperiorLink('combustivel/remanejamento/index.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Remanejar Saldo
                                </a>
                            </li>
                        </ul>
                        <ul class="dropdown-menu" aria-labelledby="afMenuCombustivel">
                            <li>
                                <a class="dropdown-item"
                                    href="<?= menuSuperiorLink('combustivel/retirada/index.php', $baseAutofrotaUrl) ?>"
                                    target="_self">Remover Saldo
                                </a>
                            </li>
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
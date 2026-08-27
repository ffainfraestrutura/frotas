<?php
if (!function_exists('esc')) {
    function esc($valor): string
    {
        return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('valorRequisicao')) {
    function valorRequisicao(array $nomes): string
    {
        foreach ($nomes as $nome) {
            if (isset($_POST[$nome]) && trim((string) $_POST[$nome]) !== '') {
                return trim((string) $_POST[$nome]);
            }
            if (isset($_GET[$nome]) && trim((string) $_GET[$nome]) !== '') {
                return trim((string) $_GET[$nome]);
            }
        }

        return '';
    }
}

if (!function_exists('formatarDataPortal')) {
    function formatarDataPortal($data, string $formato = 'd/m/Y H:i:s'): string
    {
        $data = trim((string) ($data ?? ''));
        if ($data === '' || $data === '0000-00-00' || $data === '0000-00-00 00:00:00') {
            return '';
        }

        $timestamp = strtotime($data);
        return $timestamp ? date($formato, $timestamp) : $data;
    }
}

if (!function_exists('consultaPreparada')) {
    function consultaPreparada(mysqli $conn, string $sql, string $tipos = '', array $parametros = []): array
    {
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['erro' => mysqli_error($conn), 'linhas' => []];
        }

        if ($tipos !== '' && $parametros !== []) {
            $referencias = [];
            foreach ($parametros as $indice => $valor) {
                $referencias[$indice] = &$parametros[$indice];
            }
            mysqli_stmt_bind_param($stmt, $tipos, ...$referencias);
        }

        if (!mysqli_stmt_execute($stmt)) {
            $erro = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            return ['erro' => $erro, 'linhas' => []];
        }

        $resultado = mysqli_stmt_get_result($stmt);
        $linhas = $resultado ? mysqli_fetch_all($resultado, MYSQLI_ASSOC) : [];
        mysqli_stmt_close($stmt);

        return ['erro' => '', 'linhas' => $linhas];
    }
}

if (!function_exists('buscarUmaLinha')) {
    function buscarUmaLinha(mysqli $conn, string $sql, string $tipos = '', array $parametros = []): array
    {
        $consulta = consultaPreparada($conn, $sql, $tipos, $parametros);
        return $consulta['linhas'][0] ?? [];
    }
}

if (!function_exists('buscarUfsPortal')) {
    function buscarUfsPortal(mysqli $conn): array
    {
        $consulta = consultaPreparada($conn, 'SELECT uf FROM `bdautofrotas`.`tb_ufs` WHERE uf IS NOT NULL AND TRIM(uf) <> \'\' ORDER BY uf');
        $ufs = [];

        foreach ($consulta['linhas'] as $linha) {
            $uf = strtoupper(trim((string) ($linha['uf'] ?? '')));
            if ($uf !== '') {
                $ufs[] = $uf;
            }
        }

        return array_values(array_unique($ufs));
    }
}

if (!function_exists('diretorioUploadsPortal')) {
    function diretorioUploadsPortal(): string
    {
        return '/tmp/frotas_docs';
    }
}

if (!function_exists('urlDocumentoUploadPortal')) {
    function urlDocumentoUploadPortal($documento): string
    {
        $documento = trim((string) ($documento ?? ''));
        if ($documento === '' || preg_match('~^(?:https?:)?//~i', $documento) || preg_match('~^/?visualizar-upload\.php\?abrir=~i', $documento)) {
            return $documento;
        }

        return '/visualizar-upload.php?abrir=' . rawurlencode(basename(str_replace('\\', '/', $documento)));
    }
}

if (!function_exists('badgeStatusAtivo')) {
    function badgeStatusAtivo($ativo): string
    {
        return ((string) $ativo === '1') ? 'ATIVO' : 'INATIVO';
    }
}

if (!function_exists('renderCabecalhoAutofrota')) {
    function renderCabecalhoAutofrota(string $titulo): void
    {
        echo '<!DOCTYPE html><html lang="pt-br"><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . esc($titulo) . ' - AutoFrota</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">';
        echo '<link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.css">';
        echo '<style>body,.form-control,.form-select,.btn,.table{font-size:13px}.card-info label{font-weight:600;color:#495057}.table td,.table th{vertical-align:middle}.page-wrapper{padding:1rem}</style>';
        echo '</head><body class="sb-nav-fixed">';
        if (function_exists('autofrotaMenu')) { autofrotaMenu(); } else { include __DIR__ . '/menu_superior_simples.php'; }
        echo '<div id="layoutSidenav_content"><main class="page-wrapper">';
    }
}

if (!function_exists('renderRodapeAutofrota')) {
    function renderRodapeAutofrota(): void
    {
        echo '</main></div></div>';
        echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
        echo '<script src="https://cdn.datatables.net/2.0.8/js/dataTables.js"></script>';
        echo '<script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.js"></script>';
        echo '<script>document.addEventListener("DOMContentLoaded",function(){if(!window.jQuery||!jQuery.fn||!jQuery.fn.DataTable){return;}jQuery("table[data-datatable=1]").each(function(){var $t=jQuery(this);if($t.data("dtInitialized")){return;}$t.data("dtInitialized",true);$t.DataTable({ordering:true,searching:true,paging:true,lengthChange:true,pageLength:10,lengthMenu:[[10,25,50,100,-1],[10,25,50,100,"Todos"]],order:[],language:{url:"https://cdn.datatables.net/plug-ins/2.0.8/i18n/pt-BR.json",search:"Buscar:",lengthMenu:"Exibir _MENU_ registros",info:"Mostrando _START_ até _END_ de _TOTAL_ registros",infoEmpty:"Mostrando 0 até 0 de 0 registros",infoFiltered:"(filtrado de _MAX_ registros no total)",zeroRecords:"Nenhum registro encontrado",paginate:{first:"Primeiro",last:"Último",next:"Próximo",previous:"Anterior"}}});});var b=document.getElementById("sidebarToggle");if(b){b.addEventListener("click",function(e){e.preventDefault();document.body.classList.toggle("sb-sidenav-toggled");});}});</script>';
        echo '</body></html>';
    }
}
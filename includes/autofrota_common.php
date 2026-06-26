<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/portal_helpers.php';

if (!function_exists('autofrotaInit')) {
    function autofrotaInit(): array
    {
        require_once AUTOFROTA_ROOT . '/auth.php';

        if (function_exists('mysqli_report')) {
            mysqli_report(MYSQLI_REPORT_OFF);
        }

        require_once AUTOFROTA_ROOT . '/control/conecta.php';

        $resolvedConn = null;
        if (isset($conn) && $conn instanceof mysqli) {
            $resolvedConn = $conn;
        } elseif (isset($con) && $con instanceof mysqli) {
            $resolvedConn = $con;
        } elseif (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            $resolvedConn = $GLOBALS['conn'];
        } elseif (isset($GLOBALS['con']) && $GLOBALS['con'] instanceof mysqli) {
            $resolvedConn = $GLOBALS['con'];
        }

        $resolvedDatabaseName = '';
        if (isset($databaseName) && is_string($databaseName) && $databaseName !== '') {
            $resolvedDatabaseName = $databaseName;
        } elseif (isset($database) && is_string($database) && $database !== '') {
            $resolvedDatabaseName = $database;
        } elseif (isset($GLOBALS['databaseName']) && is_string($GLOBALS['databaseName']) && $GLOBALS['databaseName'] !== '') {
            $resolvedDatabaseName = $GLOBALS['databaseName'];
        } elseif (isset($GLOBALS['database']) && is_string($GLOBALS['database']) && $GLOBALS['database'] !== '') {
            $resolvedDatabaseName = $GLOBALS['database'];
        }

        $resolvedDatabaseCorp = '';
        if (isset($databaseCorp) && is_string($databaseCorp) && $databaseCorp !== '') {
            $resolvedDatabaseCorp = $databaseCorp;
        } elseif (isset($GLOBALS['databaseCorp']) && is_string($GLOBALS['databaseCorp']) && $GLOBALS['databaseCorp'] !== '') {
            $resolvedDatabaseCorp = $GLOBALS['databaseCorp'];
        }

        if ($resolvedConn instanceof mysqli) {
            $GLOBALS['conn'] = $resolvedConn;
            $GLOBALS['con'] = $resolvedConn;
        }

        if ($resolvedDatabaseName !== '') {
            $GLOBALS['databaseName'] = $resolvedDatabaseName;
            $GLOBALS['database'] = $resolvedDatabaseName;
        }

        if ($resolvedDatabaseCorp !== '') {
            $GLOBALS['databaseCorp'] = $resolvedDatabaseCorp;
        }

        exigirLogin();

        return [
            'usuario' => (string) ($_SESSION['usuario'] ?? $_SESSION['nome'] ?? 'Usuário'),
            'matricula' => (string) ($_SESSION['matricula'] ?? ''),
            'perfil' => (string) ($_SESSION['perfil'] ?? ''),
            'conn' => $resolvedConn,
            'databaseName' => $resolvedDatabaseName,
            'databaseCorp' => $resolvedDatabaseCorp,
        ];
    }
}

if (!function_exists('autofrotaMenu')) {
    function autofrotaMenu(): void
    {
        include __DIR__ . '/menu_superior_simples.php';
    }
}
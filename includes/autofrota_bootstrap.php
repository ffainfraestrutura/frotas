<?php

declare(strict_types=1);

/**
 * Desativa alertas/exceções automáticas do mysqli, mantendo o comportamento legado.
 */
function desativarRelatorioMySQLi(): void
{
    if (function_exists('mysqli_report')) {
        mysqli_report(MYSQLI_REPORT_OFF);
    }
}

/**
 * Inicializa autenticação/conexão e retorna dados úteis da sessão.
 *
 * @return array{usuario:string,matricula:string,perfil:string}
 */
function inicializarAutofrota(): array
{
    require_once __DIR__ . '/../auth.php';

    desativarRelatorioMySQLi();

    require_once __DIR__ . '/../control/conecta.php';

    exigirLogin();

    return [
        'usuario' => (string) ($_SESSION['usuario'] ?? 'Usuário'),
        'matricula' => (string) ($_SESSION['matricula'] ?? $_SESSION['usuario'] ?? ''),
        'perfil' => (string) ($_SESSION['perfil'] ?? ''),
    ];
}

/**
 * Carrega opções de selects no formato padrão (value/label).
 *
 * @param mysqli $conn Conexão ativa do banco.
 * @param string $sql Consulta SQL que retorna as colunas de valor/descrição.
 * @param string $valueField Nome do campo usado como valor da opção.
 * @param string $labelField Nome do campo usado como rótulo da opção.
 *
 * @return array<int, array{value:string,label:string}> Lista de opções formatadas.
 */
function carregarOpcoes(mysqli $conn, string $sql, string $valueField, string $labelField): array
{
    $options = [];
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        error_log('Erro ao carregar opcoes: ' . mysqli_error($conn));
        return $options;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $options[] = [
            'value' => (string) ($row[$valueField] ?? ''),
            'label' => (string) ($row[$labelField] ?? ''),
        ];
    }

    mysqli_free_result($result);

    return $options;
}


/**
 * Inclui o menu lateral padrão do módulo Autofrota.
 *
 * @return void
 */
function incluirMenuLateralAutofrota(): void
{
    include __DIR__ . '/menu_superior_simples.php';
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/autofrota_common.php';

if (!function_exists('toaquiContextoSupervisor')) {
    function toaquiContextoSupervisor(): array
    {
        $sessao = autofrotaInit();

        if ((string) ($sessao['perfil'] ?? '') !== '1') {
            http_response_code(403);
            exit('Esta funcionalidade está disponível somente para supervisores.');
        }

        $conn = $sessao['conn'] ?? null;
        if (!$conn instanceof mysqli) {
            http_response_code(503);
            exit('Conexão com o banco de dados indisponível.');
        }

        $matricula = trim((string) ($sessao['matricula'] ?? ''));
        $databaseCorp = (string) ($sessao['databaseCorp'] ?? 'bdcorp');
        $databaseName = (string) ($sessao['databaseName'] ?? 'bdautofrotas');
        $supervisor = buscarUmaLinha(
            $conn,
            "SELECT idtbsupervisor FROM `{$databaseCorp}`.`tbsupervisor` WHERE matricula = ? LIMIT 1",
            's',
            [$matricula]
        );

        if (!isset($supervisor['idtbsupervisor'])) {
            http_response_code(403);
            exit('Supervisor não localizado na hierarquia corporativa.');
        }

        return $sessao + [
            'conn' => $conn,
            'databaseCorp' => $databaseCorp,
            'databaseName' => $databaseName,
            'idtbsupervisor' => (int) $supervisor['idtbsupervisor'],
        ];
    }
}

if (!function_exists('toaquiResponderJson')) {
    function toaquiResponderJson(array $dados, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

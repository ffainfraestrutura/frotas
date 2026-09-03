<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

if (!function_exists('autenticarUsuarioAutofrota')) {
    /**
     * Valida as credenciais e o acesso ao AutoFrotas em bdcorp, obtendo o
     * perfil da aplicacao em bdautofrotas pela matricula comum aos cadastros.
     */
    function autenticarUsuarioAutofrota(
        mysqli $conn,
        string $usuario,
        string $senha,
        string $databaseCorp = 'bdcorp',
        string $databaseAutofrota = 'bdautofrotas'
    ): ?array {
        if (
            preg_match('/^\w+$/', $databaseCorp) !== 1
            || preg_match('/^[a-zA-Z0-9_]+$/', $databaseAutofrota) !== 1
        ) {
            return null;
        }

        $sql = "SELECT corporativo.usuario,
                       corporativo.matricula,
                       autofrota.perfil
                  FROM `{$databaseCorp}`.`tbusuario` corporativo
                  INNER JOIN `{$databaseAutofrota}`.`tbusuario` autofrota
                          ON autofrota.matricula = corporativo.matricula
                 WHERE corporativo.usuario = ?
                   AND corporativo.senha = ?
                   AND corporativo.autofrotas = 1
                 LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param($stmt, 'ss', $usuario, $senha);
        mysqli_stmt_execute($stmt);
        $resultado = mysqli_stmt_get_result($stmt);
        $usuario = $resultado ? mysqli_fetch_assoc($resultado) : null;
        mysqli_stmt_close($stmt);

        return is_array($usuario) ? $usuario : null;
    }
}

if (!function_exists('exigirPerfil')) {
    /**
     * Interrompe a pagina quando o perfil da sessao nao estiver autorizado.
     *
     * @param int|string|array<int, int|string> $perfisPermitidos
     */
    function exigirPerfil(
        array $autofrotaSessao,
        $perfisPermitidos,
        string $redirecionarPara = 'index.php'
    ): void {
        $perfilAtual = (string) ($autofrotaSessao['perfil'] ?? '');
        $perfis = is_array($perfisPermitidos) ? $perfisPermitidos : [$perfisPermitidos];
        $perfis = array_map('strval', $perfis);

        if ($perfilAtual !== '' && in_array($perfilAtual, $perfis, true)) {
            return;
        }

        $mensagem = 'Você não tem permissão para acessar esta página.';

        if (requisicaoAjax()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $mensagem]);
            exit;
        }

        $destino = urlAutofrota($redirecionarPara);
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><title>Acesso negado</title>';
        echo '<meta http-equiv="refresh" content="2;url=' . htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') . '"></head><body>';
        echo '<p>' . htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<script>alert(' . json_encode($mensagem) . '); window.location.replace(' . json_encode($destino) . ');</script>';
        echo '</body></html>';
        exit;
    }
}

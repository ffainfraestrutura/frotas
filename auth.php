<?php
function caminhoBaseAutofrota(): string
{
    return '/';
}

function urlAutofrota(string $path = ''): string {
    $base = rtrim(caminhoBaseAutofrota(), '/');
    $path = ltrim($path, '/');

    return $path === '' ? $base . '/' : $base . '/' . $path;
}

function redirecionarAutofrota(string $path = ''): void {
    header('Location: ' . urlAutofrota($path));
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    $cookieParams = session_get_cookie_params();
    $cookiePath = rtrim(caminhoBaseAutofrota(), '/') . '/';

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => $cookieParams['lifetime'],
            'path' => $cookiePath,
            'domain' => $cookieParams['domain'],
            'secure' => $cookieParams['secure'],
            'httponly' => $cookieParams['httponly'],
            'samesite' => $cookieParams['samesite'] ?? 'Lax',
        ]);
    } else {
        session_set_cookie_params(
            $cookieParams['lifetime'],
            $cookiePath,
            $cookieParams['domain'],
            $cookieParams['secure'],
            $cookieParams['httponly']
        );
    }

    session_start();
}

function usuarioLogado(): bool {
    return isset($_SESSION['logado']) && $_SESSION['logado'] === true;
}

function requisicaoAjax(): bool {
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if (strtolower($requestedWith) === 'xmlhttprequest') {
        return true;
    }

    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return stripos($accept, 'application/json') !== false;
}

function exigirLogin(): void {
    if (usuarioLogado()) {
        return;
    }

    if (requisicaoAjax()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }

    redirecionarAutofrota('login.php');
}

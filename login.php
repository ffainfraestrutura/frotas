<?php
require_once __DIR__ . '/auth.php';

if (usuarioLogado()) {
    $perfilLogado = (string) ($_SESSION['perfil'] ?? '');
    if ($perfilLogado === '0') {
        redirecionarAutofrota('tecnico.php');
    }
    if ($perfilLogado === '2') {
        redirecionarAutofrota('coordenador/aprovacao-cotas.php');
    }
    if ($perfilLogado === '3') {
        redirecionarAutofrota('gerente/aprovacao-cotas.php');
    }
    redirecionarAutofrota('index.php');
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="AutoFrota" />
    <meta name="author" content="FFA" />
    <title>Login - AutoFrota</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --dark-bg: #212529; --grey-light: #f8f9fa; }
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--grey-light); }
        .login-container { width: 100%; max-width: 420px; }
        .card { border-radius: 15px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2); border: none; }
        .card-header { background: var(--dark-bg); color: white; border-radius: 15px 15px 0 0; padding: 30px; text-align: center; }
        .form-control { border-radius: 8px; padding: 12px; }
        .btn-login { background: var(--dark-bg); border: none; border-radius: 8px; padding: 12px; width: 100%; color: white; }
        .btn-login:hover { background: #1a1d23; color: white; }
        .portal-label { margin-bottom: 6px; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(255, 255, 255, 0.85); }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <div class="portal-label" aria-label="Portal">Portal</div>
                <h3>AutoFrota</h3>
            </div>
            <div class="card-body p-4">
                <form method="post" action="./control/logincontrole.php">
                    <div class="mb-3">
                        <label for="inputUsuario" class="form-label">Usuário</label>
                        <input class="form-control" name="login" id="inputUsuario" type="text" placeholder="Digite sua matrícula" inputmode="numeric" pattern="[0-9]+" maxlength="10" required />
                    </div>
                    <div class="mb-3">
                        <label for="inputSenha" class="form-label">Senha</label>
                        <input class="form-control" name="pass" id="inputSenha" type="password" placeholder="Digite sua senha" required />
                    </div>

                    <?php if (isset($_GET['erro'])): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo htmlspecialchars($_GET['erro']); ?>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt"></i> Entrar
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

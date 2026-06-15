<?php
require_once __DIR__ . '/../auth.php';

session_unset();
session_destroy();

$destino = urlAutofrota('login.php');
$destinoJs = json_encode($destino, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="pt-br">
<head>
	<meta charset="utf-8">
	<meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') ?>">
	<title>Saindo...</title>
</head>
<body>
<script>
	(function () {
		var destino = <?= $destinoJs ?>;

		if (window.opener && !window.opener.closed) {
			window.opener.location.href = destino;
			window.close();
			return;
		}

		window.location.replace(destino);
	})();
</script>
<noscript>
	<a href="<?= htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') ?>">Continuar</a>
</noscript>
</body>
</html>
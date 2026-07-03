<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? ($GLOBALS['conn'] ?? null);
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? ($GLOBALS['databaseName'] ?? 'bdautofrotas'));
$databaseCorp = (string) ($autofrotaSessao['databaseCorp'] ?? ($GLOBALS['databaseCorp'] ?? 'bdcorp'));
$matriculaLogada = (string) ($autofrotaSessao['matricula'] ?? $_SESSION['matricula'] ?? '');
$nomeLogado = (string) ($autofrotaSessao['usuario'] ?? $_SESSION['nome'] ?? '');
$perfilLogado = (string) ($autofrotaSessao['perfil'] ?? $_SESSION['perfil'] ?? '');
$matriculasAutorizadas = ['601004', '004607', '086272', '000000'];

if (!$conn instanceof mysqli) {
    exit('Conexão indisponível.');
}
if ($perfilLogado !== '4' || !in_array($matriculaLogada, $matriculasAutorizadas, true)) {
    http_response_code(403);
    exit('Acesso permitido apenas para frota autorizada.');
}

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['token'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $decisao = (int) ($_POST['decisao'] ?? 0);
    
    $tokenValido = ($token !== '' && hash_equals($token, $_SESSION['frota_orcamento_token'] ?? ''));
    
    if (!$tokenValido || $id === 0 || ($decisao !== 1 && $decisao !== 2)) {
        $_SESSION['frota_orcamento_mensagem'] = 'Requisição inválida ou token expirado.';
        $_SESSION['frota_orcamento_tipo'] = 'danger';
        header('Location: /aprovar-pedidos-orcamento-frota.php');
        exit;
    }
    
    $sql = "UPDATE `{$databaseName}`.`tbpedidosgerente` SET flag = ? WHERE idtbpedidosgerente = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $_SESSION['frota_orcamento_mensagem'] = 'Erro ao preparar query: ' . esc($conn->error);
        $_SESSION['frota_orcamento_tipo'] = 'danger';
        header('Location: /aprovar-pedidos-orcamento-frota.php');
        exit;
    }
    
    $stmt->bind_param('ii', $decisao, $id);
    
    if ($stmt->execute()) {
        $mensagem = $decisao === 2 ? 'Pedido aprovado com sucesso!' : 'Pedido reprovado com sucesso!';
        $_SESSION['frota_orcamento_mensagem'] = $mensagem;
        $_SESSION['frota_orcamento_tipo'] = 'success';
    } else {
        $_SESSION['frota_orcamento_mensagem'] = 'Erro ao atualizar pedido: ' . esc($stmt->error);
        $_SESSION['frota_orcamento_tipo'] = 'danger';
    }
    
    $stmt->close();
    header('Location: /aprovar-pedidos-orcamento-frota.php');
    exit;
}

// Se chegou aqui, redireciona para a página principal
header('Location: /aprovar-pedidos-orcamento-frota.php');
exit;
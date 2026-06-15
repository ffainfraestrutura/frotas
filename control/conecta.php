<?php
/**
 * Configurações de conexão com o banco do portal Autofrotas.
 */
$servidor = 'mysql';
$usuarioDb = 'admin';
$senhaDb = '@F3r7n6c2';
$banco = 'bdautofrotas';

/**
 * Nome do schema principal usado nas consultas das telas.
 *
 * As demais páginas devem reutilizar esta variável após incluir este arquivo,
 * evitando redefinição local de "$databaseName".
 *
 * @var string $databaseName
 */
$databaseName = $banco;

/**
 * Conexão MySQLi compartilhada entre as telas do portal.
 *
 * @var mysqli|false $conn
 */
$conn = mysqli_connect($servidor, $usuarioDb, $senhaDb, $banco);
?>

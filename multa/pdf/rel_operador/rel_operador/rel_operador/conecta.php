<?php
$servidor="localhost";
$usuario="blvp";
$senha="qrdrps";

$conexao=mysql_connect("$servidor","$usuario","$senha") or die (mysql_error());

$db=mysql_select_db("bluecall_frescato");
?>

<?php
$servidor="127.0.0.1";
$usuario="blvp";
$senha="qrdrps";

$conexao=mysql_connect("$servidor","$usuario","$senha") or die (mysql_error());

$db=mysql_select_db("bluecall");
?>

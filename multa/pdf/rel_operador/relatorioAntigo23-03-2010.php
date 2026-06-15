<?php

include "conecta.php";

$data = $_POST["datainicio"];
	$dataf= $_POST["datafim"];

header("Content-type: application/vnd.ms-excel");
header("Content-type: application/force-download");
header("Content-Disposition: attachment; filename=relatorio.xls"); 
header("Pragma: no-cache");
?>
<style type="text/css">
<!--
.header {
	font-family: Verdana, Geneva, sans-serif;
	color: #000;
	font-weight: bold;
	font-size: 18px;
	text-align: center;
}
.sub_header {
	font-weight: bold;
}
-->
</style>
<table width="759" height="100" border="0">
  <tr>
    <td colspan = 3 bgcolor="#FFFFFF" class="header">Relat&oacute;rio de Chamadas Por Per&iacute;odo    </td>
   
  </tr>
  <tr>
    <td width="417" height="41" bgcolor="#FFFFFF" class="sub_header">Per&iacute;odo de: <?php echo $data ;?> até <?php echo $dataf; ?> </td>
    <td>&nbsp;&nbsp;</td>
    <td width="233" bgcolor="#FFFFFF" class="sub_header">Emitido em: <?php echo date("d/m/Y"); ?></td>
     
  </tr>
  <tr> </tr>

 
</table>
<?php
//buscando dados digitados no form anterior

	
	$data = explode("/",$data);
	$dataf = explode("/",$dataf);

	 $dataini = $data[2] . "-" .$data[1] . "-" .$data[0] ;
        $datafim = $dataf[2] . "-" .$dataf[1] . "-" .$dataf[0] ;	
	echo" <style type='text/css'>
<!--
.style8 {font-size: x-small}
.style11 {color: #000099}
.style13 {font-family: Arial, Helvetica, sans-serif}
.style14 {font-size: x-small; font-weight: bold; color: #000099; font-family: Arial, Helvetica, sans-serif; }
-->
</style>
       
          <style type='text/css'>
<!--
.style22 {font-size: 12px; font-weight: bold; color: #000099; font-family: Arial, Helvetica, sans-serif; }
-->
</style>
<style type='text/css'>
<!--
.style30 {font-size: 12px; font-weight: bold; color: #000000; font-family: Arial, Helvetica, sans-serif; }
-->
</style>
       
           <table width='660' border='0' bordercolor='#000000'>
            <tr>
              <td bgcolor='#66CCFF'><span class='style30'>Chamadas</span></td>
			    <td bgcolor='#66CCFF'>&nbsp;&nbsp;</td>
              <td bgcolor='#66CCFF'><span class='style30'><strong>Total de Chamadas</strong></span></td>
			 
              
            </tr>";
	
	$sql = "select  count(*) as total , dst from asteriskcdrdb.cdr	
       where calldate >= '$dataini' and calldate <= '$datafim' 
	and dstchannel <> ''  and dcontext = 'queues' group by dst";


       $resultado = mysql_query($sql) or die (mysql_error());
	

	while($row = mysql_fetch_array($resultado))
	{
		
	echo " <tr>
              <td><span class='style1 style2 style3 style8'>Atendidas</span></td>
			    <td>&nbsp;&nbsp;</td>
              <td><span class='style1 style2 style3 style8'>".$row["total"]."</span></td>
			
              
            </tr>";
  }

	$sql = "select  count(*) as total , dst from asteriskcdrdb.cdr	
       where calldate >= '$dataini' and calldate <= '$datafim' 
	and dstchannel = ''  and dcontext = 'queues' group by dst";


       $resultado = mysql_query($sql) or die (mysql_error());
	
	while($row = mysql_fetch_array($resultado))
	{
		echo " <tr>
              <td><span class='style1 style2 style3 style8'>Desistente</span></td>
			    <td>&nbsp;&nbsp;</td>
              <td><span class='style1 style2 style3 style8'>".$row["total"]."</span></td>
			
              
            </tr>";
      }

  echo "</table>";		
?>


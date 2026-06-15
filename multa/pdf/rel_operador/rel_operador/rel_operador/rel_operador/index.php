<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<?php 

require_once "../../blue_call/model/valida_sessao.php";
include "conecta.php";
 
	$sql = "select codagente, nome from agente";
    $resultado = mysql_query($sql) or die (mysql_error());
    
?>
<html>
<head>
	<title>:... BlueCall</title>
	<link rel="stylesheet" type="text/css" href="../../style.css">
<script type="text/javascript" src="jquery-1.2.3.min.js"></script>

<link href="CalendarControl.css"      rel="stylesheet" type="text/css"><script src="CalendarControl.js"        language="javascript"></script>



<!--begin custom header content for this example-->
<style type="text/css">

    /*
        Set the "zoom" property to "normal" since it is set to "1" by the 
        ".example-container .bd" rule in yui.css and this causes a Menu
        instance's width to expand to 100% of the browser viewport.
    */
    
    div.yuimenu .bd {
    
        zoom: normal;
    
    }


    #calendarmenu {
    
        position: absolute;
    
    }


    /*
        Restore default padding of 10px for the calendar containtainer 
        that is overridden by the ".example-container .bd .bd" rule 
        in yui.css.
    */

    #calendarcontainer {

        padding:10px;

    }
    
	#personalinfo {
	
		border: solid 1px #666;
		padding: .5em;
	
	}
	
	#calendarpicker  {
	
		vertical-align: baseline;
		
	}

	div.field {
	
		padding: .25em;
	
	}
	
	input#year {
	
		width: 4em;
	
	}

</style>
<style type="text/css">
<!--
.style9 {
	color: #0000CC;
	font-weight: bold;
}
.style11 {color: #000000}
.style12 {color: #0000CC}
.style13 {font-weight: bold}
.style18 {font-size: x-small}
-->
</style>

<style type="text/css">
<!--
.titulo {
	font-family: Verdana, Geneva, sans-serif;
	font-size: 18px;
	color: #6A8CB0;
}
.login {
	font-family: Verdana, Geneva, sans-serif;
	font-size: 13px;
	color: #1872B8;
	font-weight: bold;
}
.style1 {font-size: small;
	color: #330099;
	font-family: Arial, Helvetica, sans-serif;
}
.style13 {	font-weight: bold;
	color: #000000;
}
.style14 {color: #000000}
.style15 {font-family: Arial, Helvetica, sans-serif}
.style2 {font-family: Arial, Helvetica, sans-serif;
	color: #330099;
}
-->
</style></head>

<body leftmargin=0 topmargin=0 marginheight="0" marginwidth="0" bgcolor="#E6E6E6" background="../../images/fon.gif">
<table border="0" cellpadding="0" cellspacing="0" width="100%" background="../../images/fon_top.gif" height="113">
  <tr>
	<td height="113" align="center" valign="top">
<table border="0" cellpadding="0" cellspacing="0" width="1024">
<tr>
<td width="644" height="68" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="../../blue_call/public/img/frescatto.png" width="180" height="64"></td>
	<td width="290" valign="middle"><img src="../../images/LOGO_BLUECALL.png" width="194" height="33"></td>
</tr>
<tr>
	<td height="43" colspan="2">
<table cellpadding="0" cellspacing="0">
<tr>
	<td width="1024" height="42" align="left" valign="middle"  class="menu02"><img src="../../blue_call/public/img/barra_menu_admin2.png" width="1024" height="42" border="0" usemap="#Map"></td>
	
	</tr>
</table>
	</td>
</tr>
<tr>
	<td height="34" colspan="2" align="left" valign="middle" background="../../images/fon_top02.gif" class="titulo" alt="" border="0" >&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&Aacute;rea Administrativa</td>
</tr>
</table>
	</td>
</tr>
</table>
<table border="0" cellpadding="0" cellspacing="0" width="1024" align="center">

<tr>
	<td width="1024" height="242" align="center" bgcolor="#EEEEEE" ><table align="center" width="753" border="0" height="462" cellspacing="0" cellpadding="0">
	 
	      <td height="2" colspan="3"><div align="left"><a href="#"></a>
	        <hr>
	        </div></td>
        </tr>
	  <tr>
	    <td colspan = 2><div align="left">
	          <div align="left" class="style14"><span class="style15"><strong>Escolha 
                um per&iacute;odo para a emiss&atilde;o do relat&oacute;rio de 
                liga&ccedil;&otilde;es por operador:</strong></span><br>
	        <br>
	        </div>
	      <form action="relatorio.php" method="POST" name="formulario" >
	        <table width="514" border="0" align="center">
	          <tr>
	            <td colspan="2" class="style13 style15"><p class="style13 style15">Informe o per&iacute;odo desejado 
	              :</p>
	                  <p> De: 
                        <input name="datainicio" size=20       onfocus="showCalendarControl(this);"       type="text">
                        &nbsp;&nbsp;&nbsp;&nbsp;at&eacute;: 
                        <input name="datafim" size=20       onfocus="showCalendarControl(this);"  type="text" id="datafim">
                        <br>
                        <br>
                        Agente: 
                        <?php 
                         print "<select name=agente>";
                         while($row = mysql_fetch_array($resultado))
	                       {  	                     
							 print"<option>$row[codagente] - $row[nome]</option>";
							 
							  }
							  print "</select>";						
						     ?>
                        <input type="checkbox" name="chktodos" value="1" checked>
                        Todos <br>
                        <br>
                        <script language="JavaScript">
<!--

function data()
{
dia=document.formulario.dia.value;
mes=document.formulario.mes.value;
ano=document.formulario.ano.value;

diaf=document.formulario.diaf.value;
mesf=document.formulario.mesf.value;
anof=document.formulario.anof.value;

if((mes=="04" || mes=="06" || mes=="09" || mes=="11")&&dia>30)
{
alert("Dia incorreto !!! O m&ecirc;s especificado cont&eacute;m no m&aacute;ximo 30 dias.");
}

if(ano%4==0&&mes=="02"&&dia>29)
{
alert("Dia incorreto !!! O m&ecirc;s especificado cont&eacute;m no m&aacute;ximo 29 dias.");
}

if(ano%4!=0&&mes=="02"&&dia>28)
{
alert("Dia incorreto !!! O m&ecirc;s especificado cont&eacute;m no m&aacute;ximo 28 dias.");
}


if((mesf=="04" || mesf=="06" || mesf=="09" || mesf=="11")&&diaf>30)
{
alert("Dia incorreto !!! O m&ecirc;s especificado cont&eacute;m no m&aacute;ximo 30 dias.");
}

if(anof%4==0&&mesf=="02"&&diaf>29)
{
alert("Dia incorreto !!! O m&ecirc;s especificado cont&eacute;m no m&aacute;ximo 29 dias.");
}

if(anof%4!=0&&mesf=="02"&&diaf>28)
{
alert("Dia incorreto !!! O m&ecirc;s especificado cont&eacute;m no m&aacute;ximo 28 dias.");
}


}

//-->
</script>
                    <tr>
	                  <td>&nbsp;</td>
                  </tr>
	          <tr>
	            <td width="385"><br>
	              <input name="enviar2" type="submit" value="Gerar Relat&oacute;rio" /></td>
	            </tr>
	          <input type="hidden" name="nivel" value="<? echo $nivel; ?>">
	          </table>
	        </form>
	      </div>
	      <div align="center"></div>
	      </td>
	    <td width="4">&nbsp;</td>
	    </tr>
	  <tr>
	    <td colspan="3" height="22"><div align="right">&nbsp;</div></td>
	    </tr>
	  <tr>
	    <td colspan="3" height="14"><div align="right"></div></td>
	    </tr>
    </table>	  <p class="menu01">&nbsp;</p></td>	
</tr>

</table>
<table border="0" cellpadding="0" cellspacing="0" width="1024" align="center" background="../../images/fon_top.gif">

<tr>
	<td width="331" height="32" align="right" ><p align="right" style="margin-right: 200px;">&nbsp;&nbsp;&nbsp;&nbsp;</p></td>
	<td width="44" align="right" valign="middle" ><img src="../../images/LogoP.png" alt="" width="21" height="25"></span></td>
	<td width="649" valign="middle" class="menu01">&nbsp;By Blueideas Tecnologia da Informa&ccedil;&atilde;o</td>
</tr>
</table>

<map name="Map">
  <area shape="rect" coords="21,2,202,40" href="../../blue_call/view/menu_audio.php">
  <area shape="rect" coords="207,0,360,40" href="../../blue_call/view/monitor_principal.php" target="_blank">
  <area shape="rect" coords="366,2,489,39" href="../../blue_call/view/cadastro.php">
  <area shape="rect" coords="494,2,668,40" href=".../../../../blue_call/view/funcoes.php">
  <!--<area shape="rect" coords="615,2,800,41" href="../../blue_call/view/liberar_operador.php">-->
  <area shape="rect" coords="676,3,759,40" href="../../logout.php">
</map>

</body>
</html>

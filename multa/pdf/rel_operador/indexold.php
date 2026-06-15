<HTML>
<head>
<title>Vigban</title>
<script type="text/javascript" src="jquery-1.2.3.min.js"></script>
</script>
 <style type="text/css">
/*margin and padding on body element
  can introduce errors in determining
  element position and are not recommended;
  we turn them off as a foundation for YUI
  CSS treatments. */
body {
	margin:0;
	padding:0;
}
 </style>

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
</head>
<BODY BGCOLOR=#FFFFFF id="www-url-cz" class="yui-skin-sam">
<div align="center">
 
    <tr>
      <td height="4"><table align="center" width="665" border="0" height="520" cellspacing="0" cellpadding="0">
        <tr>
          <td colspan="3" height="106">
            <div align="center"><a href="#"><img src="logo.gif" alt=""></a>&nbsp;</div></td>
          <br>
        <tr>
          <td height="29" colspan="3"><div align="left"><a href="#"></a></div></td>
        </tr>
        <tr>
          <td width="108">&nbsp;</td>
          <td width="497"><div align="center">
<div align="left"><span class="style9">Escolha um período para a emissão do relatório de ligaçoes:</span><br>
  <br>
</div>
<form action="relatorio.php" method="POST" name="formulario" >
<table width="491" border="0" align="center"> 
    <tr> 
                          
                          <td colspan="2"><p class="style13"><span class="style12">Informe 
                              o per&iacute;odo desejado </span>:</p>
                            <p>
                            <select name="dia">
                              <option value="dia">Dia</option>
                              <option value="01">01</option>
                              <option value="02">02</option>
                              <option value="03">03</option>
                              <option value="04">04</option>
                              <option value="05">05</option>
                              <option value="06">06</option>
                              <option value="07">07</option>
                              <option value="08">08</option>
                              <option value="09">09</option>
                              <option value="10">10</option>
                              <option value="11">11</option>
                              <option value="12">12</option>
                              <option value="13">13</option>
                              <option value="14">14</option>
                              <option value="15">15</option>
                              <option value="16">16</option>
                              <option value="17">17</option>
                              <option value="18">18</option>
                              <option value="19">19</option>
                              <option value="20">20</option>
                              <option value="21">21</option>
                              <option value="22">22</option>
                              <option value="23">23</option>
                              <option value="24">24</option>
                              <option value="25">25</option>
                              <option value="26">26</option>
                              <option value="27">27</option>
                              <option value="28">28</option>
                              <option value="29">29</option>
                              <option value="30">30</option>
                              <option value="31">31</option>
                            </select>
                            <select name ="mes">
                              <option value="mes">Mes</option>
                              <option value="01">Janeiro</option>
                              <option value="02">Fevereiro</option>
                              <option value="03">Marco</option>
                              <option value="04">Abril</option>
                              <option value="05">Maio</option>
                              <option value="06">Junho</option>
                              <option value="07">Julho</option>
                              <option value="08">Agosto</option>
                              <option value="09">Setembro</option>
                              <option value="10">Outubro</option>
                              <option value="11">Novembro</option>
                              <option value="12">Dezembro</option>
                            </select>
                            <select name="ano" onblur="data()">
                              <option value="ano">Ano</option>
                              <option value="2007">2007</option>
							  <option value="2008">2008</option>
							  <option value="2009">2009</option>
                            </select>
                            at&eacute; 
                            <select name="diaf" id="diaf">
                              <option value="dia">Dia</option>
                              <option value="01">01</option>
                              <option value="02">02</option>
                              <option value="03">03</option>
                              <option value="04">04</option>
                              <option value="05">05</option>
                              <option value="06">06</option>
                              <option value="07">07</option>
                              <option value="08">08</option>
                              <option value="09">09</option>
                              <option value="10">10</option>
                              <option value="11">11</option>
                              <option value="12">12</option>
                              <option value="13">13</option>
                              <option value="14">14</option>
                              <option value="15">15</option>
                              <option value="16">16</option>
                              <option value="17">17</option>
                              <option value="18">18</option>
                              <option value="19">19</option>
                              <option value="20">20</option>
                              <option value="21">21</option>
                              <option value="22">22</option>
                              <option value="23">23</option>
                              <option value="24">24</option>
                              <option value="25">25</option>
                              <option value="26">26</option>
                              <option value="27">27</option>
                              <option value="28">28</option>
                              <option value="29">29</option>
                              <option value="30">30</option>
                              <option value="31">31</option>
                            </select>
                            <select name ="mesf" id="mesf">
							 <option value="mes">Mes</option>
                              <option value="01">Janeiro</option>
                              <option value="02">Fevereiro</option>
                              <option value="03">Marco</option>
                              <option value="04">Abril</option>
                              <option value="05">Maio</option>
                              <option value="06">Junho</option>
                              <option value="07">Julho</option>
                              <option value="08">Agosto</option>
                              <option value="09">Setembro</option>
                              <option value="10">Outubro</option>
                              <option value="11">Novembro</option>
                              <option value="12">Dezembro</option>
                            </select>
                            <select name="anof" id="anof" onblur="data()">
                              <option value="ano">Ano</option>
                   			  <option value="2007">2007</option>
							  <option value="2008">2008</option>
							  <option value="2009">2009</option>
                            </select>
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
alert("Dia incorreto !!! O mês especificado contém no máximo 30 dias.");
}

if(ano%4==0&&mes=="02"&&dia>29)
{
alert("Dia incorreto !!! O mês especificado contém no máximo 29 dias.");
}

if(ano%4!=0&&mes=="02"&&dia>28)
{
alert("Dia incorreto !!! O mês especificado contém no máximo 28 dias.");
}


if((mesf=="04" || mesf=="06" || mesf=="09" || mesf=="11")&&diaf>30)
{
alert("Dia incorreto !!! O mês especificado contém no máximo 30 dias.");
}

if(anof%4==0&&mesf=="02"&&diaf>29)
{
alert("Dia incorreto !!! O mês especificado contém no máximo 29 dias.");
}

if(anof%4!=0&&mesf=="02"&&diaf>28)
{
alert("Dia incorreto !!! O mês especificado contém no máximo 28 dias.");
}


}

//-->
</script>
                            <br>
                            </p>
                <tr>
                      <td width="385"><br>
                  <input name="enviar" type="submit" value="Enviar" /></td>
                </tr><input type="hidden" name="nivel" value="<? echo $nivel; ?>">
</table>

 
</form>
</div>
            <div align="center"></div><p>
          </p></td>
          <td width="60">&nbsp;</td>
        </tr>
        
        

        
        <tr>
          <td colspan="3" height="22"><div align="right">&nbsp;</div>          </td>
        </tr>
        <tr>
          <td colspan="3" height="14"><div align="right"></div></td>
        </tr>
      </table>
        <div align="center"><a href="../../novaintranet/manual_de_instrucoes_pv8020_versao_er.pdf"></a></div></td>
    </tr>
</table>
</body>
</html>
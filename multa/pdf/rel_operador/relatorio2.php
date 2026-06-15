
<? 
header("Content-type: application/vnd.ms-excel");
header("Content-type: application/force-download");
header("Content-Disposition: attachment; filename=relatorio.xls");
header("Pragma: no-cache");



  include("conecta.php");
  
  $datai = $_REQUEST["ano"] . "-" .$_REQUEST["mes"] . "-" .$_REQUEST["dia"] ;
  $dataf =  $_REQUEST["anof"] . "-" .$_REQUEST["mesf"] . "-" .$_REQUEST["diaf"] ;
  
  $sql = "select * from cdr where calldate >= '$datai' and calldate <= '$dataf'";
  
  $resultado = mysql_query($sql) or die (mysql_error());
  
 echo" <style type='text/css'>
<!--
.style8 {font-size: x-small}
.style11 {color: #000099}
.style13 {font-family: Arial, Helvetica, sans-serif}
.style14 {font-size: x-small; font-weight: bold; color: #000099; font-family: Arial, Helvetica, sans-serif; }
-->
</style>
       
           <table width='973' border='1' bordercolor='#000000'>
            <tr>
              <td><span class='style14'><strong>calldate</strong></span></td>
              <td><span class='style14'><strong>clid</strong></span></td>
              <td><span class='style14'><strong>src</strong></span></td>
              <td><span class='style14'><strong>dst</strong></span></td>
              <td><span class='style14'><strong>dcontext</strong></span></td>
              <td><span class='style14'><strong>channel</strong></span></td>
              <td><span class='style14'><strong>dstchannel</strong></span></td>
              <td><span class='style14'><strong>lastapp</strong></span></td>
              <td><span class='style14'>lastdata</span></td>
              <td><span class='style14'>duration</span></td>
              <td><span class='style14'>billsec</span></td>
              <td><span class='style14'>disposition</span></td>
              <td><span class='style14'>amaflags</span></td>
              <td><span class='style14'>accountcode</span></td>
              <td><span class='style14'>userfield</span></td>
              <td><span class='style14'>uniqueid</span></td>
            </tr>";
  
  while ($linhas = mysql_fetch_array($resultado))
  {
   	echo " <tr>
              <td><span class='style1 style2 style3 style8'>".$linhas["calldate"]."</span></td>
              <td><span class='style1 style2 style3 style8'>".$linhas["clid"]."</span></td>
              <td><span class='style1 style2 style3 style8'>".$linhas["src"]."</span></td>
              <td><span class='style1 style2 style3 style8'>".$linhas["dst"]."</span></td>
              <td><span class='style1 style2 style3 style8'>".$linhas["dcontext"]."</span></td>
              <td><span class='style1 style2 style3 style8'>".$linhas["channel"]."</span></td>
              <td><span class='style1 style2 style3 style8'>".$linhas["dstchannel"]."</span></td>
              <td><span class='style1 style2 style3 style8'>".$linhas["lastapp"]."</span></td>
              <td><span class='style4 style8'>".$linhas["lastdata"]."</span></td>
              <td><span class='style4 style8'>".$linhas["duration"]."</span></td>
              <td><span class='style4 style8'>".$linhas["billsec"]."</span></td>
              <td><span class='style4 style8'>".$linhas["disposition"]."</span></td>
              <td><span class='style4 style8'>".$linhas["amaflags"]."</span></td>
              <td><span class='style4 style8'>".$linhas["accountcode"]."</span></td>
              <td><span class='style4 style8'>".$linhas["userfield"]."</span></td>
              <td><span class='style4 style8'>".$linhas["uniqueid"]."</span></td>
            </tr>";
  }
  echo "</table>";
  ?>
          
    

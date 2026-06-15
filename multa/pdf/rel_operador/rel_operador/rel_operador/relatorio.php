<?php

include "conecta.php";

$data1 = $_POST["datainicio"];
$dataf1= $_POST["datafim"];

$data = explode("/",$data1);
$dataf = explode("/",$dataf1);

$di = $data[2] . "-" .$data[1] . "-" .$data[0] ;
$df = $dataf[2] . "-" .$dataf[1] . "-" .$dataf[0] ;	

//$todos = '1';

$agente= isset($_POST["agente"]) ? addslashes(trim($_POST["agente"])) : FALSE;
$todos= isset($_POST["chktodos"]) ? addslashes(trim($_POST["chktodos"])) : FALSE;
//$di= $_POST["datainicio"];
//$df= $_POST["datafim"];


$sql = "truncate table tmpligop";
$resultad = mysql_query($sql) or die (mysql_error());

  //echo $todos;   
    if ($todos == '1') {
    //ligações de saida  
    $sql2 = "   SELECT count(*) as totalsaida, origem as codagente, round(AVG(tempo),2) as media , date(data_int) as calldate
                FROM canais, asteriskcdrdb.blueextratotarifa 
                where origem = codagente and LENGTH(destino) > 7
                and data_int >= '" . $di . "' and data_int <= '" . $df . "'  
                group by codagente, date(data_int)";
//echo$sql2;
        
    //ligações de entrada     
    $sql = "    select count(*) as totalentrada, RIGHT(dstchannel, 4) as agente, round(AVG(billsec),2) as media, date(calldate) as calldate   
                from asteriskcdrdb.cdr where dstchannel like 'Agent%'  
                and calldate >= '" . $di . "' and calldate <= '" . $df . "'  
                group by agente, date(calldate)";
    
    //echo $sql;
    }else {
    $agente = substr($agente, 0,4);
    //echo $agente;  
    $sql = "SELECT canal FROM canais where codagente = " .$agente;
//echo$sql;    
$resultado0 = mysql_query($sql) or die (mysql_error());
	while($row = mysql_fetch_array($resultado0))
	{
	   $canal = $row["canal"];
	
	}
	      
    //ligações de saida  
    $sql2 =" SELECT count(*) as totalsaida, codagente, round(AVG(billsec),2) as media, date(calldate) as calldate 
             FROM canais, asteriskcdrdb.cdr 
             where src = canal and LENGTH(dst) > 7 and calldate >= '" . $di . "' 
             and calldate <= '" . $df . "' and canal = ". $canal . " and disposition = 'ANSWERED'
             group by codagente, date(calldate)";
      //echo $sql2;
    //ligações de entrada  
    $sql = "  select count(*) as totalentrada, RIGHT(dstchannel, 4) as agente, round(AVG(billsec),2) as media, date(calldate) as calldate 
               from asteriskcdrdb.cdr where dstchannel like 'Agent%' and RIGHT(dstchannel, 4) = '" . $agente . "' 
               and calldate >= '" . $di . "' and calldate <= '" . $df . "'  
               group by agente, date(calldate)";

   //echo $sql;
        
    }  
    
    $resultado = mysql_query($sql) or die (mysql_error());
    while($rows = mysql_fetch_array($resultado))
	{	  //insert nas ligações de entrada 
	  $sql =  "insert into tmpligop(data, entrada, tempo_medio, agente) values('". $rows["calldate"] . "', ". $rows["totalentrada"] . ", ". $rows["media"] . ", '". $rows["agente"] . "') ";
	  $resultadoi = mysql_query($sql) or die (mysql_error());
	//echo $sql;
	}
	
	$resultado = mysql_query($sql2) or die (mysql_error());
while($rows = mysql_fetch_array($resultado)){	   
	//echo "saida";  
	//update ou insert nas ligações de saida para completar a tabela
     $sql = " select * from tmpligop where data = '". $rows["calldate"] . "' and agente = '". $rows["codagente"] . "'";
     //echo "$sql";
	 $result_id = mysql_query($sql) or die (mysql_error());
	 $total = mysql_num_rows($result_id);
	 $row = mysql_fetch_array($result_id);
if($total)
{	      		   
	  $sql = " update tmpligop set 
	           saida = ". $rows["totalsaida"] . ",
	           tempo_medio_saida = ". $rows["media"] . "
	           where data = '". $rows["calldate"] . "' 
	           and agente = '". $rows["codagente"] . "'";
	           
	  $resultadoi = mysql_query($sql) or die (mysql_error());
	
}else{
    $sql =  "insert into tmpligop(data, saida, tempo_medio_saida, agente) values('". $rows["calldate"] . "', ". $rows["totalsaida"] . ", ". $rows["media"] . ", '". $rows["codagente"] . "') ";
   // echo $sql;
    $result = mysql_query($sql) or die (mysql_error());
}

	}
	
	
	echo "Aguarde...";
 echo "<script>window.location=\"pdfintervalo.php?dataini=$data1&datafim=$dataf1\";</script>";
?>


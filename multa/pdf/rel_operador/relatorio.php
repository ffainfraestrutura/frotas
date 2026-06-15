<?php

include "conecta.php";


$data1 = $_POST["datainicio"];
$dataf1= $_POST["datafim"];

$data = explode("/",$data1);
$dataf = explode("/",$dataf1);

$di = $data[2] . "-" .$data[1] . "-" .$data[0] ;
$df = $dataf[2] . "-" .$dataf[1] . "-" .$dataf[0] ;	


$agente= isset($_POST["agente"]) ? addslashes(trim($_POST["agente"])) : FALSE;
$todos= isset($_POST["chktodos"]) ? addslashes(trim($_POST["chktodos"])) : FALSE;


echo $agente;

if ($agente =='109'){

     $todos = '1';
     
}


//$di= $_POST["datainicio"];
//$df= $_POST["datafim"];


$sql = "truncate table tmpligop";
//echo $sql;
$resultad = mysql_query($sql) or die (mysql_error());


$sql = "update flag set flag1 = 0";
//echo $sql;
$resultad = mysql_query($sql) or die (mysql_error());


 if ($todos == '1') {

 $sql = "SELECT id_agente FROM evento where dataevento > '" . $di . "' and dataevento < '" . $df . "' group by id_agente";

 // echo $sql;

$a = 0;


$resultadoagente = mysql_query($sql) or die (mysql_error());


	while($row = mysql_fetch_array($resultadoagente))
	{


           $agente = $row["id_agente"];

     
 

              $sql = "SELECT * FROM evento where id_agente = " . $agente . " and dataevento > '" . $di . "' and dataevento < '" . $df . "'";

             // echo $sql;

               $a = 0;


               $resultado0 = mysql_query($sql) or die (mysql_error());



	while($row = mysql_fetch_array($resultado0))
	{

	   $ramal = $row["ramal"];
	   
	    if( $row["tipo"] == "1"){ 

		 if( $a == "0"){ 	

			$dtei = $row["dataevento"];	


			}

                   }
                   

             if( $row["tipo"] == "0"){ 	

		       $dtef = $row["dataevento"];	

                   }


        $a++;

       //echo $a;          

	}

	
      
   //ligações de entrada  

    $sql = "   select count(*) as totalentrada, MID(agent, 7,4) as agente, round(AVG(data2),2) as media, date(time) as calldate, queuename
               from asteriskcdrdb.queue_log where MID(agent, 7,4) in ('" . $ramal . "') 
               and time >= '" . $dtei . "' and time <= '" . $dtef . "'  
	           and event in ('COMPLETECALLER', 'COMPLETEAGENT')	
               and  queuename <> 'AMBULATORIO'
               group by agente, queuename, date(time)";


   //echo $sql;
  

  
        
    $resultado = mysql_query($sql) or die (mysql_error());

    
	
    while($rows = mysql_fetch_array($resultado))

	{	  //insert nas ligações de entrada 


       if (trim($rows["queuename"]) <> ""){

            if (trim($rows["queuename"]) == "C_cartoes"){

                     
                     $nfila ="Clientes";

		}


             if (trim($rows["queuename"]) == "prestador"){

                     
                     $nfila = "Clinicas";

		}

             }



              $sql = " select * from tmpligop where data = '". $rows["calldate"] . "' and agente = '". $agente . "'";
            // echo "$sql";
	     $result_id = mysql_query($sql) or die (mysql_error());
	     $totali = mysql_num_rows($result_id);
	     $row = mysql_fetch_array($result_id);


             if($totali)
             {	      		   
         	  $sql = " update tmpligop set 
	           entrada2 = ". $rows["totalentrada"] . ",
	           tempo_medio2 = ". $rows["media"] . ",
                   fila2 = '". $nfila. "'
	           where data = '". $rows["calldate"] . "' 
	           and agente = '".  $agente . "'";
	           //echo $sql;           
	           $resultadoi = mysql_query($sql) or die (mysql_error());
	
                   }else{

	
                 	  $sql =  "insert into tmpligop(data, entrada, tempo_medio, agente,fila) values('". $rows["calldate"] . "', ". $rows["totalentrada"] . ", ". $rows["media"] . ", '". $agente. "', '". $nfila. "') ";
	  
                	  $resultadoi = mysql_query($sql) or die (mysql_error());

                       }



	
	}



}


     $sql2 =" select a.id_agente, b.dti, max(a.dataevento) dtf, a.ramal from evento a, 
     ( select id_agente, min(dataevento) dti from evento where dataevento >= '" . $di . "' 
     and dataevento <= '" . $df . "' 
     group by id_agente) b
     where dataevento >= '" . $di . "' 
     and dataevento <= '" . $df . "' 
     and a.id_agente = b.id_agente
     group by id_agente ";

     //echo $sql2;
  
	$resultado = mysql_query($sql2) or die (mysql_error());
	
 

     while($rows = mysql_fetch_array($resultado)){
	   
	//echo "saida";  
	//update ou insert nas ligações de saida para completar a tabela

            $dtei = $rows["dti"];
            $dtef = $rows["dtf"];
            $ramal = $rows["ramal"];
            $agente = $rows["id_agente"];

     	  //ligações de saida  
    		$sql =" SELECT count(*) as totalsaida, (src) as codagente, round(AVG(billsec),2) as media, date(calldate) as calldate
             FROM asteriskcdrdb.cdr 
             where LENGTH(dst) > 7 and calldate >= '" . $dtei . "'  
             and calldate <= '" . $dtef . "' 
             and src = '" . $ramal . "'
             and disposition = 'ANSWERED'
             group by src, date(calldate)";
              
             //echo $sql;

           $result_id = mysql_query($sql) or die (mysql_error());
           $total = mysql_num_rows($result_id);
	       $row = mysql_fetch_array($result_id);
 			
 			if($total)
                {	     


    

                 $sql = " select * from tmpligop where data = '". $row["calldate"] . "' and agente = '".  $agente . "'";
    			// echo "$sql";
	 				$result_id2 = mysql_query($sql) or die (mysql_error());
	 				$total2 = mysql_num_rows($result_id2);
	 				$row2 = mysql_fetch_array($result_id2);


     		if($total2)
      			{	      		   
	           $sql = " update tmpligop set 
	           saida = ". $row["totalsaida"] . ",
	           tempo_medio_saida = ". $row["media"] . "
	           where data = '". $row["calldate"] . "' 
	           and agente = '".  $agente . "'";
	//echo $sql;           
	            $resultadoi = mysql_query($sql) or die (mysql_error());
	
      			}else{

         			 $sql =  "insert into tmpligop(data, saida, tempo_medio_saida, agente) values('". $row["calldate"] . "', ". $row["totalsaida"] . ", ". $row["media"] . ", '". $agente . "') ";
  //  echo $sql;
          			$result = mysql_query($sql) or die (mysql_error());
            			}

        
	}
	}

}else{


 $agente = substr($agente, 0,4);

 

$sql = "SELECT * FROM evento where id_agente = " . $agente . " and dataevento > '" . $di . "' and dataevento < '" . $df . "'";

//echo $sql;

$a = 0;


$resultado0 = mysql_query($sql) or die (mysql_error());



	while($row = mysql_fetch_array($resultado0))
	{



	   $ramal = $row["ramal"];
	   
	    if( $row["tipo"] == "1"){ 

		 if( $a == "0"){ 	

			$dtei = $row["dataevento"];	


			}

                   }
	

		

                   

             if( $row["tipo"] == "0"){ 	

		       $dtef = $row["dataevento"];	

                   }


        $a++;

//        echo $dtei;
//	echo " / ";
//	echo $dtef;
        echo $a;          

	}


//ligações de saida  
    $sql2 =" SELECT count(*) as totalsaida, (src) as codagente, round(AVG(billsec),2) as media, date(calldate) as calldate 
             FROM asteriskcdrdb.cdr 
             where LENGTH(dst) > 7 and calldate >= '" . $dtei . "' 
             and calldate <= '" . $dtef . "' and src = '" . $ramal . "'
             and disposition = 'ANSWERED'
             group by src, date(calldate)";


     //echo $sql2;

    //ligações de entrada  



    $sql = "   select count(*) as totalentrada, MID(agent, 7,4) as agente, round(AVG(data2),2) as media, date(time) as calldate, queuename
               from asteriskcdrdb.queue_log where MID(agent, 7,4) in ('" . $ramal . "') 
               and time >= '" . $dtei . "' and time <= '" . $dtef . "'  
	       and event in ('COMPLETECALLER', 'COMPLETEAGENT')	
                and  queuename <> 'AMBULATORIO'
               group by agente, queuename, date(time)";


  // echo $sql;
  
        
      
    


    $resultado = mysql_query($sql) or die (mysql_error());

    
	
    while($rows = mysql_fetch_array($resultado))

	{	  //insert nas ligações de entrada 


       if (trim($rows["queuename"]) <> ""){

            if (trim($rows["queuename"]) == "C_cartoes"){

                     
                     $nfila ="Clientes";

		}


             if (trim($rows["queuename"]) == "prestador"){

                     
                     $nfila = "Clinicas";

		}

             }



              $sql = " select * from tmpligop where data = '". $rows["calldate"] . "' and agente = '". $agente . "'";
            // echo "$sql";
	     $result_id = mysql_query($sql) or die (mysql_error());
	     $totali = mysql_num_rows($result_id);
	     $row = mysql_fetch_array($result_id);


             if($totali)
             {	      		   
         	  $sql = " update tmpligop set 
	           entrada2 = ". $rows["totalentrada"] . ",
	           tempo_medio2 = ". $rows["media"] . ",
                   fila2 = '". $nfila. "'
	           where data = '". $rows["calldate"] . "' 
	           and agente = '".  $agente . "'";
	           //echo $sql;           
	           $resultadoi = mysql_query($sql) or die (mysql_error());
	
                   }else{

	
                 	  $sql =  "insert into tmpligop(data, entrada, tempo_medio, agente,fila) values('". $rows["calldate"] . "', ". $rows["totalentrada"] . ", ". $rows["media"] . ", '". $agente. "', '". $nfila. "') ";
	  
                	  $resultadoi = mysql_query($sql) or die (mysql_error());

                       }



	
	}
	
	$resultado = mysql_query($sql2) or die (mysql_error());
	
while($rows = mysql_fetch_array($resultado)){
	   
	//echo "saida";  
	//update ou insert nas ligações de saida para completar a tabela

     $sql = " select * from tmpligop where data = '". $rows["calldate"] . "' and agente = '".  $agente . "'";
    // echo "$sql";
	 $result_id = mysql_query($sql) or die (mysql_error());
	 $total = mysql_num_rows($result_id);
	 $row = mysql_fetch_array($result_id);


if($total)
{	      		   
	  $sql = " update tmpligop set 
	           saida = ". $rows["totalsaida"] . ",
	           tempo_medio_saida = ". $rows["media"] . "
	           where data = '". $rows["calldate"] . "' 
	           and agente = '".  $agente . "'";
	//echo $sql;           
	  $resultadoi = mysql_query($sql) or die (mysql_error());
	
}else{

    $sql =  "insert into tmpligop(data, saida, tempo_medio_saida, agente) values('". $rows["calldate"] . "', ". $rows["totalsaida"] . ", ". $rows["media"] . ", '". $rows["id_agente"] . "') ";
  //  echo $sql;
    $result = mysql_query($sql) or die (mysql_error());
}

}
}


          $sql = " select *  from agente";     
	  $resultadoag = mysql_query($sql) or die (mysql_error());
	
   		 while($rowag = mysql_fetch_array($resultadoag)){

			$sql = " update tmpligop set 
	           	agente = '". $rowag["nome"] . "'
	           	where agente in ('". $rowag["id_agente"] . "')";

				//echo $sql;
	     
	  		$resultadoag2 = mysql_query($sql) or die (mysql_error());
			}

	
	
	
	echo "Aguarde...";
 echo "<script>window.location=\"pdfintervalo.php?dataini=$data1&datafim=$dataf1&nome=$nome\";</script>";



?>


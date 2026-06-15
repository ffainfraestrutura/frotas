<?php
include("conecta.php");
include("periododao.php");
//recupera os valores digitados no formuário anterior
$data_ini = $_GET["dataini"];
$data_fim = $_GET["datafim"];
$intervalo = 2;
$acumulador = 0;

//formata os valores de data
//$d = explode("/",$data_ini);
//$data_ini = $data[2]."-".$data[1]."-".$data[0];
//$dataf = explode("/",$data_fim);
//$data_fim = $dataf[2]."-".$dataf[1]."-".$dataf[0];
$totais = 0;
$contador = 0;
$cont = 0;
$texto = ""; //inicializa a variavel do conteudo da pagina
$totalgeralEntrada = 0;
$totalgeralSaida = 0;

$data=date('d/m/Y');
define('FPDF_FONTPATH','fpdf/font/');
require_once("fpdf/fpdf.php");

$pdf=new FPDF();
$pdf->AliasNbPages( '{total}' );
$pdf->Open();
$pdf->AddPage();
$pdf->SetXY(10, 20);
$pdf->SetFont('Courier', 'B', 20);
$pdf->Cell(0, 2, $pdf->Image('img/logo.jpg', 1, 1,30,20)); 
$pdf->ln();

$pdf->Cell(75, 5, 'Relatório de ligações por agente');
$pdf->SetFont('Courier', 'I', 12);
$pdf->ln();
$pdf->ln();
$pdf->Cell(0, 5, ' Emitido em: '.$data.' ', 0, 0, 'R');

$pdf->ln();
$pdf->ln();


$pdf->SetFont('Courier', 'B', 10); 


	$pdf->Cell(30, 5, 'Relatório de: '.$data_ini.' até '.$data_fim.'', 0, 0, 'L');


$pdf->ln();

$pdf->SetLineWidth(0.5);
$pdf->Line(10, 27, 200, 27);
$pdf->ln(15);

$pdf->SetFont('Courier', 'B', 8);
$pdf->SetLineWidth(0.2);

$pdf->SetFillColor(153, 153, 153);
$pdf->SetFont('Courier', 'B', 8); 

$pdf->SetTextColor(239, 240, 240);


$pdf->Cell(25, 5, 'Agente',1,'J',1,1,0,'L'); 

$pdf->Cell(30, 5, 'Data',1,'J',1,1,0);

$pdf->Cell(20, 5, 'Entrada',1,'J',1,1,0);

$pdf->Cell(30, 5, 'Tempo Médio(MIN)',1,'J',1,1,0);

$pdf->Cell(20, 5, 'Saída',1,'J',1,1,0);

$pdf->Cell(30, 5, 'Tempo Médio(MIN)',1,'J',1,1,0);

//$pdf->Cell(30, 5, 'Total de ligações',1,'J',1,1,0); 


$pdf->ln(); 
$pdf->SetFont('Courier', '', 8); 
$pdf->SetTextColor(0, 0, 0);

//gera os dados do relatorio

            $sql = " select * from bluecall_frescato.tmpligop order by data";
            $resultado = mysql_query($sql) or die (mysql_error());
            //echo$sql;
            while($row = mysql_fetch_array($resultado)){
	
			// *************** FORMATANDO DATA ********************
			$d = explode ("-",$row['data']);
			$ano = $d[0];
			$mes = $d[1];
			$dia = $d[2];
			$data = $dia . "-" . $mes . "-" . $ano; 
			// ****************************************************			

                	 $pdf->Cell(25, 5, ''.$row["agente"].'',1,0); 
		        $pdf->Cell(30, 5, ''.$data.'',1,0);
		        $pdf->Cell(20, 5, ''.$row["entrada"].'',1,0);
		        $pdf->Cell(30, 5, ''.gmdate("H:i:s",$row["tempo_medio"]).'',1,0);
		        $pdf->Cell(20, 5, ''.$row["saida"].'',1,0);
		        $pdf->Cell(30, 5, ''.gmdate("H:i:s",$row["tempo_medio_saida"]).'',1,0); 
		        $pdf->ln();   
		        $horario[$cont] =  $row["data"];   
		        
		        if ($row["entrada"] <> "")
		        {
		        $ArrayEntrada[$cont] = $row["entrada"];
		        }
		        else
		        {
		        $ArrayEntrada[$cont] = 0;
		        }
		        
             if ($row["saida"] <> "")
		        {
		        $ArraySaida[$cont] = $row["saida"];
		        }
		        else
		        {
		        $ArraySaida[$cont] = 0;
		        }
		        
		      
		      
		        $totalgeralEntrada += $row["entrada"];
		        $totalgeralSaida += $row["saida"];
			 $totaltempoEntrada += $row["tempo_medio"];
			 $totaltempoSaida += $row["tempo_medio_saida"];
		          $cont ++;
            }
          

 $pdf->ln(); 
      
$pdf->Cell(50, 5, 'Total de Ligações de Entrada',1,'J',1,1,0,'L'); 
$pdf->Cell(30, 5, ''.$totalgeralEntrada.'',1,0); 
$pdf->Cell(40, 5, 'Tempo Médio Entrada',1,'J',1,1,0,'L'); 
$pdf->Cell(30, 5, ''.gmdate("H:i:s",($totaltempoEntrada/$cont)).'',1,0); 

$pdf->ln();
$pdf->ln();

$pdf->Cell(50, 5, 'Total de Ligações de Saída',1,'J',1,1,0,'L'); 
$pdf->Cell(30, 5, ''.$totalgeralSaida.'',1,0);
$pdf->Cell(40, 5, 'Tempo Médio Saída',1,'J',1,1,0,'L'); 
$pdf->Cell(30, 5, ''.gmdate("H:i:s",($totaltempoSaida/$cont)).'',1,0); 

//retirar fim
//geraGrafico2($horario,$ArrayEntrada,$totalgeralEntrada);

//geraGrafico3($horario,$ArraySaida,$totalgeralSaida);
//fim dos dados gerados para o relatorio

//$pdf->Cell(0, 2, $pdf->Image('grafico2.png', 1, 215,90,60));
 
//$pdf->Cell(0, 2, $pdf->Image('grafico3.png', 100, 215,90,60)); 

/*
$pdf->SetLineWidth(0.5);
$pdf->Line(10, 270, 200, 270);*/
$pdf->Output();

?>


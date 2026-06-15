<?php
include("conecta.php");
include("periododao.php");
//$objperiododao = new PeriodoDAO();

//recupera os valores digitados no formuário anterior
$data_ini = $_POST["dataini"];
$data_fim = $_POST["datafim"];
$periodo = $_POST["periodo"];
$acumulador = 0;
//formata os valores de data
$data = explode("/",$data_ini);
$data_ini = $data[2]."-".$data[1]."-".$data[0];
$dataf = explode("/",$data_fim);
$data_fim = $dataf[2]."-".$dataf[1]."-".$dataf[0];
$totais = 0;
$contador = 0;
$cont = 0;
$texto = ""; //inicializa a variavel do conteudo da pagina
$data=date('d/m/Y');
define('FPDF_FONTPATH','fpdf/font/');
require_once("fpdf/fpdf.php");

$pdf=new FPDF();
$pdf->AliasNbPages( '{total}' );
$pdf->Open();
$pdf->AddPage();
$pdf->SetXY(10, 20);
$pdf->SetFont('Helvetica', 'B', 20);
$pdf->Cell(0, 2, $pdf->Image('img/logo.jpg', 1, 1,50,20)); 
$pdf->ln();

$pdf->Cell(75, 5, 'Relatório de Ligações por Período');
$pdf->SetFont('Helvetica', 'I', 14);
$pdf->ln();
$pdf->ln();
$pdf->Cell(0, 5, ' Emitido em: '.$data.' ', 0, 0, 'R');

$pdf->ln();
$pdf->ln();


$pdf->SetFont('Arial', 'B', 12); 


	$pdf->Cell(30, 5, 'Relatório de: '.$data_ini.' até '.$data_fim.'', 0, 0, 'L');


$pdf->ln();

$pdf->SetLineWidth(0.5);
$pdf->Line(10, 27, 200, 27);
$pdf->ln(15);

$pdf->SetFont('Courier', 'B', 10);
$pdf->SetLineWidth(0.2);

$pdf->SetFillColor(153, 153, 153);
$pdf->SetFont('Arial', 'B', 10); 

$pdf->SetTextColor(239, 240, 240);


$pdf->Cell(40, 5, 'Período',1,'J',1,1,0,'L'); 

$pdf->Cell(40, 5, 'Total de chamadas',1,'J',1,1,0); 

//$pdf->Cell(30, 5, 'Total de ligações',1,'J',1,1,0); 


$pdf->ln(); 
$pdf->SetFont('Arial', '', 10); 
$pdf->SetTextColor(0, 0, 0);

//gera os dados do relatorio
$sql = "select count(*) as total from vwcdr where calldate >= '$data_ini' and calldate <= '$data_fim' and disposition = 'answered' ";

if($periodo <> "todos")
{
	$conteudo[] = LerConteudo();
		
	foreach($conteudo as $linha)
	{	
	//print_r($linha);
		foreach($linha as $linha2)
		{
			//print_r($linha2);
			$linhas = explode("=",$linha2);
		
			if (trim($linhas[1]) == trim($periodo))
			{
				$periodorel = $linhas[0];
			}
		}
	} 
	
	$periodos = explode("-",$periodo);
	$hora_ini = $periodos[0];
	$hora_fim = $periodos[1];
	
	$condicao = " and hora >= '$hora_ini' and hora < '$hora_fim' and disposition = 'answered'";
	
	$sql = $sql . $condicao;
    $resultado = mysql_query($sql) or die (mysql_error());

while($rows=mysql_fetch_array($resultado))
{
	$total = $rows["total"];
			 if($contador==0)
			 {
			 	$totais = $total;
			 }
			 else
			 {
			 	$totais = $totais . ",". $total;
			 }
			 $contador ++;
			 	$acumulador = $acumulador + $total;

            $pdf->Cell(40, 5, $periodorel,1,0); 
		    $pdf->Cell(40, 5, ''.$total.'',1,0); 
		    $pdf->ln(); 
        	        	 
      
      
}
	
}
elseif($periodo == "todos")
{
	$imagem = "ok";
	$conteudo[] = LerConteudo();
		
	foreach($conteudo as $linha)
	{
		//print_r($linha);
		foreach($linha as $linha2)
		{
		
		
		$periodos = explode("=",$linha2);
		$periodos3 = explode(" ",$periodos[1]);
		$periodos2 = explode("-",$periodos[1]);
		
	
		
		$hora_ini = $periodos2[0];
		$hora_fim = $periodos2[1];
		
	
		
		$periodo =  $periodos[0];
		
		if ($cont == 0)
		{
			
			$todosperiodos =  $periodo;
		}
		else
		{
	    $todosperiodos = $todosperiodos . ",".$periodo ;
		}
		
		$cont ++;
		
		
		$sql = "select count(*) as total from vwcdr where calldate >= '$data_ini' and calldate <= 					'$data_fim' and hora >= '$hora_ini' and hora < '$hora_fim' and disposition = 'answered' ";
		$resultado = mysql_query($sql) or die (mysql_error());
//echo $sql;
		while($rows=mysql_fetch_array($resultado))
		{
			 $total = $rows["total"];
			 
			  if($contador==0)
			 {
			 	$totais = $total;
			 }
			 else
			 {
			 	$totais = $totais . ",". $total;
			 }
			 $contador ++;
			 	$acumulador = $acumulador + $total;
			 	
			$pdf->Cell(40, 5, $periodo,1,0); 
		    $pdf->Cell(40, 5, ''.$total.'',1,0); 
		    $pdf->ln(); 
		}
		//echo $totais;
		geraGrafico1($data_ini,$data_fim,$periodo,$totais,$todosperiodos,$acumulador);
		
		
		
	}
	}
	
	
}
 $pdf->ln(); 
   /*   
$pdf->Cell(30, 5, 'Total de ligações',1,'J',1,1,0,'L'); 
$pdf->Cell(30, 5, ''.$totalgeral.'',1,0); 
*/


//fim dos dados gerados para o relatorio

if(!empty($imagem))
{      
$pdf->Cell(0, 2, $pdf->Image('grafico1.png', 30, 100,200,100)); 
}
/*
$pdf->SetLineWidth(0.5);
$pdf->Line(10, 270, 200, 270);*/
$pdf->Output();

?>


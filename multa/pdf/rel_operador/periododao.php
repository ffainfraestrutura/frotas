<?php

	     include "libchart/classes/libchart.php";
	function retornaPeriodo()
	{
		$fd = fopen ("admin/config.txt", "r");
		while (!feof ($fd))
		{
			$buffer = fgets($fd, 4096);
			$lines[] = $buffer;
		}	
		
		fclose ($fd);
		
		$texto = "<select name=periodo>";
		$texto = $texto . "<option value=''>selecione</option>";
		$texto = $texto . "<option value='todos'>todos</option>";
		foreach($lines as $linha)//monta o listbox com os valores de datas separados por um "-"
		{
			$dados = explode("=",$linha);
			$texto = $texto."<option value = ".$dados[1].">".$dados[0]."</option>";
		
		}
		return $texto;
	}
	
	function LerConteudo()
	{
		$fd = fopen ("admin/config.txt", "r");
		while (!feof ($fd))
		{
			$buffer = fgets($fd, 4096);
			$lines[] = $buffer;
		}	
		//print_r($lines);
		fclose ($fd);
		
		return $lines;
	}
		
/*********************função para gerar o gráfico do primeiro relatório*****************/
	function geraGrafico1($data_ini,$data_fim,$periodo,$totais,$todosperiodos,$acumulador)
	{
	
		
$resultado = "";
$totais = explode(",",$totais);

$i = 0;
		foreach ($totais as $tot)
		{
			if ($i==0)
		{
			$resultado = ($acumulador*$tot)/$acumulador;
		}
		else
		{
			$resultado = $resultado . ",". ($acumulador*$tot)/$acumulador;
		}
		$i++;
	}
		$resultado = explode(",",$resultado);
		$todosperiodos = explode(",",$todosperiodos);

		

	   $chart = new PieChart();

	$dataSet = new XYDataSet();
	$i = 0;
	
	foreach($todosperiodos as $periodo)
	{
	$dataSet->addPoint(new Point($periodo. ': ' .$resultado[$i], $resultado[$i]));
	$i++;
	}
	$chart->setDataSet($dataSet);
	$chart->setTitle("Chamadas por período");
	$chart->render("grafico1.png");
		
}
	
	
	
	
	
	/*********************************************/
function geraGrafico2($horarios,$totais,$totalchamadas)
	{
   $chart = new VerticalBarChart();

   $dataSet = new XYDataSet();
   $cont = 0;
		foreach($horarios as $horario)
		{
		
		 $dataSet->addPoint(new Point($horario, $totais[$cont]));
		 $cont ++;
		}
	
	
	
	/*$dataSet->addPoint(new Point("March 2005", 642));
	$dataSet->addPoint(new Point("April 2005", 800));
	$dataSet->addPoint(new Point("May 2005", 1200));
	$dataSet->addPoint(new Point("June 2005", 1500));
	$dataSet->addPoint(new Point("July 2005", 2600));*/
	$chart->setDataSet($dataSet);

	$chart->setTitle("Total de chamadas de entrada: ".$totalchamadas);
	$chart->render("grafico2.png");
 
	}	
	
	
	function geraGrafico3($horarios,$totais,$totalchamadas)
	{
   $chart = new VerticalBarChart();

   $dataSet = new XYDataSet();
   $cont = 0;
		foreach($horarios as $horario)
		{
		 $dataSet->addPoint(new Point($horario, $totais[$cont]));
		 $cont ++;
		}
	
	
	
	/*$dataSet->addPoint(new Point("March 2005", 642));
	$dataSet->addPoint(new Point("April 2005", 800));
	$dataSet->addPoint(new Point("May 2005", 1200));
	$dataSet->addPoint(new Point("June 2005", 1500));
	$dataSet->addPoint(new Point("July 2005", 2600));*/
	$chart->setDataSet($dataSet);

	$chart->setTitle("Total de chamadas de saída: ".$totalchamadas);
	$chart->render("grafico3.png");
 
	}	
?>

  
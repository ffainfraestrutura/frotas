<?php

// Includes
include("pChart/pData.class");
include("pChart/pChart.class");

// definicao do objeto, e adicao dos dados
$DataSet = new pData;
$DataSet->AddPoint(array(1,4,3,2,3,3,2,1,0,7,4,3,2,3,3,5,1,0,7));
$DataSet->AddSerie();
$DataSet->SetSerieName("Sample data","Serie1");

// Inicializacao do grafico
$Test = new pChart(700,230);
$Test->setFontProperties("Fonts/tahoma.ttf",10);
$Test->setGraphArea(40,30,680,200);
$Test->drawGraphArea(252,252,252);
$Test->drawScale($DataSet->GetData(), $DataSet->GetDataDescription(), 5,150,150,150,TRUE,0,2);
$Test->drawGrid(5,TRUE,230,230,230,255);

// pintura da linha
$Test->drawLineGraph($DataSet->GetData(), $DataSet->GetDataDescription());
$Test->drawPlotGraph($DataSet->GetData(), $DataSet->GetDataDescription(),3,2,255,255,255);

// Finalizacao do grafico
$Test->setFontProperties("Fonts/tahoma.ttf",10);
$Test->drawLegend(65,35, $DataSet->GetDataDescription(),255,255,255);
$Test->drawTitle(60,22,"www.oficinadanet.com.br", 50,50,50,585);
$Test->Stroke("Naked.png");
?>
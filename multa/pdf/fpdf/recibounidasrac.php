<?php
require'fpdf.php';
require('../../conecta.php');
require('../../conecta2.php');
header("Content-type: text/html; charset=utf-8");

$id=$_POST['id'];

//echo $id;

$sql = "SELECT * FROM bdfrota.tbmovidatramite where idtbmovidatramite='$id';";
$resultado = mysqli_query($conexao, $sql) or die(mysqli_error($conexao));
$row = mysqli_fetch_array($resultado, MYSQLI_BOTH);

$nome= $row['nome'];
$cpf= $row['cpf'];
$placa=$row['placa'];
$autoinfra=$row['autoinfra'];
$valor = $row['valor'];
$idmovida = $row['idmovida'];
$autoinfracao = $row['autoinfra'];
$idmulta = $row['idmulta'];


$valorlocador = '15.62';
$valortotal = $valor + $valorlocador;  //bcadd($valor, $valorlocador, 2);

//tratando valor
$valor=str_replace('.', ',', $valor);
$valortotal=str_replace('.', ',', $valortotal);

$hoje = date('Y-m-d');
$datah = explode("-", $hoje);
$ano = $datah[0];
$mes = $datah[1];
if ($mes == 1) {
	$mesd = 'janeiro';
} elseif ($mes == 2) {
	$mesd = 'fevereiro';
} elseif ($mes == 3) {
	$mesd = 'março';
} elseif ($mes == 4) {
	$mesd = 'abril';
} elseif ($mes == 5) {
	$mesd = 'maio';
} elseif ($mes == 6) {
	$mesd = 'junho';
} elseif ($mes == 7) {
	$mesd = 'julho';
} elseif ($mes == 8) {
	$mesd = 'agosto';
} elseif ($mes == 9) {
	$mesd = 'setembro';
} elseif ($mes == 10) {
	$mesd = 'outubro';
} elseif ($mes == 11) {
	$mesd = 'novembro';
} elseif ($mes == 12) {
	$mesd = 'dezembro';
}
$dia = $datah[2];

$sql2a = "SELECT MAX(idtbfuncionario) AS idtbfuncionario FROM bdcorp.tbfuncionario WHERE nome = '$nome'; ";
$resultado2a = mysqli_query($conexao, $sql2a) or die(mysqli_error($conexao));
$row2a = mysqli_fetch_array($resultado2a, MYSQLI_BOTH);
	$idtbfuncionario = $row2a['idtbfuncionario'];

//$sql2 = "SELECT rg, cpf, endereco, bairro, cidade, estado, cep, matricula FROM bdcorp.tbfuncionario WHERE nome = '$nome'; ";
$sql2 = "SELECT rg, cpf, endereco, bairro, cidade, estado, cep, matricula FROM bdcorp.tbfuncionario WHERE idtbfuncionario = '$idtbfuncionario'; ";
$resultado2 = mysqli_query($conexao, $sql2) or die(mysqli_error($conexao));
$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);

$rg = $row2['rg'];
$cpf = $row2['cpf'];
$endereco = utf8_encode($row2['endereco']);
$bairro = utf8_encode($row2['bairro']);
$cidade = utf8_encode($row2['cidade']);
$estado = $row2['estado'];
$cep = $row2['cep'];
$matricula = $row2['matricula'];

$sql3 = "SELECT renavam, chassi FROM bdfrota.tbveiculo WHERE placa='$placa'; ";
$resultado3 = mysqli_query($conexao, $sql3) or die(mysqli_error($conexao));
$row3 = mysqli_fetch_array($resultado3, MYSQLI_BOTH);

$chassi = $row3['chassi'];
$renavam = $row3['renavam'];

$sql4 = "SELECT numcnh FROM bdfrota.tbcnh WHERE matricula='$matricula'; ";
$resultado4 =  mysqli_query($conexao, $sql4) or die(mysqli_error($conexao));
$row4 = mysqli_fetch_array($resultado4, MYSQLI_BOTH);

$cnh = $row4['numcnh'];



if($idmovida != ''){
	$sql5 = "SELECT dataInfra, descricao, endereco, cidade, uf, renavam FROM bdfrota.tbmovidamultas WHERE idmovida = '$idmovida' ; ";
} else{
	if($idmulta == ''){
		$sql5 = "SELECT datainfracao, descricaoinfra, endereco, municipio  FROM bdfrota.tbmulta WHERE placa='$placa' AND autoinfracao='$autoinfracao'";
	} else{
		$sql5 = "SELECT datainfracao, descricaoinfra, endereco, municipio  FROM bdfrota.tbmulta WHERE idtbmulta='$idmulta'";
	}
}

$resultado5 = mysqli_query($conexao, $sql5) or die(mysqli_error($conexao));
$row5 = mysqli_fetch_array($resultado5, MYSQLI_BOTH);

$dataInfra = $row5[0];
$descricao = utf8_encode($row5[1]);
$enderecoinfra = utf8_encode($row5[2]);
$cidadeinfra = utf8_encode($row5[3]);
//$ufinfra = $row5['uf'];
//$renavam = $row5['renavam'];

//tratando data
$dataInfra1 = explode(" ", $dataInfra);
$dataInfra2 = explode("-", $dataInfra1[0]);
$dataInfraf = $dataInfra2[2]."/".$dataInfra2[1]."/".$dataInfra2[0]." ".$dataInfra1[1];

//UF-$ufinfra

class PDF extends FPDF
{
// Page header
function Header()
	{
		// Logo
		//$this->Image('../../src/images/logo-movida.png',10,6,30);
		// Arial bold (negrito) 11
		$this->SetFont('Times','B',12);
		// Cor de fundo
		$this->SetFillColor(255);
		// Line break (quebra de linha)
		$this->Ln(10);
		// Move to the right - espaçamento antes do texto começar
		$this->Cell(10);
		// Título
		$this->Cell(0,10, utf8_decode('Indicação de Condutor e Declaração de Responsabilidade'),0,1,'C','false');
		// Line break (quebra de linha)
		$this->Ln(2);
	}

}

// Instanciation of inherited class
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Times','',11);

$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0,6,utf8_decode("\nEu, $nome, portador (a) da Carteira de Habilitação (CNH) Nº $cnh, da Cédula de identidade RG: $rg (a) inscrito no CPF /MF sob o nº $cpf, motorista / condutor de veículo locado,  neste ato autorizo a empresa Ouro Verde Locações, CNPJ XXXXXXX e  LW TECNOLOGIA, para, nos termos das Resoluções 17/98, 72/98 e 149/03, do Conselho Nacional de Trânsito, indicar meu nome, como condutor do veículo locado, acima identificado, preencher meus dados no formulário de \"Indicação do Condutor\", inclusive assinatura, e representar-me perante os órgãos que compõe o Sistema Nacional de Trânsito, quando assim for necessário pela imputação de qualquer penalidade em face de infrações cometidas durante o período de contrato, em que o veículo locado de placa $placa, renavam: $renavam e chassi $chassi estiver sob minha responsabilidade ou mesmo que não estiver mais na posse do mesmo a responsabilidade permanece em relação as infrações recebidas \"a posteriori\", mas no periodo do contrato de locação em referência."),0, 'J');

$pdf->Ln(2);
$pdf->Cell(10);
$pdf->MultiCell(0,5,utf8_decode("\nAUTO INFRAÇÃO: $autoinfra\nDATA E HORA DA INFRAÇÃO: $dataInfraf"),0,'J');

$pdf->Ln(2);
$pdf->Cell(10);
$pdf->MultiCell(0,5,utf8_decode("\nPara atendimento às questões acima mencionadas, autorizo também o fornecimento de cópias de meus documentos (RG/CPF/CNH), bem como do Contrato de Locação, por mim assinado ou pela empresa onde trabalho, sempre que necessário.\n\nDeclaro conhecer todas as normas de trânsito, obrigando-rne a respeitá-las e comunicar, imediatamente Ouro Verde Locações, CNPJ XXXXXXXX, qualquer circunstância anormal que envolva o veículo.\n\nDeclaro, também, para todos os fins de direito que na qualidade de Locatário/Usuário do veículo acima discriminado, a partir da assinatura do termo, assumo total responsabilidade, tanto na esfera civil, como na esfera criminal, com relação à utilização do veículo, conforme pactuado no Contrato de Locação acima referido.\n\nDeclaro, por fim, assumir eventuais penalidades (multas e pontuação) aplicadas em decorrência de quaisquer infrações cometidas quando da condução do veículo locado e durante o período em que este estiver sob minha responsabilidade."),0,'J');

$pdf->Ln(2);
$pdf->Cell(10);
$pdf->MultiCell(0,5,utf8_decode("\nCuritiba, _____de ___________ de_____"),0,'C');

$pdf->Ln(5);
$pdf->Cell(10);
$pdf->MultiCell(0,5,utf8_decode("\n_______________________________________________________________\nAssinatura do Condutor\n(Assinatura Idêntica a da CNH)"),0,'C');


$pdf->Output();
?>


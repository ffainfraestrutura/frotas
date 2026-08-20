<?php
require '../181/fpdf.php';
require('../../../control/conecta.php');
header("Content-type: text/html; charset=utf-8");
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('memory_limit', '256M'); // Aumenta para 256MB

class PDF extends FPDF
{
// Page header
function Header()
	{	
		// Logo
		$this->Image('../../src/images/logo-localiza.png',10,6,35);
		// Arial bold (negrito) 11
		$this->SetFont('Arial','B',12);
		// Cor de fundo
		$this->SetFillColor(255);
		// Line break (quebra de linha)
		$this->Ln(0);
		// Move to the right - espaçamento antes do texto começar
		$this->Cell(35);
		// Título
		$this->Cell(0,10, utf8_decode('TERMO DE RESPONSABILIDADE POR INFRAÇÃO DE TRÂNSITO'),0,1,'J','false');
		// Line break (quebra de linha)
		$this->Ln(2);
	}

}


$id=$_POST['id'];
//$id = '3543';
//echo $id;

$sql = "SELECT * FROM bdfrota.tbmovidatramite where idtbmovidatramite= '$id';";
$resultado = mysqli_query($conexao, $sql) or die(mysqli_error($conexao));
$row = mysqli_fetch_array($resultado, MYSQLI_BOTH);

$nome= utf8_encode($row['nome']);
$matricula = $row['matricula'];
$cpf= $row['cpf'];
$placa= $row['placa'];
$autoinfra= $row['autoinfra'];
$valor = $row['valor'];
$idmovida = $row['idmovida'];

$valorlocador = '25.00';
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

$datahf = $dia."/".$mes."/".$ano;

$sql2 = "SELECT rg, cpf, endereco, bairro, cidade, estado, cep, matricula FROM bdcorp.tbfuncionario WHERE matricula = '$matricula'; ";
$resultado2 = mysqli_query($conexao, $sql2) or die(mysqli_error($conexao));
$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);

$rg = $row2['rg'];
$cpf = $row2['cpf'];
$endereco = utf8_encode($row2['endereco']);
$bairro = utf8_encode($row2['bairro']);
$cidade = utf8_encode($row2['cidade']);
$estado = $row2['estado'];
$cep = $row2['cep'];
//$matricula = $row2['matricula'];

$sql3 = "SELECT chassi, renavam, modelo FROM bdfrota.tbveiculo WHERE placa='$placa'; ";
$resultado3 = mysqli_query($conexao, $sql3) or die(mysqli_error($conexao));
$row3 = mysqli_fetch_array($resultado3, MYSQLI_BOTH);

$chassi = $row3['chassi'];
$renavam = $row3['renavam'];
$modelo = $row3['modelo'];

$sqla = "SELECT modelo FROM bdfrota.tbmodeloveic WHERE idtbmodeloveic='$modelo' ";
$resultadoa = mysqli_query($conexao, $sqla) or die(mysqli_error($conexao));
$rowa = mysqli_fetch_array($resultadoa, MYSQLI_BOTH);
	$modelo = utf8_encode($rowa['modelo']);

$sql4 = "SELECT numcnh, uf FROM bdfrota.tbcnh WHERE matricula='$matricula';";
$resultado4 =  mysqli_query($conexao, $sql4) or die(mysqli_error($conexao));
$row4 = mysqli_fetch_array($resultado4, MYSQLI_BOTH);
$cnh = $row4['numcnh'];
$ufcnh = utf8_encode($row4['uf']);

$sql5 = "SELECT datainfracao, descricaoinfra, endereco, municipio, orgao FROM bdfrota.tbmulta WHERE placa='$placa' AND autoinfracao='$autoinfra' ; ";
$resultado5 = mysqli_query($conexao, $sql5) or die(mysqli_error($conexao));
$row5 = mysqli_fetch_array($resultado5, MYSQLI_BOTH);

$dataInfra = $row5['datainfracao'];
$descricao = utf8_encode($row5['descricaoinfra']);
$endereco = utf8_encode($row5['endereco']);
$cidade = utf8_encode($row5['municipio']);
$orgao = utf8_encode($row5['orgao']);

//tratando data
$dataInfra1 = explode(" ", $dataInfra);
$dataInfra2 = explode("-", $dataInfra1[0]);
$dataInfraf = $dataInfra2[2]."/".$dataInfra2[1]."/".$dataInfra2[0]." às ".$dataInfra1[1]." horas";

// Instanciation of inherited class
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',10);

$pdf->Cell(8);
$pdf->MultiCell(30,5,utf8_decode("\nContrato nº:\n\nCliente:\n\n\nPlaca/Modelo:\n"),0,'J');

$pdf->Ln(-35);
$pdf->Cell(40);
$pdf->MultiCell(0,5,utf8_decode("\nRIO0007/21\nFFA INFRAESTRUTURA E SERVICOS LTDA\nRUA TANAGRA, nº42 - OLARIA\nCEP: 21031560 - RIO DE JANEIRO - RJ - BRASIL\n\n$placa/$modelo"),0,'J');

$pdf->Ln(2);
$pdf->Cell(10);
$pdf->MultiCell(0,5,utf8_decode("__________________________________________________________________________________"),0,'J');

$pdf->Ln(5);
$pdf->Cell(8); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0,6,utf8_decode("INFORMAÇÕES DO CONDUTOR QUE SERÁ INDICADO"),0, 'C');

$pdf->Ln(5);
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(40,6,utf8_decode("Nome do Condutor:"),0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('','U');
$pdf->Cell(46); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(70,6,utf8_decode("$nome     "),0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('','');
$pdf->Cell(116); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(20,6,utf8_decode("CPF:"),0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('','U');
$pdf->Cell(126); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(70,6,utf8_decode("$cpf     "),0, 'J');

$pdf->Ln(4);
$pdf->SetFont('','');
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(80,6,utf8_decode("Nº Registro Carteira Nacional de Habilitação:"),0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('','U');
$pdf->Cell(88); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(70,6,utf8_decode("$cnh "),0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('','');
$pdf->Cell(118); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(80,6,utf8_decode("UF(CNH):"),0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('','U');
$pdf->Cell(138); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(70,6,utf8_decode("$ufcnh "),0, 'J');

$pdf->Ln(4);
$pdf->SetFont('','');
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(10,6,utf8_decode("RG:"),0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('','U');
$pdf->Cell(18); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(30,6,utf8_decode("$rg "),0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('','');
$pdf->Cell(60); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(40,6,utf8_decode("Órgão Emissor:"),0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('','U');
$pdf->Cell(90); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(30,6,utf8_decode("$emissorrg "),0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('','');
$pdf->Cell(130); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(40,6,utf8_decode("UF(RG):"),0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('','U');
$pdf->Cell(147); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(30,6,utf8_decode("$ufrg "),0, 'J');

$pdf->Ln(8);
$pdf->SetFont('','');
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0,6,utf8_decode("O Usuário / condutor identificado acima declara que estava em posse do carro placa $placa
no dia $dataInfraf, momento do cometimento da infração nº AIT $autoinfra, emitida pelo(a) $orgao."),0, 'C');

$pdf->Ln(0);
$pdf->Cell(4); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0,6,utf8_decode("Data:"),0, 'C');

$pdf->Ln(-6);
$pdf->SetFont('','U');
$pdf->Cell(35); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0,6,utf8_decode("$datahf"),0, 'C');

$pdf->Ln(8);
$pdf->SetFont('','');
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0,6,utf8_decode("Assinatura: ____________________________________________________________"),0, 'C');

$pdf->Ln(1);
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0,6,utf8_decode("(Idêntica à assinatura da CNH)"),0, 'C');

$pdf->Ln(4);
$pdf->SetFont('','B', 9);
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0,6,utf8_decode("Considerando a ciência do Usuário quanto ao Contrato de Aluguel e Gestão de Frota nº xxxxxxx"),0, 'J');

$pdf->Ln(0);
$pdf->SetFont('','', 8.8);
$pdf->Cell(12); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0,6,utf8_decode("firmado entre LOCALIZA FLEET S/A, CNPJ 02.286.479/0001-08, com sede em Belo Horizonte/MG Localiza Gestão de Frotas, e o Cliente (“Contrato”), o Usuário / condutor declara que:\n1) Assume a responsabilidade pela infração supracitada cometida na condução do Carro alugado e pela pontuação decorrente desta infração, nos termos do artigo 5º e seus parágrafos, da Resolução 619/16 do CONTRAN, e da cláusula 1.7.1 do Contrato.\n2) Autoriza a Localiza Fleet a assinar o termo de apresentação do condutor / infrator das multas de trânsito que envolvam o Carro alugado, nos termos do artigo 257, parágrafos 1º, 3º, 7º e 8º, do Código Brasileiro de Trânsito, e da cláusula 1.7.1 do Contrato.\n3) É preposto autorizado pelo Cliente a conduzir os Carros alugados nos termos do Contrato.\n4) Não havendo identificação do infrator e sendo o veículo de propriedade de pessoa jurídica, será lavrada nova multa ao proprietário do veículo, mantida a originada pela infração, cujo valor é o da multa multiplicada pelo número de infrações iguais cometidas no período de doze meses.\n5) O preposto responsabiliza-se nas esferas cível, administrativa e penal, pela veracidade das informações e dos documentos fornecidos.\n6) A identificação do condutor infrator só surtirá efeito se estiver corretamente preenchida, assinada e acompanhada de cópia legível do documento de habilitação, além de documento que comprove a assinatura do condutor infrator, quando esta não constar do referido documento.\n"),0, 'J');

$pdf->Image('../../src/images/rodape-localiza.png', 50, 280, 120);

$pdf->Output();
?>


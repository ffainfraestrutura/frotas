<?php
require'fpdf.php';
require('../../conecta.php');
require('../../conecta2.php');
header("Content-type: text/html; charset=utf-8");

class PDF extends FPDF
{
// Page header
function Header()
	{
		// Arial bold (negrito) 11
		$this->SetFont('Arial','B',12);
		// Cor de fundo
		$this->SetFillColor(255);
		// Line break (quebra de linha)
		$this->Ln(0);
		// Move to the right - espaçamento antes do texto começar
		$this->Cell(10);
		// Título
		$this->Cell(0,10, utf8_decode('TERMO DE RESPONSABILIDADE POR INFRAÇÃO DE TRÂNSITO'),0,1,'J','false');
		// Line break (quebra de linha)
		$this->Ln(2);
	}

}


$id=$_POST['id'];
//$id = '682';
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

$sql4 = "SELECT numcnh FROM bdfrota.tbcnh WHERE matricula='$matricula';";
$resultado4 =  mysqli_query($conexao, $sql4) or die(mysqli_error($conexao));
$row4 = mysqli_fetch_array($resultado4, MYSQLI_BOTH);
$cnh = $row4['numcnh'];

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
$dataInfraf = $dataInfra2[2]."/".$dataInfra2[1]."/".$dataInfra2[0]." ".$dataInfra1[1];

// Instanciation of inherited class
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',11);

$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0,6,utf8_decode("Placa: $placa \nModelo: $modelo \n\nINFORMAÇÕES DO CONDUTOR QUE SERÁ INDICADO\n\nNome do condutor: $nome \nCPF: $cpf\nRG: $rg \nNº Registro Carteira Nacional de Habilitação: $cnh\n\nO usuário/condutor identificado acima declara que estava em posse do carro placa $placa de propriedade da Companhia de Locação das Américas, pessoa jurídica de direito privado, inscrita no CNPJ 10.215.988/0059-86 com sede em Ribeirão Preto-SP, no dia $dataInfraf, momento do cometimento da infração nº $autoinfra emitida pelo(a) órgão: $orgao. \n\n     1) Assume a responsabilidade pela infração supracitada cometida na condução do Carro alugado e pela pontuação decorrente desta infração, nos termos do artigo 5º e seus parágrafos, da Resolução 619/16 do CONTRAN, e da cláusula 1.7.1 do Contrato.\n     2) Autoriza a Localiza Fleet a assinar o termo de apresentação do condutor / infrator das multas de trânsito que envolvam o Carro alugado, nos termos do artigo 257, parágrafos 1º, 3º, 7º e 8º, do Código Brasileiro de Trânsito, e da cláusula 1.7.1 do Contrato.\n     3) É preposto autorizado pelo Cliente a conduzir os Carros alugados nos termos do Contrato.\n     4) Não havendo identificação do infrator e sendo o veículo de propriedade de pessoa jurídica, será lavrada nova multa ao proprietário do veículo, mantida a originada pela infração, cujo valor é o da multa multiplicada pelo número de infrações iguais cometidas no período de doze meses.\n   5) O preposto responsabiliza-se nas esferas cível, administrativa e penal, pela veracidade das informações e dos documentos fornecidos.\n     6) A identificação do condutor infrator só surtirá efeito se estiver corretamente preenchida, assinada e acompanhada de cópia legível do documento de habilitação, além de documento que comprove a assinatura do condutor infrator, quando esta não constar do referido documento.\n\n O compartilhamento dos dados do condutor se dará exclusivamente para fins de apuração da infração e responsabilidade decorrentes da infração de trânsito, motivo pelo qual deverão ser cumpridas todas as leis, regras e regulamentos aplicáveis ao tratamento de dados pessoais utilizados ou obtidos, incluindo, mas não se limitando, as leis e regulamentos que regem sobre a privacidade, confdencialidade, segurança e proteção de dados pessoais, em especial às disposições da Lei nº 13.709/2019.\n\n Data: $datah[2]/$datah[1]/$datah[0] \nAssinatura: ____________________________________________________________                                                                            (Idêntica à assinatura da CNH)"),0, 'J');



/*$pdf->Ln(2);
$pdf->Cell(10);
$pdf->MultiCell(0,5,utf8_decode("\nAUTO INFRAÇÃO: $autoinfra   -    DATA HORA $dataInfra \nDESCRIÇÃO: $descricao \nENDEREÇO INFRAÇÃO: $endereco - $cidade UF-$uf\n"),0,'J');
//$pdf->Ln(2);
$pdf->Cell(10);
$pdf->MultiCell(0,5,utf8_decode("\n\nbem como nomeio e constituo como minha bastante procuradora a empresa Movida Locacao de Veiculos S.A., Sociedade Anonima Fechada, inscrita no CNPJ sob o nº 07976147002295, com sede na Av Bias Fortes, 704, Lourdes, Município de Belo Horizonte, Estado de Minas Gerais, CEP: 30170-011, legítima proprietária do veículo acima descrito, para que em meu nome assine o \"Termo de Apresentação do Condutor Infrator\", relativo à multa acima descrita nos termos do artigo 257, parágrafo 7° do Código de Trânsito Brasileiro e a resolução do Contran n° 149, de 19 de setembro de 2013.\n\nDECLARO, ainda, ciência quanto aos termos do artigo 257, parágrafo 8° do Código de Trânsito Brasileiro, que prevê que em caso de não identificação do condutor infrator e, em sendo o veículo de propriedade de pessoa jurídica, será lavrada nova multa, cujo valor será o da multa originária multiplicada pelo número de infrações iguais, cometidas no período subsequente de 12 (doze) meses, bem como ser responsável pelas referidas multas até a formalização de minha indicação como condutor infrator junto ao órgão de trânsito
responsável. \n\n São Paulo, $dia de $mesd de $ano.\n\n\n\n\n\n\n\n                              ____________________________________________________________\n                                                           $nome \n\n\n"),0,'J');*/
$pdf->Output();
?>


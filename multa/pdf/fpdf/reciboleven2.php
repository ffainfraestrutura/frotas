<?php
require '../181/fpdf.php';
require('../../../control/conecta.php');
header("Content-type: text/html; charset=utf-8");
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('memory_limit', '256M'); // Aumenta para 256MB



/*$link = "$_SERVER[REQUEST_URI]";
$idaux = explode("=", $link);
$id = $idaux[1];*/

$id = $_POST['id'];
//echo $id;
//$id='2759';
$sql = "SELECT * FROM tbmovidatramite where idtbmovidatramite= '$id';";
$resultado = mysqli_query($conn, $sql) or die(mysqli_error($conn));
$row = mysqli_fetch_array($resultado, MYSQLI_BOTH);

$nome = $row['nome'];
$cpf = $row['cpf'];
$matricula = $row['matricula'];
$placa = $row['placa'];
$autoinfra = $row['autoinfra'];
//$valor = $row['valor'];
$dtcons = $row['dtcons'];

$locadora = $row['locadora'];
$idmulta = $row['idmulta'];

/*$valordescaux = $row['valor'] * 8 / 10;
$valordesc = sprintf('%0.2f', round($valordescaux, 2));
$valortotal2 = $valordesc+80;
$valortotal = sprintf('%0.2f', round($valortotal2, 2));*/

if ($idmulta != '') {
	$sql1 = "SELECT * FROM tbmulta where idtbmulta= '$idmulta';";
} else {
	$sql1 = "SELECT * FROM tbmulta where placa= '$placa' AND autoinfracao = '$autoinfra';";
}
//print $sql1;
$resultado1 = mysqli_query($conn, $sql1) or die(mysqli_error($conn));
$row1 = mysqli_fetch_array($resultado1, MYSQLI_BOTH);
$valor1 = $row1['valor'];
$valdesconto1 = $row1['valdesconto'];
$taxaadm1 = $row1['taxaadm'];
$juros = $row1['juros'];
$valortotal = $row1['valtotal'];
$valparcelas = $row1['valparcelas'];
$filial = $row1['filial'];
$dataInfra = $row1['datainfracao'];
$descricao = utf8_encode($row1['descricaoinfra']);
$enderecoinfra = utf8_encode($row1['endereco']);
$codmulta = $row1['codigom'];

$dataInfra1 = explode(" ", $dataInfra);
$dataInfra2 = explode("-", $dataInfra1[0]);
$dataInfraf = $dataInfra2[2] . "/" . $dataInfra2[1] . "/" . $dataInfra2[0] . " " . $dataInfra1[1];

if ($locadora == '') {
	$sql2 = "SELECT idlocador FROM tbveiculo WHERE placa='$placa';";
	$resultado2 = mysqli_query($conn, $sql2) or die(mysqli_error($conn));
	$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
	$locadora = $row2['idlocador'];
}

$sql2 = "SELECT 
    fi.CNPJ, fi.estado
FROM
    bdcorp.tbfuncionario f
        JOIN
    bdcorp.tbfilial fi ON fi.idtbfilial = f.codfilial
WHERE
    f.matricula = '$matricula'";
$resultado2 = mysqli_query($conn, $sql2) or die(mysqli_error($conn));
$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
$cnpj = $row2[0];
$estadofilial = $row2['Estado'];

if ($filial == '') {
	$sql3 = "SELECT unidade FROM tbveiculo WHERE placa='$placa';";
	$resultado3 = mysqli_query($conn, $sql3) or die(mysqli_error($conn));
	$row3 = mysqli_fetch_array($resultado3, MYSQLI_BOTH);
	$estadofilial = $row3['unidade'];
}


if ($estadofilial == 'SP') {
	if (empty($cnpj)) {
		$cnpj = '08.375.450/0005-02';
	}

	$cidadeass = 'SÃO PAULO/SP';

} elseif ($estadofilial == 'RJ') {
	if (empty($cnpj)) {
		$cnpj = '01.307.399/0001-10';
	}

	$cidadeass = 'RIO DE JANEIRO/RJ';

} elseif ($estadofilial == 'PR') {
	if (empty($cnpj)) {
		$cnpj = '08.375.450/0017-38';
	}

	$cidadeass = 'CURITIBA/PR';
}



if ($taxaadm1 == '' || $taxaadm1 == '0') {
	if ($locadora == '2' || $locadora == '9' || $locadora == '16' || $locadora == '17' || $locadora == '19') {//movida
		$taxaadm1 = round((0.15 * $valor1), 2);
	} elseif ($locadora == '3' || $locadora == '4' || $locadora == '14' || $locadora == '15') {//localiza
		$taxaadm1 = '25.00';
	} elseif ($locadora == '6' || $locadora == '8') {
		$taxaadm1 = round((0.2 * $valor1), 2);

	}
}

//if($valortotal == ''){
$valortotal = ($valor1 + $taxaadm1) - $valdesconto1;
//}

if ($taxaadm1 == '') {
	$taxaadm1 = '0.00';
}

//tratando valor
$valor1 = str_replace('.', ',', $valor1);
$valortotal = str_replace('.', ',', $valortotal);
$valdesconto1 = str_replace('.', ',', $valdesconto1);

$taxaadm1 = str_replace('.', ',', $taxaadm1);

$preco = explode(',', $valortotal);
$centavos = $preco[1];
$real = $preco[0];

if (strlen($centavos) == 1) {
	$valortotal = $valortotal . "0";
}




if (strpos($taxaadm1, ",") === false) {
	$taxaadm1 = $taxaadm1 . ",00";
}

if (strpos($valortotal, ",") === false) {
	$valortotal = $valortotal . ",00";
}

$hoje = date('Y-m-d');
$datah = explode("-", $hoje);
$ano = $datah[0];
$mes = $datah[1];

switch ($mes) {
	case 1:
		$mesd = 'janeiro';
		break;

	case 2:
		$mesd = 'fevereiro';
		break;

	case 3:
		$mesd = 'março';
		break;

	case 4:
		$mesd = 'abril';
		break;

	case 5:
		$mesd = 'maio';
		break;

	case 6:
		$mesd = 'junho';
		break;

	case 7:
		$mesd = 'julho';
		break;

	case 8:
		$mesd = 'agosto';
		break;

	case 9:
		$mesd = 'setembro';
		break;

	case 10:
		$mesd = 'outubro';
		break;

	case 11:
		$mesd = 'novembro';
		break;

	case 12:
		$mesd = 'dezembro';
		break;

	default:
		$mesd = '';
}

$dia = $datah[2];

$hojef = $datah[2] . "/" . $datah[1] . "/" . $datah[0];

$sql2a = "SELECT MAX(idtbfuncionario) AS idtbfuncionario FROM bdcorp.tbfuncionario WHERE nome = '$nome'; ";
$resultado2a = mysqli_query($conn, $sql2a) or die(mysqli_error($conn));
$row2a = mysqli_fetch_array($resultado2a, MYSQLI_BOTH);
$idtbfuncionario = $row2a['idtbfuncionario'];

//$sql2 = "SELECT rg, cpf, endereco, bairro, cidade, estado, cep, matricula FROM bdcorp.tbfuncionario WHERE nome = '$nome'; ";
$sql2 = "SELECT rg, cpf, endereco, bairro, cidade, estado, cep, matricula FROM bdcorp.tbfuncionario WHERE idtbfuncionario = '$idtbfuncionario'; ";
$resultado2 = mysqli_query($conn, $sql2) or die(mysqli_error($conn));
$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
$rg = $row2['rg'];
$orgaorg = $row2['orgaorg'];
$cpf = $row2['cpf'];
$endereco = utf8_encode($row2['endereco']);
$bairro = utf8_encode($row2['bairro']);
$cidade = utf8_encode($row2['cidade']);
$estado = $row2['estado'];
$cep = $row2['cep'];
$matricula = $row2['matricula'];

$sql3 = "SELECT renavam, chassi, modelo FROM tbveiculo WHERE placa='$placa'; ";
$resultado3 = mysqli_query($conn, $sql3) or die(mysqli_error($conn));
$row3 = mysqli_fetch_array($resultado3, MYSQLI_BOTH);
$chassi = $row3['chassi'];
$renavam = $row3['renavam'];
$modelo = $row3['modelo'];

$sql4 = "SELECT numcnh FROM tbcnh WHERE matricula='$matricula'; ";
$resultado4 = mysqli_query($conn, $sql4) or die(mysqli_error($conn));
$row4 = mysqli_fetch_array($resultado4, MYSQLI_BOTH);
$cnh = $row4['numcnh'];

$sql5 = "SELECT modelo FROM tbmodeloveic WHERE idtbmodeloveic='$modelo'; ";
$resultado5 = mysqli_query($conn, $sql5) or die(mysqli_error($conn));
$row5 = mysqli_fetch_array($resultado5, MYSQLI_BOTH);
$modelof = $row5['modelo'];

$sql6 = "SELECT tel_corp FROM bdcorp.tbfuncionario WHERE matricula='$matricula'; ";
$resultado6 = mysqli_query($conn, $sql6) or die(mysqli_error($conn));
$row6 = mysqli_fetch_array($resultado6, MYSQLI_BOTH);
$telefone = $row6['tel_corp'];
$ddd = substr($telefone, 0, 2);
$telefone = substr($telefone, 2);

class PDF extends FPDF
{
	// Page header

}

// Instanciation of inherited class
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

$pdf->Image('../../img/logo_hallen.png', 10, 6, 20);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(215);
$pdf->Ln(0.1);
$pdf->Cell(22);
// Title
$pdf->Cell(160, 20, 'RECIBO DE ADIANTAMENTO SALARIAL', 1, 1, 'C', 'true');

$pdf->SetFont('Arial', '', 10);
$pdf->SetFillColor(255);
$pdf->Ln(2);
$pdf->Cell(5);
$pdf->MultiCell(177, 5, utf8_decode("Nome do Empregador: HALLEN TELECOM\nCNPJ n.º $cnpj\n"), 1, 1);

$pdf->Ln(2);
$pdf->Cell(5);
$pdf->MultiCell(177, 5, utf8_decode("Nome do Empregado: $nome\nCPF: $cpf\n"), 1, 1);
$pdf->Ln(2);
$pdf->Cell(5);//R$ 15,62
$pdf->MultiCell(177, 5, utf8_decode("Adiantamento Salarial para pagamento de MULTA DO VEÍCULO $placa\n\nAuto de infração: $autoinfra\nValor da multa: R$ $valor1\nValor Taxa Administração Locadora: R$ $taxaadm1\n\nValor desconto: $valdesconto1\nTotal: R$ $valortotal\n\nDeclaro, para todos os efeitos, ter recebido a título de \"Adiantamento Salarial para pagamento de multa\", a importância de R$ $valortotal em espécie, e  em consonância com o disposto no art. 462, caput, da CLT, tenho a  plena ciência de que o respectivo valor será descontado, pelo  empregador, quando do pagamento da minha remuneração mensal relativa à folha de pagamento.\n\n$cidadeass, $dia de $mesd de $ano.\n\n\n\n\n\n                              ______________________________________________________\n                                                                   Assinatura do Empregado \n\n\n"), 1, 1);

// Instanciation of inherited class


$pdf->AddPage();

$pdf->Image('../../../src/logo/logo-leven.png', 80, 5, 45, 15);
// Arial bold (negrito) 11
$pdf->SetFont('Arial', 'B', 11);
// Cor de fundo
$pdf->SetFillColor(255);
// Line break (quebra de linha)
$pdf->Ln(10);
// Move to the right - espaçamento antes do texto começar
$pdf->Cell(35);
// Título
$pdf->MultiCell(0, 14.8, utf8_decode('FORMULÁRIO DE IDENTIFICAÇÃO DO CONDUTOR INFRATOR'), 0, 1, 'c', 'false');

$pdf->SetFont('Times', '', 12);
//$pdf->Image('../../src/images/logo-leven.png',20,5,45,15);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->Cell(20, 6, utf8_decode("PLACA: "), 0, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(30, 6, utf8_decode("$placa"), 1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(60, 6, utf8_decode("VEÍCULO (MODELO):"), 0, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(69, 6, utf8_decode("$modelof"), 1, 'J', false);

$pdf->Ln(8);
$pdf->Cell(10);
$pdf->Cell(50, 6, utf8_decode("AUTO DE INFRAÇÃO: "), 0, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(40, 6, utf8_decode("$autoinfra"), 1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(60, 6, utf8_decode("CÓDIGO DA INFRAÇÃO: "), 0, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(0, 6, utf8_decode("$codmulta"), 1, 'J', false);

$pdf->Ln(8);
$pdf->Cell(10);
$pdf->Cell(120, 6, utf8_decode("DATA EM QUE ESTAVA CONDUZINDO O VEÍCULO:"), 0, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(0, 6, utf8_decode("$dataInfraf"), 1, 'J', false);

$pdf->Ln(8);
$pdf->Cell(10);
$pdf->Cell(68, 6, utf8_decode("NOME (CONDUTORINFRATOR): "), 0, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(112, 6, utf8_decode("$nome"), 1, 'J', false);

$pdf->Ln(8);
$pdf->Cell(10);
$pdf->Cell(40, 6, utf8_decode("NÚMERO DA CNH:"), 0, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(40, 6, utf8_decode("$cnh"), 1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(20, 6, utf8_decode("CPF:"), 0, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(80, 6, utf8_decode("$cpf"), 1, 'J', false);

$pdf->Ln(8);
$pdf->Cell(10);
$pdf->Cell(60, 6, utf8_decode("Nº DE IDENTIDADE (RG):"), 0, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(40, 6, utf8_decode("$rg"), 1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(20, 6, utf8_decode("UF: "), 0, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(0, 6, utf8_decode("$orgaorg"), 1, 'J', false);

$pdf->Ln(8);
$pdf->Cell(10);
$pdf->Cell(60, 6, utf8_decode("ENDEREÇO ATUALIZADO:"), 0, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(0, 6, utf8_decode("$endereco"), 1, 'J', false);

$pdf->Ln(8);
$pdf->Cell(10);
$pdf->Cell(25, 6, utf8_decode("BAIRRO:"), 0, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(50, 6, utf8_decode("$bairro"), 1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(25, 6, utf8_decode("CIDADE:"), 0, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(0, 6, utf8_decode("$cidade"), 1, 'J', false);

$pdf->Ln(8);
$pdf->Cell(10);
$pdf->Cell(10, 6, utf8_decode("UF"), 0, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(10, 6, utf8_decode("$estado"), 1, 'J', false);
$pdf->Cell(25, 6, utf8_decode("CEP:"), 0, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(30, 6, utf8_decode("$cep"), 1, 'J', false);

$pdf->Ln(8);
$pdf->Cell(10);
$pdf->Cell(15, 6, utf8_decode("DDD"), 0, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(15, 6, utf8_decode("$ddd"), 1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(30, 6, utf8_decode("TELEFONE"), 0, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(50, 6, utf8_decode("$telefone"), 1, 'J', false);

$pdf->Ln(10);
$pdf->Cell(10);
$pdf->MultiCell(0, 6, utf8_decode("Declaramos, sob as penas da lei, a veracidade das informações neste FICI e dos documentos que o acompanham."), 0, 'J', false);

$pdf->Ln(8);
$pdf->Cell(110);
$pdf->Cell(40, 6, utf8_decode("DATA:"), 0, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(40, 6, utf8_decode("$hojef"), 1, 'J', false);

$pdf->Ln(12);
$pdf->Cell(10);
$pdf->MultiCell(70, 6, utf8_decode("________________________________\nAssinatura do Condutor/Infrator"), 0, 'J', false);
$pdf->Ln(-12);
$pdf->Cell(90);
$pdf->MultiCell(90, 6, utf8_decode("__________________________________\nAssinatura do Proprietário do Veículo"), 0, 'J', false);

$pdf->SetFont('Times', '', 9);
$pdf->Ln(8);
$pdf->Cell(5);
$pdf->MultiCell(0, 6, utf8_decode("Observações\n1. A indicação do condutor infrator somente será acatada e produzirá seus efeitos legais se este Formulário de Indicação de Condutor Infrator - FICI estiver corretamente preenchido, sem rasuras, com assinaturas originais do condutor e do proprietário do veículo e acompanhados dos documentos citados no irem abaixo.\n2. O FICI deverá estar acompanhado de cópia repográfica legível do documento de habilitação do infrator e documento de identificação do proprietário do veículo ou de seu representante legal, que, neste caso, deverá juntar o documento que comprove a representação (procuração).\n3. Se o proprietário for pessoa jurídica, apresentação dos atos constitutivos da empresa e identificação do responsável pela mesma.\n4. O proprietário poderá responder civil, penal e administrativamente pela veracidade das informações constantes no FICI.\n5. Caso o proprietário do veículo não informe o nome do condutor responsável pela infração, a pontuação será lançada em seu prontuário.\n6. As assinaturas do condutor/infrator e do proprietário do veículo deverão ser idênticas com as do último formulário do RENACH, de emissão das respectivas CNHs e serão conferidas pelo Detran/GO.\n7. Quando o proprietário do veículo não for habilitado, sua assinatura deverá ser conferida com os documentos do processo de registro do veículo.\n8. Em caso de Procuração, seguir as orientações descritas no site www.detran.go.gov.br no lik: Serviços com Procuração.\n9. A não identificação do condutor infrator acarretará as consequências constantes pár. 7º e 8º do Art. 257, do Código de Trânsito Brasileiro.\n10. Endereço para entrega do Formulário de Indicação de Condutor Infrator - FICI: Departamento Estadual de Trânsito de Goiás - Av. Atílio Correia Lima, s/n, Cidade Jardi, Goiânia - GO, CEP: 74425-091."), 0, 'J', false);

$pdf->Output();

/* \n\nValor desconto: $valdesconto1*/
?>
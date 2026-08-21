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
//$id = 8097;

//echo $id;
//$id='2759';
$sql = "SELECT * FROM bdautofrota.tbmovidatramite where idtbmovidatramite= '$id';";
$resultado = mysqli_query($conexao, $sql) or die(mysqli_error($conexao));
$row = mysqli_fetch_array($resultado, MYSQLI_BOTH);

$matcond = $row['matricula'];
$nome = $row['nome'];
$cpf = $row['cpf'];
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
    $sql1 = "SELECT * FROM bdautofrota.tbmulta where idtbmulta= '$idmulta';";
} else {
    $sql1 = "SELECT * FROM bdautofrota.tbmulta where placa= '$placa' AND autoinfracao = '$autoinfra';";
}

$resultado1 = mysqli_query($conexao, $sql1) or die(mysqli_error($conexao));
$row1 = mysqli_fetch_array($resultado1, MYSQLI_BOTH);
$valor1 = $row1['valor'];
$valdesconto1 = $row1['valdesconto'];
$taxaadm1 = $row1['taxaadm'];
$juros = $row1['juros'];
$valortotal = $row1['valtotal'];
$valorparcelas = $row1['valparcelas'];
$numparcelas = $row1['numparcelas'];
$filial = $row1['filial'];
$orgaoautuador = $row1['orgao'];
$codmulta = $row1['codigom'];

if ($locadora == '') {
    $sql2 = "SELECT idlocador FROM bdautofrota.tbveiculo WHERE placa='$placa';";
    $resultado2 = mysqli_query($conexao, $sql2) or die(mysqli_error($conexao));
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
    f.matricula = '$matcond'";
$resultado2 = mysqli_query($conexao, $sql2) or die(mysqli_error($conexao));
$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
$cnpj = $row2[0];
$estadofilial = $row2['estado'];

if ($filial == '') {
    $sql3 = "SELECT unidade FROM bdautofrota.tbveiculo WHERE placa='$placa';";
    $resultado3 = mysqli_query($conexao, $sql3) or die(mysqli_error($conexao));
    $row3 = mysqli_fetch_array($resultado3, MYSQLI_BOTH);
    $estadofilial = $row3['unidade'];
}


/*if($filial == 'FFA Sao Paulo' || $filial=='SP' || $filial=='FFA SP'){
    $cnpj='08.375.450/0005-02';
    $cidadeass='SÃO PAULO/SP';
}else{
    $cnpj='01.307.399/0001-10';
    $cidadeass='RIO DE JANEIRO/RJ';
}*/

// if ($estadofilial == 'SP') {
//     if (empty($cnpj)) {
//         $cnpj = '08.375.450/0005-02';
//     }

//     $cidadeass = 'SÃO PAULO/SP';

// } elseif ($estadofilial == 'RJ') {
//     if (empty($cnpj)) {
//         $cnpj = '01.307.399/0001-10';
//     }

//     $cidadeass = 'RIO DE JANEIRO/RJ';

// } elseif ($estadofilial == 'PR') {
//     if (empty($cnpj)) {
//         $cnpj = '08.375.450/0017-38';
//     }

//     $cidadeass = 'CURITIBA/PR';
// }

if ($taxaadm1 == '' || $taxaadm1 == '0') {
    if ($locadora == '2' || $locadora == '9' || $locadora == '16' || $locadora == '17' || $locadora == '19') {//movida
        $taxaadm1 = round((0.15 * $valor1), 2);
    } elseif ($locadora == '3' || $locadora == '4' || $locadora == '14' || $locadora == '15') {//localiza
        $taxaadm1 = '25.00';
    } elseif ($locadora == '6' || $locadora == '8') {
        $taxaadm1 = round((0.2 * $valor1), 2);

    } elseif ($locadora == '1') {
        $taxaadm1 = '20.70';

    }
}

if ($taxaadm1 == '') {
    $sql4 = "SELECT tipoposse FROM bdautofrota.tbveiculo WHERE placa='$placa';";
    $resultado4 = mysqli_query($conexao, $sql4) or die(mysqli_error($conexao));
    $row4 = mysqli_fetch_array($resultado4, MYSQLI_BOTH);
    $tipoposse = $row4['tipoposse'];

    if ($tipoposse == 'PROPRIO') {
        $taxaadm1 = '20.70';
    } else {
        $taxaadm1 = '0.00';
    }

}

$sql5 = "SELECT numcnh FROM bdautofrota.tbcnh WHERE matricula='$matcond'; ";
$resultado5 = mysqli_query($conexao, $sql5) or die(mysqli_error($conexao));
$row5 = mysqli_fetch_array($resultado5, MYSQLI_BOTH);
$numcnh = $row5['numcnh'];

$sql6 = "SELECT cpf, rg FROM bdcorp.tbfuncionario WHERE matricula='$matcond'; ";
$resultado6 = mysqli_query($conexao, $sql6) or die(mysqli_error($conexao));
$row6 = mysqli_fetch_array($resultado6, MYSQLI_BOTH);
$cpf = $row6['cpf'];
$rg = $row6['rg'];


//if($valortotal == ''){
$valortotal = ($valor1 + $taxaadm1) - $valdesconto1;
//}

//tratando valor
$valor1 = str_replace('.', ',', $valor1);
$valortotal = str_replace('.', ',', $valortotal);
$valdesconto1 = str_replace('.', ',', $valdesconto1);
$valorparcelas = str_replace('.', ',', $valorparcelas);

$taxaadmf = str_replace('.', ',', $taxaadm1);

$preco = explode(',', $valortotal);
$centavos = $preco[1];
$real = $preco[0];

if (strlen($centavos) == 1) {
    $valortotal = $valortotal . "0";
}

$precotaxaadmf = explode(',', $taxaadmf);
$centavostaxaadmf = $precotaxaadmf[1];
$realtaxaadmf = $precotaxaadmf[0];
/*if(strpos($taxaadm1, ",") === false){
    $taxaadm1 = $taxaadm1.",00";
}*/

if (strlen($centavostaxaadmf) == 1) {
    $vtaxaadmf = $taxaadmf . "0";
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

class PDF extends FPDF
{

    function RoundedRect($x, $y, $w, $h, $r, $corners = '1234', $style = '')
    {
        $k = $this->k;
        $hp = $this->h;
        if ($style == 'F')
            $op = 'f';
        elseif ($style == 'FD' || $style == 'DF')
            $op = 'B';
        else
            $op = 'S';
        $MyArc = 4 / 3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));

        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        if (strpos($corners, '2') === false)
            $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $y) * $k));
        else
            $this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);

        $xc = $x + $w - $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        if (strpos($corners, '3') === false)
            $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - ($y + $h)) * $k));
        else
            $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);

        $xc = $x + $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        if (strpos($corners, '4') === false)
            $this->_out(sprintf('%.2F %.2F l', ($x) * $k, ($hp - ($y + $h)) * $k));
        else
            $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);

        $xc = $x + $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', ($x) * $k, ($hp - $yc) * $k));
        if (strpos($corners, '1') === false) {
            $this->_out(sprintf('%.2F %.2F l', ($x) * $k, ($hp - $y) * $k));
            $this->_out(sprintf('%.2F %.2F l', ($x + $r) * $k, ($hp - $y) * $k));
        } else
            $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
    {
        $h = $this->h;
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c ',
            $x1 * $this->k,
            ($h - $y1) * $this->k,
            $x2 * $this->k,
            ($h - $y2) * $this->k,
            $x3 * $this->k,
            ($h - $y3) * $this->k
        ));
    }

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
$pdf->Ln(2);
$pdf->Cell(5);
$pdf->MultiCell(177, 5, utf8_decode("Nome do Empregador: HALLEN TELECOM\nCNPJ n.º $cnpj\n"), 1, 1);

$pdf->Ln(2);
$pdf->Cell(5);
$pdf->MultiCell(177, 5, utf8_decode("Nome do Empregado: $nome\nCPF: $cpf\n"), 1, 1);
$pdf->Ln(2);
$pdf->Cell(5);//R$ 15,62
$pdf->MultiCell(177, 5, utf8_decode("Adiantamento Salarial para pagamento de MULTA DO VEÍCULO $placa\n\nAuto de infração: $autoinfra\nValor da multa: R$ $valor1\nValor Taxa Administração Locadora: R$ $taxaadmf\n\nValor desconto: $valdesconto1\nTotal: R$ $valortotal\nQuantidade de Parcelas: $numparcelas\nValor das Parcelas: R$ $valorparcelas\n\nDeclaro, para os devidos fins, que recebi da empresa, a título de Adiantamento Salarial para pagamento de multa, a importância de R$ $valortotal, em espécie.\nEm conformidade com o disposto no artigo 462, caput, da Consolidação das Leis do Trabalho (CLT), estou ciente de que o referido valor será integralmente descontado da minha remuneração mensal, por meio de $numparcelas parcelas de R$ $valorparcelas, a serem abatidas diretamente na folha de pagamento, até a quitação total do valor antecipado.\n\n$cidadeass, $dia de $mesd de $ano.\n\n\n\n\n\n                              ______________________________________________________\n                                                                   Assinatura do Empregado \n\n\n"), 1, 1);

$pdf->AddPage();

// Logo
$pdf->Image('../../src/images/logo-prf.png', 0, 0, 0);

$pdf->SetFont('Arial', 'B', 7);
$pdf->Ln(10);

$pdf->MultiCell(0, 5, utf8_decode("FORMULÁRIO DE IDENTIFICAÇÃO DO CONDUTOR INFRATOR - FICI"), 0, 1);

//$pdf->Rect(5, 28.25, 100, 30, 'D');
$pdf->RoundedRect(5, 28.25, 100, 30, 1.5, '1234', '');

$pdf->SetFillColor(255);

$pdf->Ln(0.8);
$pdf->Cell(-3);
$pdf->MultiCell(51, 5, utf8_decode("IDENTIFICAÇÃO DO ÓRGÃO AUTUADOR"), 0, 1, 'L', true);

$pdf->SetFont('Arial', '', 7);
$pdf->Ln(1);
$pdf->Cell(-5);
$pdf->MultiCell(99, 5, utf8_decode("CÓDIGO DO ÓRGÃO AUTUADOR\n______________________________________________________________________"), 0, 1);

$pdf->Ln(0.5);
$pdf->Cell(-5);
$pdf->MultiCell(0, 5, utf8_decode("NOME ÓRGÃO AUTUADOR\n$orgaoautuador"), 0, 1);

/**/
//$pdf->Rect(108, 28.25, 100, 40, 'D');
$pdf->RoundedRect(108, 28.25, 100, 40, 1.5, '1234', '');

$pdf->SetFont('Arial', 'B', 7);
$pdf->Ln(-26.5);
$pdf->Cell(100);
$pdf->SetFillColor(255);
$pdf->MultiCell(43, 5, utf8_decode("IDENTIFICAÇÃO DA AUTUAÇÃO"), 0, 1, 'L', true);

$pdf->SetFont('Arial', '', 7);
$pdf->Ln(1);
$pdf->Cell(100);
$pdf->MultiCell(47.5, 5, utf8_decode("NÚMERO DO AUTO DE INFRAÇÃO\n$autoinfra"), 0, 1);

$pdf->Ln(-10);
$pdf->Cell(147.5);
$pdf->MultiCell(50, 5, utf8_decode("CÓDIGO DA INFRAÇÃO\n$codmulta"), 'L', 1);

$pdf->Ln(1);
$pdf->Cell(100);
$pdf->MultiCell(97.5, 5, utf8_decode("PLACA DO VEÍCULO\n$placa"), 0, 1);

$pdf->Ln(1);
$pdf->Cell(100);
$pdf->MultiCell(97.5, 5, utf8_decode("DATA LIMITE PARA IDENTIFICAÇÃO DO CONDUTOR INFRATOR"), 0, 1);

/**/
$pdf->SetFillColor(255);
//$pdf->Rect(5, 63, 100, 80, 'D');
$pdf->RoundedRect(5, 63, 100, 80, 1.5, '1234', '');

$pdf->SetFont('Arial', 'B', 7);
$pdf->Ln(1.8);
$pdf->Cell(-3);
$pdf->MultiCell(55, 5, utf8_decode("IDENTIFICAÇÃO DO CONDUTOR INFRATOR"), 0, 1, 'J', true);

$pdf->SetFont('Arial', '', 7);
$pdf->Ln(1);
$pdf->Cell(-5);
$pdf->MultiCell(99, 5, utf8_decode("NOME\n$nome"), 0, 1);

$pdf->Ln(1);
$pdf->Cell(-5);
$pdf->MultiCell(47.5, 5, utf8_decode("NÚMERO DA CNH\n$numcnh"), 'R', 1);

$pdf->Ln(-12);
$pdf->Cell(42.5);
$pdf->MultiCell(52.5, 5, utf8_decode("CPF\n$cpf"), 0, 1);

$pdf->Ln(8);
$pdf->Cell(-5);
$pdf->MultiCell(47.5, 5, utf8_decode("RG\n$rg"), 'R', 1);

$pdf->Ln(-10);
$pdf->Cell(42.5);
$pdf->MultiCell(52.5, 5, utf8_decode("UF (RG)\n____________________________________"), 0, 1);

$pdf->Ln(1);
$pdf->Cell(-5);
$pdf->MultiCell(100, 5, utf8_decode("INDICAÇÃO ACEITA EM"), 0, 1);

$pdf->Ln(1);
$pdf->Cell(-5);
$pdf->MultiCell(47.5, 5, utf8_decode("DATA\n________________________________"), 'R', 1);

$pdf->Ln(-10);
$pdf->Cell(42.5);
$pdf->MultiCell(52.5, 5, utf8_decode("HORA\n____________________________________"), 0, 1);

$pdf->Ln(1);
$pdf->Cell(-5);
$pdf->MultiCell(99, 5, utf8_decode("ASSINATURA"), 0, 1);

/**/
//$pdf->Rect(108, 72, 100, 155, 'D');
$pdf->RoundedRect(108, 72, 100, 155, 1.5, '1234', '');

$pdf->SetFont('Arial', 'B', 7);
$pdf->Ln(-52);
$pdf->Cell(100);
$pdf->MultiCell(22, 5, utf8_decode("OBSERVAÇÕES"), 0, 1, 'J', true);

$pdf->SetFont('Arial', '', 7);
$pdf->Ln(1);
$pdf->Cell(100);
$pdf->MultiCell(97, 5, utf8_decode("1. Não havendo indicação do condutor infrator no prazo legal, o
principal condutor será considerado responsável pela infração ou, em sua ausência, o proprietário do veículo, nos termos do § 7º do art. 257 do CTB.\n2. Se o proprietário do veículo for pessoa jurídica e o condutorinfrator não tenha sido identificado no prazo legal, será lavrada nova multa ao proprietário do veículo, mantida a originada pela infração, cujo valor é o da multa multiplicada pelo número de infrações iguais cometidas no período de doze meses, nos termos do § 8º do art. 257 do CTB.\n3. No caso de preenchimento manual do formulário, a indicação do condutor infrator somente será acatada e produzirá efeitos legais se o formulário de identificação do condutor estiver corretamente
preenchido, sem rasuras, com as assinaturas originais do condutor e do proprietário do veículo ou principal condutor.\n4. Se o proprietário do veículo for pessoa jurídica, e não tenha sido possível a coleta da assinatura do condutor infrator, deverá ser anexado a este formulário, cópia de documento onde conste cláusula de responsabilidade por infrações cometidas pelo condutor e comprovante da posse do veículo no momento do cometimento da infração.\n5. Se o proprietário do veículo for Órgão ou Entidade Pública, e não tenha sido possível a coleta da assinatura do condutor infrator, deverá ser anexado a este formulário, ofício do representante legal do Órgão ou Entidade Pública identificando o condutor infrator, acompanhando de cópia de documento que comprove a condução do veículo no momento da infração.\n6. Este formulário deverá ser apresentado juntamento com procuração, quando for o caso.\n7. O requerente é responsável penal, cível e administrativamente pela veracidade das informações e dos documentos fornecidos.\n8. O formulário preenchido, datado e assinado manualmente deverá
ser entregue ao órgão autuador conforme sua orientação. Para mais informações, consulte o endereço eletrônico do órgão autuador ou entre em contato diretamente."), 0, 1, 'J');

/**/

//$pdf->Rect(5, 148, 100, 78.6, 'D');
$pdf->RoundedRect(5, 148, 100, 78.6, 1.5, '1234', '');

$pdf->SetFont('Arial', 'B', 7);
$pdf->Ln(-80);
$pdf->Cell(-3);
$pdf->MultiCell(80, 5, utf8_decode("IDENTIFICAÇÃO DO PROPRIETÁRIO OU PRINCIPAL CONDUTOR"), 0, 1, 'J', true);

$pdf->SetFont('Arial', '', 7);
$pdf->Ln(1);
$pdf->Cell(-5);
$pdf->MultiCell(99, 5, utf8_decode("NOME\n______________________________________________________________________"), 0, 1);

$pdf->Ln(1);
$pdf->Cell(-5);
$pdf->MultiCell(99, 5, utf8_decode("INDICAÇÃO REALIZADA EM\n"), 0, 1);

$pdf->Ln(1);
$pdf->Cell(-5);
$pdf->MultiCell(47.5, 5, utf8_decode("DATA\n________________________________"), 'R', 1);

$pdf->Ln(-10);
$pdf->Cell(42.5);
$pdf->MultiCell(52.5, 5, utf8_decode("HORA\n___________________________________"), 0, 1);

$pdf->Ln(1);
$pdf->Cell(-5);
$pdf->MultiCell(99, 5, utf8_decode("ASSINATURA"), 0, 1);

$pdf->Ln(45);
$pdf->Cell(-10);
$pdf->MultiCell(290, 0, utf8_decode("__ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __ __"), 0, 1);

//$pdf->Rect(5, 240, 200, 40, 'D');
$pdf->RoundedRect(5, 240, 200, 50, 1.5, '1234', '');

$pdf->SetFont('Arial', 'B', 7);
$pdf->Ln(8);
$pdf->Cell(50);
$pdf->MultiCell(80, 5, utf8_decode("SOLICITAÇÃO DE IDENTIFICAÇÃO DE CONDUTOR INFRATOR"), 0, 'C', true);

$pdf->SetFont('Arial', '', 7);
$pdf->Ln(1);
$pdf->MultiCell(0, 5, utf8_decode("Apenas para Protocolo Presencial - Para uso do Órgão Autuador"), 0, 'C', true);

$pdf->Ln(1);
$pdf->MultiCell(100, 5, utf8_decode("RECEBIDO EM: _______________________________________________\n"), 'R', 1);
$pdf->Ln(1);
$pdf->MultiCell(100, 5, utf8_decode("NOME/MATRÍCULA/RG: ________________________________________\n"), 'R', 1);
$pdf->Ln(1);
$pdf->MultiCell(100, 5, utf8_decode("ASSINATURA/CARIMBO:"), 'R', 1);

/**/
$pdf->Ln(-17);
$pdf->Cell(100);
$pdf->MultiCell(100, 5, utf8_decode("ÓRGÃO AUTUADOR:____________________________________\n"), 0, 1);

$pdf->Ln(1);
$pdf->Cell(100);
$pdf->MultiCell(100, 5, utf8_decode("NÚMERO DO AUTO DE INFRAÇÃO:___________________________\n"), 0, 1);

$pdf->Ln(1);
$pdf->Cell(100);
$pdf->MultiCell(100, 5, utf8_decode("CÓDIGO DA INFRAÇÃO:___________________________\n"), 0, 1);

$pdf->Ln(1);
$pdf->Cell(100);
$pdf->MultiCell(100, 5, utf8_decode("PLACA DO VEÍCULO:___________________________\n"), 'L', 1);





$pdf->Output();

/* \n\nValor desconto: $valdesconto1*/
?>
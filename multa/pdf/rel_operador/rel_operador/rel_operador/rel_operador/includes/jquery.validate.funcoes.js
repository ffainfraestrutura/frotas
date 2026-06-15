/*
 * NOVO METODO PARA O JQUERY VALIDATE
 * VALIDA DATA NO FORMATO (DD/MM/AAAA)
 */
jQuery.validator.addMethod("validaDataBR", function(value, element) {  
//declarando variáveis
	var checkstr = "0123456789";
	var DateValue = value;
	var DateTemp = "";
	var seperator = ".";
	var day;
	var month;
	var year;
	var leap = 0;
	var err = 0;
	var i;
   	
   	/* verificando se valor recebido está correto */
   	if(DateValue.length != "10") return false;
   	/* Apaga as "/" */
   	for (i = 0; i < DateValue.length; i++) {
		if (checkstr.indexOf(DateValue.substr(i,1)) >= 0) {
	    	DateTemp = DateTemp + DateValue.substr(i,1);
	  	}
   	}
   	DateValue = DateTemp;
	/* separando os valores digitados */
	year = DateValue.substr(4,4);
	month = DateValue.substr(2,2);
	day = DateValue.substr(0,2);
	year = parseInt(year);
	/* Ano errado se o ano digitado for = 0000 */
	if (year < 1900) return false;
	/* Validação do mês */
	if ((month < 1) || (month > 12)) return false;
	/* validação do dia */
	if (day < 1) return false;
	/* Validação do ano bisexto / fevereiro / dia */
	if ((year % 4 == 0) || (year % 100 == 0) || (year % 400 == 0)) leap = 1;
	if ((month == 2) && (leap == 1) && (day > 29)) return false;
	if ((month == 2) && (leap != 1) && (day > 28)) return false;
	/* Validação de outros meses */
	if ((day > 31) && ((month == "01") || (month == "03") || (month == "05") || (month == "07") || (month == "08") || (month == "10") || (month == "12"))) return false;
	if ((day > 30) && ((month == "04") || (month == "06") || (month == "09") || (month == "11"))) return false;
	/* Se 00 for digitado, deleta o valor */
	if ((day == 00) || (month == 00) || (year == 0000)) return false;
	/* Se não existe erro, retorna TRUE */
  	return true;
}, "Informe uma data válida");  // Mensagem padrão

/*
 * NOVO METODO PARA O JQUERY VALIDATE
 * VALIDA DATA E HORA NO FORMATO (DD/MM/AAAA HH:MM) 
 */
jQuery.validator.addMethod("validaDataHoraBR", function(value, element) {  
     //contando chars  
    if(value.length!=16) return false;  
     // dividindo data e hora  
    if(value.substr(10,1)!=' ') return false; // verificando se há espaço  
    var arrOpcoes = value.split(' ');  
    if(arrOpcoes.length!=2) return false; // verificando a divisão de data e hora  
    // verificando data  
    var data        = arrOpcoes[0];  
    var dia         = data.substr(0,2);  
    var barra1      = data.substr(2,1);  
    var mes         = data.substr(3,2);  
    var barra2      = data.substr(5,1);  
    var ano         = data.substr(6,4);  
    if(data.length!=10||barra1!="/"||barra2!="/"||isNaN(dia)||isNaN(mes)||isNaN(ano)||dia>31||mes>12)return false;  
    if ((mes==4||mes==6||mes==9||mes==11) && dia==31)return false;  
    if (mes==2 && (dia>29||(dia==29 && ano%4!=0)))return false;  
    // verificando hora  
    var horario     = arrOpcoes[1];  
    var hora        = horario.substr(0,2);  
    var doispontos  = horario.substr(2,1);  
    var minuto      = horario.substr(3,2);  
    if(horario.length!=5||isNaN(hora)||isNaN(minuto)||hora>23||minuto>59||doispontos!=":")return false;  
    return true;  
}, "Informe uma data e uma hora válida"); 

/*
 * NOVO METODO PARA O JQUERY VALIDATE
 * VALIDA CPF COM OU SEM OS CARACTERES ESPECIAIS
 */
jQuery.validator.addMethod("verificaCPF", function(value, element) {
    value = value.replace('.','');
    value = value.replace('.','');
    cpf = value.replace('-','');
    while(cpf.length < 11) cpf = "0"+ cpf;
    var expReg = /^0+$|^1+$|^2+$|^3+$|^4+$|^5+$|^6+$|^7+$|^8+$|^9+$/;
    var a = [];
    var b = new Number;
    var c = 11;
    for (i=0; i<11; i++){
        a[i] = cpf.charAt(i);
        if (i < 9) b += (a[i] * --c);
    }
    if ((x = b % 11) < 2) { a[9] = 0 } else { a[9] = 11-x }
    b = 0;
    c = 11;
    for (y=0; y<10; y++) b += (a[y] * c--);
    if ((x = b % 11) < 2) { a[10] = 0; } else { a[10] = 11-x; }
    if ((cpf.charAt(9) != a[9]) || (cpf.charAt(10) != a[10]) || cpf.match(expReg)) return false;
    return true;
}, "Informe um CPF válido."); // Mensagem padrão

/*
 * NOVO METODO PARA O JQUERY VALIDATE
 * VALIDA CNPJ COM 14 OU 15 DIGITOS
 * A VALIDAÇÃO É FEITA COM OU SEM OS CARACTERES SEPARADORES, PONTO, HIFEN, BARRA
 *
 * ESTE MÉTODO FOI ADAPTADO POR:
 * 
 * Shiguenori Suguiura Junior <junior@dothcom.net>
 * 
 * http://blog.shiguenori.com
 * http://www.dothcom.net
 * 
 */
jQuery.validator.addMethod("validaCnpj", function(cnpj, element) {
   // DEIXA APENAS OS NÚMEROS
   cnpj = cnpj.replace('/','');
   cnpj = cnpj.replace('.','');
   cnpj = cnpj.replace('.','');
   cnpj = cnpj.replace('-','');
 
   var numeros, digitos, soma, i, resultado, pos, tamanho, digitos_iguais;
   digitos_iguais = 1;
 
   if (cnpj.length < 14 && cnpj.length < 15){
      return false;
   }
   for (i = 0; i < cnpj.length - 1; i++){
      if (cnpj.charAt(i) != cnpj.charAt(i + 1)){
         digitos_iguais = 0;
         break;
      }
   }
 
   if (!digitos_iguais){
      tamanho = cnpj.length - 2
      numeros = cnpj.substring(0,tamanho);
      digitos = cnpj.substring(tamanho);
      soma = 0;
      pos = tamanho - 7;
 
      for (i = tamanho; i >= 1; i--){
         soma += numeros.charAt(tamanho - i) * pos--;
         if (pos < 2){
            pos = 9;
         }
      }
      resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
      if (resultado != digitos.charAt(0)){
         return false;
      }
      tamanho = tamanho + 1;
      numeros = cnpj.substring(0,tamanho);
      soma = 0;
      pos = tamanho - 7;
      for (i = tamanho; i >= 1; i--){
         soma += numeros.charAt(tamanho - i) * pos--;
         if (pos < 2){
            pos = 9;
         }
      }
      resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
      if (resultado != digitos.charAt(1)){
         return false;
      }
      return true;
   }else{
      return false;
   }
}, "Informe um CNPJ válido."); // Mensagem padrão 

/*
 * NOVO METODO PARA O JQUERY VALIDATE
 * COMPARA SE DATA É MENOR QUE OUTRA 
 */
jQuery.validator.addMethod('comparaData',function(value,element,params){
	
	params =jQuery(params).val();
	
	var data_ini = params.split('/');
	var dia_ini = data_ini[0];
	var mes_ini = data_ini[1];
	var ano_ini = data_ini[2];
	var data_i = parseInt(ano_ini+mes_ini+dia_ini);
	
	var data_fim = value.split('/');
	var dia_fim = data_fim[0];
	var mes_fim = data_fim[1];
	var ano_fim = data_fim[2];
	var data_f = parseInt(ano_fim+mes_fim+dia_fim);

	return this.optional(element) || !(data_f < data_i);

},"A data inicial deve ser menor ou igual a data final");

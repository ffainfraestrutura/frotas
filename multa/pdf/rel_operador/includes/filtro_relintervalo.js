$().ready(function(){
	$("#form").validate({  
		rules: {  
			dataini: {  
				required: true,
				maxlength: 200
			},
			datafim: {  
				required: true
			},
			periodo: {  
				required: true
			},
			intervalo: {  
				required: true
			}
						
		},  
		messages: {  
			dataini: {  
				required: "      Escolha a data de início!",
				maxlength: "   Por favor, insira no mÃ¡ximo 200 caracteres"
			},
			datafim: {  
				required: "      Escolha a data de fim!"
			},
			periodo: {  
				required: "      Selecione o perí­odo!"
			},
			intervalo: {  
				required: "      Selecione o intervalo!"
			}
		}
	});
});// JavaScript Document
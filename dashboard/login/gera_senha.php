<?php

	function geraSenha($tamanho = 8, $maiusculas = true, $numeros = true){
		// Caracteres de cada tipo
		$lmin = 'abcdefghijkmnpqrstuvwxyz';
		$lmai = 'ABCDEFGHIJKMNPQRSTUVWXYZ';
		$num = '123456789';

		// Vari�veis internas
		$retorno = '';
		$caracteres = '';

		// Agrupamos todos os caracteres que poder�o ser utilizados
		$caracteres .= $lmin;
		if ($maiusculas) $caracteres .= $lmai;
		if ($numeros) $caracteres .= $num;

		// Calculamos o total de caracteres poss�veis
		$len = strlen($caracteres);

		for ($n = 1; $n <= $tamanho; $n++) {
			// Criamos um n�mero aleat�rio de 1 at� $len para pegar um dos caracteres
			$rand = mt_rand(1, $len);
			// Concatenamos um dos caracteres na vari�vel $retorno
			$retorno .= $caracteres[$rand-1];
		}

		return $retorno;
	}

?>
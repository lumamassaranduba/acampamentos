<?php
// includes/tema.php

function getTemaAcampamento($nomeOuSigla) {
    // Remove acentos e converte para minúsculas para evitar erros de banco (ex: "Sênior" x "senior")
    $texto = mb_strtolower(trim($nomeOuSigla), 'UTF-8');
    $texto = preg_replace('/[áàãâä]/u', 'a', $texto);
    $texto = preg_replace('/[éèêë]/u', 'e', $texto);
    $texto = preg_replace('/[íìîï]/u', 'i', $texto);
    $texto = preg_replace('/[óòõôö]/u', 'o', $texto);
    $texto = preg_replace('/[úùûü]/u', 'u', $texto);
    $texto = preg_replace('/[ç]/u', 'c', $texto);

    // Dicionário de temas com base nos logos oficiais de Regente Feijó
    $temas = [
        'fac' => [
            'nome'   => 'FAC',
            'lema'   => 'Viajei nas asas de um anjo',
            'icone'  => 'bi-feather',
            'cor'    => '#1e3a2b', // Verde do FAC
            'bg_tag' => '#e8f5e9'
        ],
        'ces' => [
            'nome'   => 'CES',
            'lema'   => 'Confirmados no Espírito Santo',
            'icone'  => 'bi-fire',
            'cor'    => '#e60000', // Vermelho do CES
            'bg_tag' => '#ffebee'
        ],
        'mirim' => [
            'nome'   => 'MIRIM',
            'lema'   => 'Acampamento Mirim ',
            'icone'  => 'bi-sun-fill', // Eucaristia / Ostensório
            'cor'    => '#d97706', // Dourado
            'bg_tag' => '#fef3c7'
        ],
        'senior' => [
            'nome'   => 'SÊNIOR',
            'lema'   => 'Firmes na Fé',
            'icone'  => 'bi-tent-fill', // Barraca
            'cor'    => '#0a1931', // Azul Marinho
            'bg_tag' => '#e0e7ff'
        ],
        'pac' => [
            'nome'   => 'PAC',
            'lema'   => 'Pais de Adolescentes Cristãos',
            'icone'  => 'bi-house-heart-fill',
            'cor'    => '#0d9488', // Verde Água / Turquesa
            'bg_tag' => '#ccfbf1'
        ],
        'maanaim' => [
            'nome'   => 'MAANAIM',
            'lema'   => 'Onde os anjos acampam',
            'icone'  => 'bi-cloud-sun-fill', // Arca / Céu
            'cor'    => '#0284c7', // Azul Céu
            'bg_tag' => '#e0f2fe'
        ],
        'joam' => [
            'nome'   => 'JOAM',
            'lema'   => 'Jovens Avivados em Missão',
            'icone'  => 'bi-compass-fill',
            'cor'    => '#b45309', // Areia / Deserto
            'bg_tag' => '#fef3c7'
        ]
    ];

    // Busca exata ou por palavra contida
    foreach ($temas as $chave => $tema) {
        if (strpos($texto, $chave) !== false) {
            return $tema;
        }
    }

    // Se não achar nada, retorna um padrão neutro azul
    return [
        'nome'   => strtoupper($nomeOuSigla),
        'lema'   => 'Acampamento Paroquial',
        'icone'  => 'bi-shield-check',
        'cor'    => '#2563eb',
        'bg_tag' => '#dbeafe'
    ];
}
?>
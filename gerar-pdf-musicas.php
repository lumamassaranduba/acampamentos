<?php
// gerar-pdf-musicas.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Captura o ID da Edição
$edicao_id = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;

if ($edicao_id === 0) {
    die("Edição não especificada.");
}

// 2. Busca informações do Acampamento/Edição
$nome_edicao = "Edição #" . $edicao_id;
$stmt_info = $conn->prepare("SELECT e.nome AS edicao_nome, m.nome AS modalidade_nome 
                            FROM edicoes e 
                            LEFT JOIN modalidades m ON e.modalidade_id = m.id 
                            WHERE e.id = ?");
if ($stmt_info) {
    $stmt_info->bind_param("i", $edicao_id);
    if ($stmt_info->execute()) {
        $res = $stmt_info->get_result()->fetch_assoc();
        if ($res) {
            $mod = $res['modalidade_nome'] ?? '';
            $ed = $res['edicao_nome'] ?? '';
            $nome_edicao = trim("$mod - $ed");
        }
    }
}

// 3. Busca Músicas para esta edição
$stmt = $conn->prepare("SELECT * FROM apostilas_musicas WHERE edicao_id = ? ORDER BY categoria, subcategoria, titulo ASC");
$stmt->bind_param("i", $edicao_id);
$stmt->execute();
$result = $stmt->get_result();

$musicas = [
    'TEMA' => [],
    'MISSA' => [],
    'MOMENTOS' => []
];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cat = $row['categoria'];
        $sub = !empty($row['subcategoria']) ? $row['subcategoria'] : 'Geral';
        $musicas[$cat][$sub][] = $row;
    }
}

// Rótulos no estilo do modelo da foto
$titulos_categorias = [
    'TEMA' => 'TEMAS',
    'MISSA' => 'MISSA',
    'MOMENTOS' => 'PREGAÇÕES'
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Apostila de Músicas - <?= htmlspecialchars($nome_edicao) ?></title>
    <style>
        /* TAMANHO A5 (METADE DO A4) OU A4 EM 2 COLUNAS */
        @page {
            size: A4 landscape; /* Imprime em A4 Paisagem para dobrar ao meio como livreto */
            margin: 10mm 12mm;
        }

        body {
            font-family: Arial, sans-serif;
            color: #000000;
            margin: 0;
            padding: 0;
            font-size: 11px;
            line-height: 1.35;
        }

        /* DIVISÃO DA FOLHA EM 2 COLUNAS (LIVRETO MEIA FOLHA) */
        .conteudo-apostila {
            column-count: 2;
            column-gap: 25mm;
            column-rule: 1px dashed #cccccc;
        }

        .header-print {
            text-align: center;
            border-bottom: 2px solid #000000;
            padding-bottom: 6px;
            margin-bottom: 15px;
        }

        .header-print h1 {
            margin: 0;
            font-size: 18px;
            color: #000000;
            text-transform: uppercase;
        }

        .header-print h3 {
            margin: 2px 0 0 0;
            font-size: 12px;
            color: #444444;
            font-weight: normal;
        }

        /* ESTILO DOS TÍTULOS COM BALINHA/BULLET COMO NA IMAGEM */
        .categoria-titulo {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 15px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            page-break-after: avoid;
            break-after: avoid;
        }

        .categoria-titulo::before {
            content: "•";
            font-size: 18px;
            margin-right: 4px;
        }

        .subcategoria-titulo {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 10px;
            margin-bottom: 8px;
            page-break-after: avoid;
            break-after: avoid;
        }

        /* ESTRUTURA DAS MÚSICAS CENTRALIZADAS COMO NA FOTO */
        .musica-bloco {
            margin-bottom: 18px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .musica-cabecalho {
            text-align: center;
            margin-bottom: 8px;
        }

        .musica-titulo {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            display: block;
        }

        .musica-artista {
            font-size: 10px;
            color: #333333;
            display: block;
            margin-top: 2px;
        }

        .musica-letra {
            white-space: pre-line;
            font-size: 10.5px;
            color: #000000;
            text-align: left;
        }

        /* OCULTA BOTÕES AO IMPRIMIR */
        @media print {
            .no-print {
                display: none !important;
            }
        }

        .bar-acoes {
            background: #0F172A;
            padding: 10px;
            text-align: center;
            margin-bottom: 15px;
            border-radius: 6px;
        }

        .btn-imprimir {
            background-color: #C5A059;
            color: #ffffff;
            border: none;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>

    <!-- Barra de Impressão -->
    <div class="bar-acoes no-print">
        <button class="btn-imprimir" onclick="window.print()">🖨️ Imprimir Apostila (Meia Folha / Livreto)</button>
    </div>

    <div class="conteudo-apostila">
        
        <!-- Cabeçalho Principal -->
        <div class="header-print">
            <h1>Apostila de Músicas</h1>
            <h3><?= htmlspecialchars($nome_edicao) ?></h3>
        </div>

        <!-- Músicas Formatadas -->
        <?php foreach ($musicas as $cat_chave => $subcategorias): ?>
            <?php if (!empty($subcategorias)): ?>
                
                <div class="categoria-titulo">
                    <span style="text-decoration: underline;"><?= $titulos_categorias[$cat_chave] ?? $cat_chave ?></span>
                </div>

                <?php foreach ($subcategorias as $subcat => $lista_musicas): ?>
                    <?php if ($subcat !== 'Geral'): ?>
                        <div class="subcategoria-titulo"><?= htmlspecialchars($subcat) ?></div>
                    <?php endif; ?>

                    <?php foreach ($lista_musicas as $m): ?>
                        <div class="musica-bloco">
                            <div class="musica-cabecalho">
                                <span class="musica-titulo"><?= htmlspecialchars($m['titulo']) ?></span>
                                <?php if (!empty($m['artista'])): ?>
                                    <span class="musica-artista"><?= htmlspecialchars($m['artista']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="musica-letra"><?= htmlspecialchars($m['letra']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>

            <?php endif; ?>
        <?php endforeach; ?>

    </div>

</body>
</html>
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

// 2. Busca informações do Acampamento/Edição (Tabela corrigida para 'edicoes')
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

// 3. Busca Músicas ESTRITAMENTE para esta edição
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

// Rótulos amigáveis para as categorias
$titulos_categorias = [
    'TEMA' => '🔥 MÚSICAS TEMA',
    'MISSA' => '⛪ MÚSICAS DA MISSA',
    'MOMENTOS' => '🕊️ PREGAÇÕES E MOMENTOS'
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Apostila de Músicas - <?= htmlspecialchars($nome_edicao) ?></title>
    <style>
        @page {
            size: A4;
            margin: 15mm 15mm 15mm 15mm;
        }

        body {
            font-family: Arial, sans-serif;
            color: #0F172A;
            margin: 0;
            padding: 0;
            font-size: 12px;
        }

        .header-print {
            text-align: center;
            border-bottom: 2px solid #C5A059;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        .header-print h1 {
            margin: 0;
            font-size: 22px;
            color: #0F172A;
            text-transform: uppercase;
        }

        .header-print h3 {
            margin: 4px 0 0 0;
            font-size: 14px;
            color: #C5A059;
            font-weight: normal;
        }

        .categoria-titulo {
            background-color: #0F172A;
            color: #ffffff;
            padding: 8px 12px;
            font-size: 14px;
            font-weight: bold;
            margin-top: 25px;
            margin-bottom: 15px;
            border-left: 4px solid #C5A059;
            border-radius: 4px;
            page-break-after: avoid;
        }

        .subcategoria-titulo {
            color: #C5A059;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px solid #E2E8F0;
            padding-bottom: 4px;
            margin-top: 15px;
            margin-bottom: 10px;
            page-break-after: avoid;
        }

        .musica-card {
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
            page-break-inside: avoid;
            background-color: #FAFAFA;
        }

        .musica-cabecalho {
            border-bottom: 1px solid #CBD5E1;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }

        .musica-titulo {
            font-size: 13px;
            font-weight: bold;
            color: #0F172A;
        }

        .musica-artista {
            font-size: 11px;
            color: #C5A059;
            font-weight: bold;
            float: right;
        }

        .musica-letra {
            white-space: pre-line;
            font-size: 11px;
            line-height: 1.5;
            color: #334155;
        }

        /* Oculta os botões de ação ao imprimir/salvar PDF */
        @media print {
            .no-print {
                display: none !important;
            }
        }

        .bar-acoes {
            background: #0F172A;
            padding: 12px;
            text-align: center;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .btn-imprimir {
            background-color: #C5A059;
            color: #ffffff;
            border: none;
            padding: 8px 20px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>

    <!-- Barra Superior com Ação de Impressão/Download -->
    <div class="bar-acoes no-print">
        <button class="btn-imprimir" onclick="window.print()">🖨️ Imprimir / Salvar em PDF</button>
    </div>

    <!-- Cabeçalho Oficial -->
    <div class="header-print">
        <h1>Apostila de Músicas</h1>
        <h3><?= htmlspecialchars($nome_edicao) ?></h3>
    </div>

    <!-- Conteúdo da Apostila -->
    <?php foreach ($musicas as $cat_chave => $subcategorias): ?>
        <?php if (!empty($subcategorias)): ?>
            <div class="categoria-titulo">
                <?= $titulos_categorias[$cat_chave] ?? $cat_chave ?>
            </div>

            <?php foreach ($subcategorias as $subcat => $lista_musicas): ?>
                <?php if ($subcat !== 'Geral'): ?>
                    <div class="subcategoria-titulo"><?= htmlspecialchars($subcat) ?></div>
                <?php endif; ?>

                <?php foreach ($lista_musicas as $m): ?>
                    <div class="musica-card">
                        <div class="musica-cabecalho">
                            <?php if (!empty($m['artista'])): ?>
                                <span class="musica-artista"><?= htmlspecialchars($m['artista']) ?></span>
                            <?php endif; ?>
                            <span class="musica-titulo"><?= htmlspecialchars($m['titulo']) ?></span>
                            <div style="clear: both;"></div>
                        </div>
                        <div class="musica-letra"><?= htmlspecialchars($m['letra']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endforeach; ?>

</body>
</html>
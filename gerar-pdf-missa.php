<?php
// gerar-pdf-missa.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    exit("Acesso negado.");
}

$missa_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($missa_id <= 0) {
    exit("Missa inválida.");
}

// Buscar dados apenas da missa solicitada
$stmt = $conn->prepare("SELECT * FROM missas WHERE id = ?");
$stmt->bind_param("i", $missa_id);
$stmt->execute();
$missa = $stmt->get_result()->fetch_assoc();

if (!$missa) {
    exit("Missa não encontrada.");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($missa['titulo']) ?> - Leituras e Preces</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 1.8cm 2cm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #000000;
            line-height: 1.6;
            background: #ffffff;
            margin: 0;
            padding: 0;
        }

        /* Página 1: Leituras | Página 2: Preces */
        .pagina {
            page-break-after: always;
            break-after: page;
        }
        .pagina:last-child {
            page-break-after: auto;
            break-after: avoid;
        }

        .cabecalho {
            text-align: center;
            border-bottom: 1.5pt solid #000000;
            padding-bottom: 10pt;
            margin-bottom: 20pt;
        }
        .cabecalho h1 {
            margin: 0 0 4pt 0;
            font-size: 18pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .cabecalho .subtitulo {
            font-size: 12pt;
            color: #333333;
        }

        .conteudo-titulo {
            font-size: 15pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 16pt;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .texto-corpo {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14pt;
            white-space: pre-wrap;
            text-align: justify;
            word-break: break-word;
        }

        @media print {
            .no-print {
                display: none !important;
            }
        }

        .btn-imprimir {
            position: fixed;
            top: 16px;
            right: 16px;
            background: #0A192F;
            color: #ffffff;
            border: 1.5px solid #C5A059;
            padding: 10px 18px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 13px;
            font-family: Arial, Helvetica, sans-serif;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <button class="btn-imprimir no-print" onclick="window.print()">
        Imprimir / Salvar PDF
    </button>

    <!-- PÁGINA 1: LEITURAS DO DIA -->
    <div class="pagina">
        <div class="cabecalho">
            <h1><?= htmlspecialchars($missa['titulo']) ?></h1>
            <div class="subtitulo"><?= htmlspecialchars($missa['dia_missa']) ?></div>
        </div>

        <div class="conteudo-titulo">Leituras da Missa</div>

        <div class="texto-corpo"><?= !empty($missa['leituras']) ? htmlspecialchars($missa['leituras']) : 'Nenhuma leitura cadastrada.' ?></div>
    </div>

    <!-- PÁGINA 2: PRECES DO DIA -->
    <div class="pagina">
        <div class="cabecalho">
            <h1><?= htmlspecialchars($missa['titulo']) ?></h1>
            <div class="subtitulo"><?= htmlspecialchars($missa['dia_missa']) ?></div>
        </div>

        <div class="conteudo-titulo">Preces dos Fiéis</div>

        <div class="texto-corpo"><?= !empty($missa['preces']) ? htmlspecialchars($missa['preces']) : 'Nenhuma prece cadastrada.' ?></div>
    </div>

    <script>
        // Só dispara automaticamente se a página for aberta diretamente fora do iframe
        window.onload = function() {
            if (window.self === window.top) {
                setTimeout(function() {
                    window.print();
                }, 400);
            }
        };
    </script>
</body>
</html>
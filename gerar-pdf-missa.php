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

// Buscar dados da missa
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
            size: A4;
            margin: 20mm;
        }
        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            color: #111;
            line-height: 1.6;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        
        /* Quebra de página estrita */
        .pagina {
            page-break-after: always;
            break-after: page;
            min-height: 90vh;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }
        .pagina:last-child {
            page-break-after: avoid;
            break-after: avoid;
        }

        .cabecalho {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        .cabecalho h1 {
            margin: 0 0 8px 0;
            font-size: 24pt;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .cabecalho .subtitulo {
            font-size: 12pt;
            color: #555;
            font-style: italic;
        }

        .conteudo-titulo {
            font-size: 16pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
            color: #222;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .texto-corpo {
            font-size: 13pt;
            white-space: pre-wrap;
            text-align: justify;
        }

        /* Oculta botões ao gerar PDF/Imprimir */
        @media print {
            .no-print {
                display: none !important;
            }
        }

        .btn-imprimir {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #000;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-family: sans-serif;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>

    <button class="btn-imprimir no-print" onclick="window.print()">
        🖨️ Guardar em PDF / Imprimir
    </button>

    <!-- PÁGINA 1: LEITURAS -->
    <div class="pagina">
        <div class="cabecalho">
            <h1><?= htmlspecialchars($missa['titulo']) ?></h1>
            <div class="subtitulo">
                <?= htmlspecialchars($missa['dia_missa']) ?> 
            </div>
        </div>

        <div class="conteudo-titulo">— LEITURAS DA MISSA —</div>
        
        <div class="texto-corpo">
            <?= !empty($missa['leituras']) ? htmlspecialchars($missa['leituras']) : '<i>Nenhuma leitura cadastrada.</i>' ?>
        </div>
    </div>

    <!-- PÁGINA 2: PRECES -->
    <div class="pagina">
        <div class="cabecalho">
            <h1><?= htmlspecialchars($missa['titulo']) ?></h1>
            <div class="subtitulo">
                <?= htmlspecialchars($missa['dia_missa']) ?> 
            </div>
        </div>

        <div class="conteudo-titulo">— PRECES DOS FIÉIS —</div>
        
        <div class="texto-corpo">
            <?= !empty($missa['preces']) ? htmlspecialchars($missa['preces']) : '<i>Nenhuma prece cadastrada.</i>' ?>
        </div>
    </div>

    <script>
        // Abre automaticamente a caixa de diálogo para salvar em PDF / Imprimir
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
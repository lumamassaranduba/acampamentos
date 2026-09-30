<?php
// pdf-funcoes-equipe.php
require_once 'config/database.php';

$edicao_id = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;

if ($edicao_id === 0) {
    die("Edição não especificada.");
}

// Busca Equipes e Atribuições
$stmt = $conn->prepare("SELECT * FROM equipes_trabalho WHERE edicao_id = ? ORDER BY nome ASC");
$stmt->bind_param("i", $edicao_id);
$stmt->execute();
$equipes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Funções das Equipes de Trabalho - ÉFATA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            font-family: Arial, Helvetica, sans-serif; 
            background: #fff; 
            color: #111;
            font-size: 15px;
            line-height: 1.6;
        }

        /* Quebra de página para cada equipe na impressão */
        .page-equipe {
            page-break-after: always;
            padding: 20px 10px;
        }
        
        .page-equipe:last-child {
            page-break-after: avoid;
        }

        /* Título no estilo da imagem */
        .titulo-equipe {
            font-size: 26px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }

        /* Conteúdo das Atribuições */
        .conteudo-atribuicoes {
            white-space: pre-line;
            text-align: justify;
        }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0 !important; }
            .page-equipe { padding: 0; margin-bottom: 0; }
        }
    </style>
</head>
<body class="p-4">

    <!-- Botão de Impressão -->
    <div class="no-print mb-4 d-flex justify-content-between align-items-center bg-light p-3 rounded border">
        <div>
            <h5 class="fw-bold m-0">Manual de Funções por Equipe</h5>
            <small class="text-muted">Cada equipe será impressa numa página individual.</small>
        </div>
        <button onclick="window.print()" class="btn btn-dark btn-lg fw-bold">
            🖨️ Imprimir / Salvar em PDF
        </button>
    </div>

    <?php foreach ($equipes as $eq): ?>
        <div class="page-equipe">
            
            <!-- Título no formato: Equipe: NOME DA EQUIPE -->
            <div class="titulo-equipe">
                <span>Equipe: <?= mb_strtoupper(htmlspecialchars($eq['nome']), 'UTF-8') ?></span>
                
            </div>

            <!-- Atribuições e Funções formatadas -->
            <div class="conteudo-atribuicoes">
                <?= !empty($eq['atribuicoes']) ? htmlspecialchars($eq['atribuicoes']) : 'Nenhuma função registrada para esta equipe.' ?>
            </div>

        </div>
    <?php endforeach; ?>

</body>
</html>
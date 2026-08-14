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
        body { font-family: sans-serif; background: #fff; color: #000; }
        .header-print { border-bottom: 2px solid #C5A059; padding-bottom: 10px; margin-bottom: 20px; }
        .box-equipe { border: 1px solid #ccc; border-radius: 8px; padding: 15px; margin-bottom: 20px; page-break-inside: avoid; }
        .coordenador { font-weight: bold; text-decoration: underline; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="p-4">

    <div class="no-print mb-4 d-flex justify-content-between align-items-center">
        <button onclick="window.print()" class="btn btn-primary btn-lg">
            🖨️ Imprimir ou Salvar como PDF
        </button>
        <span class="text-muted">Dica: Na janela de impressão, escolha "Salvar como PDF" para enviar o arquivo.</span>
    </div>

    <div class="header-print text-center">
        <h2 class="fw-bold m-0">ÉFATA - MANUAL DE FUNÇÕES E ATRIBUIÇÕES</h2>
        <p class="text-muted mb-0">Relatório de Responsabilidades das Equipes de Trabalho</p>
    </div>

    <?php foreach ($equipes as $eq): ?>
        <?php
        // Membros e Coordenadores
        $stmt_m = $conn->prepare("SELECT em.e_coordenador, eg.nome, eg.telefone 
                                 FROM equipe_membros em 
                                 JOIN equipe_geral eg ON em.voluntario_id = eg.id 
                                 WHERE em.equipe_id = ? 
                                 ORDER BY em.e_coordenador DESC, eg.nome ASC");
        $stmt_m->bind_param("i", $eq['id']);
        $stmt_m->execute();
        $membros = $stmt_m->get_result()->fetch_all(MYSQLI_ASSOC);
        ?>

        <div class="box-equipe">
            <h4 class="fw-bold border-bottom pb-2"><?= htmlspecialchars($eq['nome']) ?></h4>
            
            <div class="mb-3">
                <h6 class="fw-bold text-uppercase">Integrantes da Equipe:</h6>
                <p class="mb-0">
                    <?php 
                    $lista_membros = [];
                    foreach ($membros as $m) {
                        $nome_formatado = htmlspecialchars($m['nome']);
                        if ($m['e_coordenador']) {
                            $nome_formatado = "<strong>" . $nome_formatado . " (COORDENADOR)</strong>";
                        }
                        $lista_membros[] = $nome_formatado;
                    }
                    echo !empty($lista_membros) ? implode(' • ', $lista_membros) : 'Nenhum voluntário alocado.';
                    ?>
                </p>
            </div>

            <div>
                <h6 class="fw-bold text-uppercase">Funções e Atribuições do Setor:</h6>
                <div style="white-space: pre-line;" class="p-3 bg-light rounded border">
                    <?= !empty($eq['atribuicoes']) ? htmlspecialchars($eq['atribuicoes']) : 'Nenhuma função registrada para este setor.' ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

</body>
</html>
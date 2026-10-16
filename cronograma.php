<?php
// cronograma.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$edicao_id = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;

if ($edicao_id === 0) {
    die("Edição não especificada.");
}

// 1. Criar tabela de cronograma se não existir
$conn->query("
    CREATE TABLE IF NOT EXISTS `cronograma` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `edicao_id` INT DEFAULT 0,
        `dia_numero` VARCHAR(255) DEFAULT 'Sexta-feira (20 de Novembro de 2026)',
        `horario_inicio` VARCHAR(100) DEFAULT '',
        `duracao_minutos` VARCHAR(100) DEFAULT '',
        `roteiro_atividade` TEXT DEFAULT '',
        `equipes_responsaveis` TEXT DEFAULT '',
        `detalhes` TEXT DEFAULT '',
        `cor_destaque` VARCHAR(50) DEFAULT 'padrao',
        `ordem` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 2. PROCESSAMENTO DE AÇÕES VIA POST / AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao =$_POST['acao'];

    // Atualização em Tempo Real (Estilo Word)
    if ($acao === 'salvar_campo_ajax') {
        $id    = intval($_POST['id'] ?? 0);
        $campo =$_POST['campo'] ?? '';
        $valor = trim($_POST['valor'] ?? '');

        $campos_permitidos = ['horario_inicio', 'duracao_minutos', 'roteiro_atividade', 'equipes_responsaveis', 'cor_destaque', 'dia_numero'];
        if ($id > 0 && in_array($campo,$campos_permitidos)) {
            $stmt =$conn->prepare("UPDATE cronograma SET `{$campo}` = ? WHERE id = ? AND edicao_id = ?");
            $stmt->bind_param("sii", $valor,$id, $edicao_id);$stmt->execute();
            echo json_encode(['status' => 'sucesso']);
            exit;
        }
    }

    // Adicionar Nova Linha na Tabela
    if ($acao === 'adicionar_linha') {
        $stmt_max =$conn->prepare("SELECT MAX(ordem) as max_ordem FROM cronograma WHERE edicao_id = ?");
        $stmt_max->bind_param("i", $edicao_id);$stmt_max->execute();
        $res =$stmt_max->get_result()->fetch_assoc();
        $nova_ordem = ($res['max_ordem'] ?? 0) + 1;

        $stmt =$conn->prepare("INSERT INTO cronograma (edicao_id, horario_inicio, duracao_minutos, roteiro_atividade, equipes_responsaveis, ordem) VALUES (?, '00h00', '30\'', 'Nova atividade', 'Equipe', ?)");
        $stmt->bind_param("ii", $edicao_id, $nova_ordem);$stmt->execute();
        header("Location: cronograma.php?edicao_id={$edicao_id}");
        exit;
    }

    // Deletar Linha
    if ($acao === 'deletar_item') {
        $item_id = intval($_POST['item_id'] ?? 0);
        $stmt =$conn->prepare("DELETE FROM cronograma WHERE id = ? AND edicao_id = ?");
        $stmt->bind_param("ii", $item_id, $edicao_id);$stmt->execute();
        header("Location: cronograma.php?edicao_id={$edicao_id}");
        exit;
    }
}

// 3. BUSCA DOS ITENS DO CRONOGRAMA
$stmt =$conn->prepare("SELECT * FROM cronograma WHERE edicao_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $edicao_id);$stmt->execute();
$itens_cronograma =$stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$dia_titulo = !empty($itens_cronograma) ? $itens_cronograma[0]['dia_numero'] : 'Sexta-feira (20 de Novembro de 2026)';$caminho_logo = "./uploads/logos/logoefata.png";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cronograma Interativo - ÉFATA / CES</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        body { background-color: #525659; font-family: 'Calibri', 'Arial', sans-serif; color: #000; padding-bottom: 50px; }
        
        /* BARRA DE FERRAMENTAS ESTILO WORD */
        .word-toolbar {
            background: #2b579a;
            color: white;
            padding: 10px 20px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        /* FOLHA A4 ESTILO WORD / GOOGLE DOCS */
        .folha-word {
            background: #ffffff;
            width: 210mm;
            min-height: 297mm;
            margin: 25px auto;
            padding: 20mm 15mm;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.4);
            border-radius: 2px;
            position: relative;
        }

        /* TABELA IDÊNTICA AO WORD */
        .tabela-word {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000000;
        }

        .tabela-word th, .tabela-word td {
            border: 1px solid #000000;
            padding: 6px 8px;
            text-align: center;
            vertical-align: middle;
            font-size: 13px;
        }

        .tabela-word th {
            background-color: #f2f2f2;
            font-weight: bold;
            color: #000;
        }

        /* CÉLULA EDITÁVEL */
        [contenteditable="true"] {
            outline: none;
            transition: background 0.2s;
            border-radius: 2px;
            padding: 2px;
        }
        [contenteditable="true"]:hover {
            background-color: rgba(66, 133, 244, 0.15) !important;
            cursor: text;
        }
        [contenteditable="true"]:focus {
            background-color: #ffffff !important;
            box-shadow: 0 0 0 2px #2b579a;
            color: #000000 !important;
        }

        /* CORES DE HIGHLIGHT DA LINHA */
        .linha-padrao { background-color: #ffffff; }
        .linha-amarelo { background-color: #FFFF00 !important; font-weight: bold; }
        .linha-preto { background-color: #000000 !important; color: #ffffff !important; font-weight: bold; }
        .linha-preto [contenteditable="true"] { color: #ffffff; }
        .linha-preto [contenteditable="true"]:focus { color: #000000 !important; }
        .linha-roxo { background-color: #D8D0E6 !important; font-weight: bold; }

        .logo-cabecalho {
            max-width: 65px;
            max-height: 65px;
            object-fit: contain;
        }

        /* TICKET INDICADOR DE SALVAMENTO */
        #status-salvamento {
            font-size: 0.85rem;
            font-weight: 500;
            padding: 4px 12px;
            border-radius: 20px;
            background: rgba(255,255,255,0.2);
            transition: all 0.3s;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; padding: 0 !important; }
            .folha-word { box-shadow: none !important; margin: 0 !important; width: 100% !important; padding: 0 !important; }
            [contenteditable="true"] { border: none !important; }
        }
    </style>
</head>
<body>

    <!-- BARRA SUPERIOR - ESTILO WORD -->
    <div class="word-toolbar no-print d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <a href="equipe-hub.php?edicao_id=<?= $edicao_id ?>" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
            <span class="fw-bold fs-5"><i class="bi bi-file-earmark-word me-2"></i>Editor de Cronograma</span>
            <span id="status-salvamento" class="text-white-50"><i class="bi bi-check-circle me-1"></i> Alterações salvas</span>
        </div>

        <div class="d-flex align-items-center gap-2">
            <form method="POST" action="cronograma.php?edicao_id=<?= $edicao_id ?>" class="d-inline">
                <input type="hidden" name="acao" value="adicionar_linha">
                <button type="submit" class="btn btn-warning btn-sm fw-bold"><i class="bi bi-plus-lg me-1"></i> Inserir Linha</button>
            </form>
            
            <button onclick="window.print()" class="btn btn-light btn-sm fw-bold"><i class="bi bi-printer me-1"></i> Imprimir / PDF</button>
        </div>
    </div>

    <!-- FOLHA TIPO DOCUMENTO A4 -->
    <div class="folha-word">
        
        <!-- CABEÇALHO DO DOCUMENTO -->
        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-2 border-dark">
            <div>
                <h4 class="fw-bold m-0" style="letter-spacing: 1px;">2º ACAMPAMENTO CES</h4>
                <small class="text-muted fw-semibold">Paróquia Nossa Senhora Aparecida | Regente Feijó / SP</small>
                <h3 class="fw-bold m-0 mt-1" style="letter-spacing: 2px;">CRONOGRAMA</h3>
            </div>
            <div>
                <img src="<?= file_exists($caminho_logo) ?$caminho_logo : 'https://via.placeholder.com/65' ?>" alt="Logo" class="logo-cabecalho">
            </div>
        </div>

        <!-- FAIXA DO DIA (EDITÁVEL) -->
        <div class="bg-dark text-white fw-bold text-center py-2 mb-2 rounded-1 fs-6">
            <span contenteditable="true" onblur="salvarCampoDirect(<?= $itens_cronograma[0]['id'] ?? 0 ?>, 'dia_numero', this.innerText)"><?= htmlspecialchars($dia_titulo) ?></span>
        </div>

        <!-- TABELA DE HORÁRIOS -->
        <table class="tabela-word">
            <thead>
                <tr>
                    <th style="width: 15%;">Horário</th>
                    <th style="width: 10%;">Tempo</th>
                    <th style="width: 42%;">Roteiro</th>
                    <th style="width: 25%;">Equipe</th>
                    <th class="no-print" style="width: 8%;">Cor / Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($itens_cronograma)): ?>
                    <tr>
                        <td colspan="5" class="py-4 text-muted">Nenhum horário cadastrado. Clique no botão "Inserir Linha" acima.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($itens_cronograma as$item): ?>
                        <?php 
                            $cor =$item['cor_destaque'] ?? 'padrao';
                            $classe_cor = 'linha-' .$cor;
                        ?>
                        <tr id="linha-<?= $item['id'] ?>" class="<?= $classe_cor ?>">
                            <!-- Horário -->
                            <td>
                                <div contenteditable="true" onblur="salvarCampoDirect(<?= $item['id'] ?>, 'horario_inicio', this.innerText)">
                                    <?= htmlspecialchars($item['horario_inicio']) ?>
                                </div>
                            </td>

                            <!-- Tempo/Duração -->
                            <td>
                                <div contenteditable="true" onblur="salvarCampoDirect(<?= $item['id'] ?>, 'duracao_minutos', this.innerText)">
                                    <?= htmlspecialchars($item['duracao_minutos']) ?>
                                </div>
                            </td>

                            <!-- Roteiro/Atividade -->
                            <td class="text-start">
                                <div contenteditable="true" style="white-space: pre-wrap;" onblur="salvarCampoDirect(<?= $item['id'] ?>, 'roteiro_atividade', this.innerText)">
                                    <?= htmlspecialchars($item['roteiro_atividade']) ?>
                                </div>
                            </td>

                            <!-- Equipe Responsável -->
                            <td class="text-start">
                                <div contenteditable="true" style="white-space: pre-wrap;" onblur="salvarCampoDirect(<?= $item['id'] ?>, 'equipes_responsaveis', this.innerText)">
                                    <?= htmlspecialchars($item['equipes_responsaveis']) ?>
                                </div>
                            </td>

                            <!-- Seletor de Cor + Excluir -->
                            <td class="no-print text-center">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <select onchange="mudarCorLinha(<?= $item['id'] ?>, this.value)" class="form-select form-select-sm p-0 border-0" style="width: 28px; height: 28px; cursor: pointer;">
                                        <option value="padrao" <?= $cor === 'padrao' ? 'selected' : '' ?>>⚪ Branco</option>
                                        <option value="amarelo" <?= $cor === 'amarelo' ? 'selected' : '' ?>>🟡 Amarelo</option>
                                        <option value="preto" <?= $cor === 'preto' ? 'selected' : '' ?>>⚫ Preto</option>
                                        <option value="roxo" <?= $cor === 'roxo' ? 'selected' : '' ?>>🟣 Roxo</option>
                                    </select>

                                    <form action="cronograma.php?edicao_id=<?= $edicao_id ?>" method="POST" class="d-inline" onsubmit="return confirm('Excluir esta linha?');">
                                        <input type="hidden" name="acao" value="deletar_item">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn btn-sm text-danger p-0 ms-1" title="Excluir"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    </div>

    <script>
        // Função para salvar alterações do texto em tempo real (AJAX)
        function salvarCampoDirect(id, campo, valor) {
            if (id <= 0) return;
            
            const statusEl = document.getElementById('status-salvamento');
            statusEl.innerHTML = '<i class="bi bi-arrow-repeat spin me-1"></i> Salvando...';
            statusEl.classList.replace('text-white-50', 'text-warning');

            const formData = new FormData();
            formData.append('acao', 'salvar_campo_ajax');
            formData.append('id', id);
            formData.append('campo', campo);
            formData.append('valor', valor);

            fetch('cronograma.php?edicao_id=<?= $edicao_id %>', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'sucesso') {
                    statusEl.innerHTML = '<i class="bi bi-check-circle me-1"></i> Alterações salvas';
                    statusEl.classList.replace('text-warning', 'text-white-50');
                }
            })
            .catch(() => {
                statusEl.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> Erro ao salvar';
                statusEl.classList.replace('text-warning', 'text-danger');
            });
        }

        // Troca a cor de fundo da linha na hora
        function mudarCorLinha(id, cor) {
            const tr = document.getElementById('linha-' + id);
            tr.className = 'linha-' + cor;
            salvarCampoDirect(id, 'cor_destaque', cor);
        }
    </script>
</body>
</html>
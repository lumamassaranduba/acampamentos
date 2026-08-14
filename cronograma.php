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

// DEFINA O CAMINHO DA SUA LOGO AQUI:
$caminho_logo = "assets/img/logo.png"; // Alterar para o caminho correto da imagem da logo

$mensagem_sucesso = '';

// PROCESSAMENTO: Salvar novo item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];

    if ($acao === 'adicionar_item') {
        $dia_numero           = trim($_POST['dia_numero'] ?? 'Sexta-feira (10 de Julho de 2026)');
        $horario_inicio       = trim($_POST['horario_inicio'] ?? '');
        $duracao_minutos      = trim($_POST['duracao_minutos'] ?? '');
        $roteiro_atividade    = trim($_POST['roteiro_atividade'] ?? '');
        
        // Pega a equipe selecionada no select (ou se digitou algo manual no campo extra)
        $equipe_sel           = trim($_POST['equipe_select'] ?? '');
        $equipe_outro         = trim($_POST['equipe_manual'] ?? '');
        $equipes_responsaveis = !empty($equipe_sel) ? $equipe_sel : $equipe_outro;

        $detalhes             = trim($_POST['detalhes'] ?? '');
        $cor_destaque         = $_POST['cor_destaque'] ?? 'padrao';

        $stmt = $conn->prepare("INSERT INTO cronograma (edicao_id, dia_numero, horario_inicio, duracao_minutos, roteiro_atividade, equipes_responsaveis, detalhes, cor_destaque) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", $edicao_id, $dia_numero, $horario_inicio, $duracao_minutos, $roteiro_atividade, $equipes_responsaveis, $detalhes, $cor_destaque);
        $stmt->execute();
        $mensagem_sucesso = "Item adicionado com sucesso!";
    }

    if ($acao === 'deletar_item') {
        $item_id = intval($_POST['item_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM cronograma WHERE id = ? AND edicao_id = ?");
        $stmt->bind_param("ii", $item_id, $edicao_id);
        $stmt->execute();
        $mensagem_sucesso = "Item removido!";
    }
}

// BUSCA AS EQUIPES CADASTRADAS PARA O SELECT
$stmt_eq = $conn->prepare("SELECT nome FROM equipes_trabalho WHERE edicao_id = ? ORDER BY nome ASC");
$stmt_eq->bind_param("i", $edicao_id);
$stmt_eq->execute();
$equipes_cadastradas = $stmt_eq->get_result()->fetch_all(MYSQLI_ASSOC);

// BUSCA DOS ITENS DO CRONOGRAMA
$stmt = $conn->prepare("SELECT * FROM cronograma WHERE edicao_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $edicao_id);
$stmt->execute();
$itens_cronograma = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$dia_titulo = !empty($itens_cronograma) ? $itens_cronograma[0]['dia_numero'] : 'Sexta-feira (10 de Julho de 2026)';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cronograma - ÉFATA / CES</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        body { font-family: 'Arial', sans-serif; background-color: #f4f6f9; color: #000; }
        
        .folha-cronograma {
            background: #fff;
            max-width: 900px;
            margin: 20px auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .tabela-cronograma {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
        }

        .tabela-cronograma th, .tabela-cronograma td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
            vertical-align: middle;
            font-size: 13px;
        }

        .tabela-cronograma th {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        /* Cores idênticas ao Word */
        .linha-amarelo { background-color: #FFFF00 !important; font-weight: bold; }
        .linha-preto { background-color: #000000 !important; color: #ffffff !important; font-weight: bold; }
        .linha-roxo { background-color: #D8D0E6 !important; font-weight: bold; }

        .logo-cabecalho {
            max-width: 70px;
            max-height: 70px;
            object-fit: contain;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .folha-cronograma { box-shadow: none; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

    <!-- Menu Superior -->
    <div class="no-print bg-dark text-white p-3 mb-4 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <a href="equipe-hub.php?edicao_id=<?= $edicao_id ?>" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
            <h5 class="m-0 fw-bold">Cronograma do Acampamento</h5>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-warning btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalNovoHorario">
                <i class="bi bi-plus-circle me-1"></i> Adicionar Horário
            </button>
            <button onclick="window.print()" class="btn btn-light btn-sm fw-bold">
                <i class="bi bi-printer me-1"></i> Imprimir / Salvar PDF
            </button>
        </div>
    </div>

    <div class="container">
        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert alert-success alert-dismissible fade show no-print"><?= $mensagem_sucesso ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- FOLHA ESTILO WORD -->
        <div class="folha-cronograma">
            
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-3">
                    <!-- LOGO DA SUA APLICAÇÃO -->
                    <div>
                        <img src="<?= file_exists($caminho_logo) ? $caminho_logo : 'https://via.placeholder.com/70?text=LOGO' ?>" alt="Logo Acampamento" class="logo-cabecalho">
                    </div>
                    <div>
                        <h4 class="fw-bold m-0" style="letter-spacing: 1px;">2º ACAMPAMENTO CES</h4>
                        <small class="text-muted">Paróquia Nossa Senhora Aparecida | Regente Feijó / SP</small>
                        <h3 class="fw-bold m-0 mt-1" style="letter-spacing: 2px;">CRONOGRAMA</h3>
                    </div>
                </div>
            </div>

            <div class="bg-dark text-white fw-bold text-center py-2 mb-2 rounded-1">
                <?= htmlspecialchars($dia_titulo) ?>
            </div>

            <table class="tabela-cronograma">
                <thead>
                    <tr>
                        <th style="width: 15%;">Horário</th>
                        <th style="width: 10%;">Tempo</th>
                        <th style="width: 40%;">Roteiro</th>
                        <th style="width: 30%;">Equipe</th>
                        <th class="no-print" style="width: 5%;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($itens_cronograma)): ?>
                        <tr>
                            <td colspan="5" class="py-4 text-muted">Nenhum horário cadastrado no cronograma.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($itens_cronograma as $item): ?>
                            <?php 
                                $cor = $item['cor_destaque'] ?? 'padrao';
                                $classe_cor = '';
                                if ($cor === 'amarelo') $classe_cor = 'linha-amarelo';
                                if ($cor === 'preto') $classe_cor = 'linha-preto';
                                if ($cor === 'roxo') $classe_cor = 'linha-roxo';
                            ?>
                            <tr class="<?= $classe_cor ?>">
                                <td><?= htmlspecialchars($item['horario_inicio']) ?></td>
                                <td><?= htmlspecialchars($item['duracao_minutos']) ?></td>
                                <td class="text-start" style="white-space: pre-line;"><?= htmlspecialchars($item['roteiro_atividade']) ?></td>
                                <td class="text-start" style="white-space: pre-line;">
                                    <?= htmlspecialchars($item['equipes_responsaveis']) ?>
                                    <?php if(!empty($item['detalhes'])): ?>
                                        <br><small class="fst-italic">(<?= htmlspecialchars($item['detalhes']) ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td class="no-print">
                                    <form action="cronograma.php?edicao_id=<?= $edicao_id ?>" method="POST" onsubmit="return confirm('Remover item?');">
                                        <input type="hidden" name="acao" value="deletar_item">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Novo Horário -->
    <div class="modal fade no-print" id="modalNovoHorario" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold">Adicionar Linha ao Cronograma</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="cronograma.php?edicao_id=<?= $edicao_id ?>" method="POST">
                    <input type="hidden" name="acao" value="adicionar_item">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Dia / Cabeçalho</label>
                            <input type="text" name="dia_numero" class="form-control" value="<?= htmlspecialchars($dia_titulo) ?>" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold">Horário</label>
                                <input type="text" name="horario_inicio" class="form-control" placeholder="Ex: 7h00 - 7h30" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold">Tempo Dur.</label>
                                <input type="text" name="duracao_minutos" class="form-control" placeholder="Ex: 30' ou 1h10" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Roteiro / Atividade</label>
                            <textarea name="roteiro_atividade" class="form-control" rows="2" placeholder="Ex: Pregação: Amor de Deus..." required></textarea>
                        </div>
                        
                        <!-- SELEÇÃO DE EQUIPES CADASTRADAS -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Equipe Responsável</label>
                            <select name="equipe_select" class="form-select mb-2">
                                <option value="">-- Selecione uma Equipe Cadastrada --</option>
                                <option value="Todas as Equipes">Todas as Equipes</option>
                                <?php foreach ($equipes_cadastradas as $eq_item): ?>
                                    <option value="<?= htmlspecialchars($eq_item['nome']) ?>"><?= htmlspecialchars($eq_item['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted d-block mb-1">Ou digite manualmente caso precise combinar mais de uma equipe:</small>
                            <input type="text" name="equipe_manual" class="form-control" placeholder="Ex: Secretaria / Recreação / Externa">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Observações / Detalhes</label>
                            <input type="text" name="detalhes" class="form-control" placeholder="Ex: Entregar pirulito no embarque...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Cor de Destaque da Linha</label>
                            <select name="cor_destaque" class="form-select">
                                <option value="padrao">Padrão (Branco)</option>
                                <option value="amarelo">🟨 Amarelo (Pregações / Avisos)</option>
                                <option value="preto">⬛ Preto (Refeições / Almoço)</option>
                                <option value="roxo">🟪 Roxo (Vivências / Atividades)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning fw-bold">Adicionar Linha</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
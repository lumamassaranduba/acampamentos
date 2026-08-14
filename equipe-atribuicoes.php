<?php
// equipe-atribuicoes.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$edicao_id = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;

if ($edicao_id === 0) {
    die("Edição não especificada.");
}

$mensagem_sucesso = '';

// PROCESSAMENTO: Salvar / Editar Atribuições da Equipe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_atribuicoes') {
    $equipe_id = intval($_POST['equipe_id'] ?? 0);
    $atribuicoes = trim($_POST['atribuicoes'] ?? '');

    $stmt = $conn->prepare("UPDATE equipes_trabalho SET atribuicoes = ? WHERE id = ? AND edicao_id = ?");
    $stmt->bind_param("sii", $atribuicoes, $equipe_id, $edicao_id);
    $stmt->execute();
    $mensagem_sucesso = "Funções e atribuições atualizadas com sucesso!";
}

// BUSCA DE EQUIPES
$stmt = $conn->prepare("SELECT * FROM equipes_trabalho WHERE edicao_id = ? ORDER BY nome ASC");
$stmt->bind_param("i", $edicao_id);
$stmt->execute();
$equipes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atribuições das Equipes - ÉFATA</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --efata-dark: #0F172A; --efata-gold: #C5A059; --efata-bg: #F8FAFC; }
        body { font-family: 'Outfit', sans-serif; background-color: var(--efata-bg); }
        .topbar-efata { background-color: var(--efata-dark); border-bottom: 2px solid var(--efata-gold); color: #fff; }
        .btn-gold { background-color: var(--efata-gold); color: #fff; font-weight: 600; border: none; }
        .btn-gold:hover { background-color: #B38E48; color: #fff; }
        
        .card-equipe-atribuicao {
            background: #ffffff;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .card-equipe-atribuicao:hover {
            transform: translateY(-4px);
            border-color: var(--efata-gold);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>

    <header class="topbar-efata py-3 px-4 d-flex align-items-center justify-content-between sticky-top">
        <div class="d-flex align-items-center gap-3">
            <a href="equipe-hub.php?edicao_id=<?= $edicao_id ?>" class="btn btn-sm btn-outline-light rounded-circle">
                <i class="bi bi-arrow-left"></i>
            </a>
            <h5 class="fw-bold m-0 text-white">3. Atribuições e Funções das Equipes</h5>
        </div>
        <div>
            <!-- Botão de Exportar PDF Geral com todas as funções -->
            <a href="pdf-funcoes-equipe.php?edicao_id=<?= $edicao_id ?>" target="_blank" class="btn btn-outline-light btn-sm px-3 rounded-3">
                <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i> Exportar PDF Geral
            </a>
        </div>
    </header>

    <main class="container py-4">

        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= $mensagem_sucesso ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="row g-4">
            <?php if (empty($equipes)): ?>
                <div class="col-12 text-center py-5 text-muted">
                    <h5>Nenhuma equipe cadastrada para esta edição.</h5>
                    <p>Cadastre as equipes na **Parte 2 (Divisão das Equipes)** para depois registrar as funções aqui.</p>
                </div>
            <?php else: ?>
                <?php foreach ($equipes as $eq): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card-equipe-atribuicao p-4 h-100 d-flex flex-column justify-content-between" onclick="abrirModalFuncoes(<?= $eq['id'] ?>, '<?= htmlspecialchars(addslashes($eq['nome'])) ?>', '<?= htmlspecialchars(addslashes($eq['atribuicoes'] ?? '')) ?>')">
                            <div>
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                        <i class="bi bi-journal-text fs-4"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold text-dark m-0"><?= htmlspecialchars($eq['nome']) ?></h5>
                                        <small class="text-muted">Clique para ver ou editar as funções</small>
                                    </div>
                                </div>

                                <div class="bg-light p-3 rounded-3 border">
                                    <small class="fw-bold text-uppercase text-muted d-block mb-1" style="font-size: 0.75rem;">Resumo das Atribuições:</small>
                                    <p class="small text-secondary mb-0 text-truncate-3" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; white-space: pre-line;">
                                        <?= !empty($eq['atribuicoes']) ? htmlspecialchars($eq['atribuicoes']) : 'Nenhuma função/atribuição cadastrada ainda.' ?>
                                    </p>
                                </div>
                            </div>

                            <div class="pt-3 mt-3 border-top d-flex justify-content-between align-items-center">
                                <span class="badge bg-secondary">Cadastrar/Editar</span>
                                <i class="bi bi-arrow-right-circle-fill text-warning fs-5"></i>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal para Visualizar / Editar Funções da Equipe -->
    <div class="modal fade" id="modalFuncoes" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-journal-check text-warning me-2"></i>Funções da Equipe: <span id="modal_nome_equipe"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="equipe-atribuicoes.php?edicao_id=<?= $edicao_id ?>" method="POST">
                    <input type="hidden" name="acao" value="salvar_atribuicoes">
                    <input type="hidden" name="equipe_id" id="modal_equipe_id">
                    
                    <div class="modal-body p-4">
                        <label class="form-label fw-bold text-dark">Descreva todas as funções e responsabilidades desta equipe:</label>
                        <p class="text-muted small mb-2">Guarde aqui todas as orientações do que esta equipe precisa fazer (antes do evento, durante o acampamento, escala de trabalho, materiais, etc.).</p>
                        
                        <textarea name="atribuicoes" id="modal_texto_atribuicoes" class="form-control rounded-3 p-3" rows="12" placeholder="Digite ou cole aqui todas as atribuições da equipe..."></textarea>
                    </div>

                    <div class="modal-footer bg-light d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        <button type="submit" class="btn btn-gold px-4">Salvar Atribuições</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function abrirModalFuncoes(id, nome, textoAtribuicoes) {
            document.getElementById('modal_equipe_id').value = id;
            document.getElementById('modal_nome_equipe').innerText = nome;
            document.getElementById('modal_texto_atribuicoes').value = textoAtribuicoes;
            
            new bootstrap.Modal(document.getElementById('modalFuncoes')).show();
        }
    </script>
</body>
</html>
<?php
// pregacoes-dinamicas.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$edicao_id = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$mensagem = '';
$erro = '';

// --- PROCESSAMENTO DO FORMULÁRIO (SALVAR / EDITAR / EXCLUIR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'salvar') {
        $id              = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $tema            = trim($_POST['tema'] ?? '');
        $pregacao        = trim($_POST['pregacao'] ?? '');
        $orientacoes     = trim($_POST['orientacoes'] ?? '');
        $dinamica_titulo = trim($_POST['dinamica_titulo'] ?? '');
        $dinamica_desc   = trim($_POST['dinamica_desc'] ?? '');
        $ordem           = intval($_POST['ordem'] ?? 0);

        if ($id > 0) {
            // Atualizar existente
            $stmt = $conn->prepare("UPDATE pregacoes_dinamicas SET tema=?, pregacao=?, orientacoes=?, dinamica_titulo=?, dinamica_desc=?, ordem=? WHERE id=?");
            $stmt->bind_param("sssssii", $tema, $pregacao, $orientacoes, $dinamica_titulo, $dinamica_desc, $ordem, $id);
            if ($stmt->execute()) {
                $mensagem = "Registro atualizado com sucesso!";
            } else {
                $erro = "Erro ao atualizar: " . $conn->error;
            }
        } else {
            // Inserir novo
            $stmt = $conn->prepare("INSERT INTO pregacoes_dinamicas (edicao_id, ordem, tema, pregacao, orientacoes, dinamica_titulo, dinamica_desc) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssss", $edicao_id, $ordem, $tema, $pregacao, $orientacoes, $dinamica_titulo, $dinamica_desc);
            if ($stmt->execute()) {
                $mensagem = "Novo conteúdo cadastrado com sucesso!";
            } else {
                $erro = "Erro ao cadastrar: " . $conn->error;
            }
        }
    } elseif ($action === 'excluir') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM pregacoes_dinamicas WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $mensagem = "Registro excluído com sucesso!";
            } else {
                $erro = "Erro ao excluir: " . $conn->error;
            }
        }
    }
}

// --- CONSULTA DOS REGISTROS ---
$conteudos = [];
if ($edicao_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM pregacoes_dinamicas WHERE edicao_id = ? OR edicao_id IS NULL OR edicao_id = 0 ORDER BY ordem ASC, id ASC");
    $stmt->bind_param("i", $edicao_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $conteudos = $result->fetch_all(MYSQLI_ASSOC);
    }
} else {
    $result = $conn->query("SELECT * FROM pregacoes_dinamicas ORDER BY ordem ASC, id ASC");
    if ($result) {
        $conteudos = $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pregações e Dinâmicas - ÉFATA</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --efata-dark: #0F172A;
            --efata-gold: #C5A059;
            --efata-bg: #F8FAFC;
            --efata-slate: #334155;
            --efata-border: #E2E8F0;
        }

        body { 
            font-family: 'Outfit', sans-serif; 
            background-color: var(--efata-bg); 
            color: var(--efata-dark); 
        }

        .topbar-efata {
            background-color: var(--efata-dark);
            border-bottom: 2px solid var(--efata-gold);
            color: #ffffff;
        }

        .card-item {
            background: #ffffff;
            border: 1px solid var(--efata-border);
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            transition: all 0.2s ease;
        }

        .card-item:hover {
            border-color: var(--efata-gold);
            box-shadow: 0 6px 16px rgba(197, 160, 89, 0.12);
        }

        .box-dinamica {
            background-color: #FFFDF5;
            border: 1px solid #F3E8C4;
            border-left: 4px solid var(--efata-gold);
            border-radius: 12px;
            padding: 16px;
        }

        .box-orientacoes {
            background-color: #F1F5F9;
            border-left: 4px solid var(--efata-slate);
            border-radius: 12px;
            padding: 16px;
        }

        .texto-formatado {
            white-space: pre-line;
            line-height: 1.6;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .card-item { border: 1px solid #ccc; box-shadow: none; page-break-inside: avoid; }
        }
    </style>
</head>
<body>

    <header class="topbar-efata py-3 px-4 d-flex align-items-center justify-content-between sticky-top shadow-sm no-print">
        <div class="d-flex align-items-center gap-3">
            <a href="<?= $edicao_id > 0 ? "edicao-detalhes.php?id=$edicao_id" : "dashboard-geral.php" ?>" class="btn btn-sm btn-outline-light rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h5 class="fw-bold m-0 text-white"><i class="bi bi-book me-2"></i>Pregações, Dinâmicas e Momentos</h5>
                <small class="text-warning">Manual do Encontro / Acampamento</small>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-success fw-bold px-3 py-2 rounded-3" onclick="abrirModalCadastrar()">
                <i class="bi bi-plus-lg me-1"></i> Nova Pregação / Dinâmica
            </button>
            <button onclick="window.print()" class="btn btn-sm btn-warning fw-bold px-3 py-2 rounded-3">
                <i class="bi bi-printer-fill me-1"></i> Imprimir / PDF
            </button>
        </div>
    </header>

    <main class="container py-4" style="max-width: 900px;">

        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($mensagem) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($erro)): ?>
            <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($erro) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($conteudos)): ?>
            <div class="text-center py-5 bg-white rounded-4 border p-4 my-4 shadow-sm">
                <i class="bi bi-journal-plus fs-1 text-warning d-block mb-2"></i>
                <h5 class="fw-bold text-dark mb-1">Nenhuma pregação ou dinâmica cadastrada.</h5>
                <p class="text-muted small mb-3">Comece criando o primeiro momento do evento.</p>
                <button type="button" class="btn btn-warning fw-bold px-4 py-2 rounded-3" onclick="abrirModalCadastrar()">
                    <i class="bi bi-plus-lg me-1"></i> Cadastrar Agora
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($conteudos as $item): ?>
                <?php 
                    $hasPregacao = !empty($item['pregacao']);
                    $hasDinamica = !empty($item['dinamica_titulo']) || !empty($item['dinamica_desc']);
                    $hasOrientacoes = !empty($item['orientacoes']);
                ?>
                
                <div class="card-item p-4 mb-4 position-relative">
                    
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom pb-3 mb-3">
                        <h4 class="fw-bold m-0 text-dark">
                            <?php if (!empty($item['tema'])): ?>
                                <span class="text-muted fw-normal fs-6 d-block">Tema:</span>
                                <?= htmlspecialchars($item['tema']) ?>
                            <?php else: ?>
                                <span class="text-warning fw-bold"><i class="bi bi-lightning-charge-fill me-1"></i>Dinâmica Especial</span>
                            <?php endif; ?>
                        </h4>

                        <div class="d-flex align-items-center gap-2">
                            <?php if ($hasPregacao): ?>
                                <span class="badge bg-primary px-3 py-2 rounded-pill"><i class="bi bi-megaphone-fill me-1"></i> Pregação</span>
                            <?php endif; ?>
                            
                            <?php if ($hasDinamica): ?>
                                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill"><i class="bi bi-puzzle-fill me-1"></i> Dinâmica</span>
                            <?php endif; ?>

                            <!-- Botões de Ação (Apenas na tela) -->
                            <div class="no-print ms-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle" title="Editar" onclick='editarItem(<?= json_encode($item) ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este registro?');">
                                    <input type="hidden" name="action" value="excluir">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="Excluir">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <?php if ($hasPregacao): ?>
                        <div class="mb-4">
                            <h6 class="fw-bold text-uppercase text-secondary fs-7 mb-2"><i class="bi bi-file-text me-1"></i> Pregação / Conteúdo:</h6>
                            <div class="texto-formatado text-justify text-dark">
                                <?= htmlspecialchars($item['pregacao']) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($hasOrientacoes): ?>
                        <div class="box-orientacoes mb-4">
                            <h6 class="fw-bold text-dark fs-7 mb-2"><i class="bi bi-info-circle-fill me-1"></i> Momento da Oração (Orientações):</h6>
                            <div class="texto-formatado text-dark small">
                                <?= htmlspecialchars($item['orientacoes']) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($hasDinamica): ?>
                        <div class="box-dinamica">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <h5 class="fw-bold m-0 text-dark">
                                    Dinâmica: <?= htmlspecialchars($item['dinamica_titulo'] ?? 'Atividade') ?>
                                </h5>
                            </div>
                            <div class="texto-formatado text-justify text-dark small">
                                <?= htmlspecialchars($item['dinamica_desc']) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </main>

    <!-- MODAL DE CADASTRO / EDIÇÃO -->
    <div class="modal fade" id="modalConteudo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content rounded-4 border-0">
                <form method="POST">
                    <input type="hidden" name="action" value="salvar">
                    <input type="hidden" name="id" id="form_id" value="0">

                    <div class="modal-header bg-dark text-white border-bottom border-warning">
                        <h5 class="modal-header-title fw-bold m-0" id="modalTitulo">Nova Pregação / Dinâmica</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-9">
                                <label class="form-label fw-bold">Tema da Pregação</label>
                                <input type="text" name="tema" id="form_tema" class="form-control" placeholder="Ex: O Amor de Deus">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Ordem</label>
                                <input type="number" name="ordem" id="form_ordem" class="form-control" value="1">
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold">Conteúdo da Pregação</label>
                                <textarea name="pregacao" id="form_pregacao" class="form-control" rows="4" placeholder="Escreva aqui o conteúdo central da pregação..."></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold">Orientações do Momento / Oração</label>
                                <textarea name="orientacoes" id="form_orientacoes" class="form-control" rows="2" placeholder="Dicas de músicas, gestos, símbolos..."></textarea>
                            </div>

                            <hr class="my-3">

                            <div class="col-12">
                                <label class="form-label fw-bold text-warning-emphasis">Título da Dinâmica</label>
                                <input type="text" name="dinamica_titulo" id="form_dinamica_titulo" class="form-control" placeholder="Ex: Dinâmica do Pirulito">
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold text-warning-emphasis">Descrição da Dinâmica</label>
                                <textarea name="dinamica_desc" id="form_dinamica_desc" class="form-control" rows="3" placeholder="Como executar a dinâmica, materiais necessários..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning fw-bold px-4">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const modalElement = new bootstrap.Modal(document.getElementById('modalConteudo'));

        function abrirModalCadastrar() {
            document.getElementById('form_id').value = '0';
            document.getElementById('form_tema').value = '';
            document.getElementById('form_ordem').value = '1';
            document.getElementById('form_pregacao').value = '';
            document.getElementById('form_orientacoes').value = '';
            document.getElementById('form_dinamica_titulo').value = '';
            document.getElementById('form_dinamica_desc').value = '';
            document.getElementById('modalTitulo').innerText = 'Nova Pregação / Dinâmica';
            modalElement.show();
        }

        function editarItem(item) {
            document.getElementById('form_id').value = item.id;
            document.getElementById('form_tema').value = item.tema || '';
            document.getElementById('form_ordem').value = item.ordem || '1';
            document.getElementById('form_pregacao').value = item.pregacao || '';
            document.getElementById('form_orientacoes').value = item.orientacoes || '';
            document.getElementById('form_dinamica_titulo').value = item.dinamica_titulo || '';
            document.getElementById('form_dinamica_desc').value = item.dinamica_desc || '';
            document.getElementById('modalTitulo').innerText = 'Editar Pregação / Dinâmica';
            modalElement.show();
        }
    </script>
</body>
</html>
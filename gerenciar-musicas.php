<?php
// gerenciar-musicas.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Controle de Acesso
$perfis_permitidos = ['COORDENADORA_GERAL', 'COORDENADOR', 'DIRETOR'];
$perfil_usuario = $_SESSION['usuario_perfil'] ?? '';

if (!in_array($perfil_usuario, $perfis_permitidos)) {
    header("Location: dashboard-geral.php");
    exit();
}

// 2. Captura a edição vinculada na URL
$edicao_id = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;

if ($edicao_id === 0) {
    die("Edição não especificada. Acesse esta página a partir de um acampamento ativo.");
}

// Mensagens de feedback
$mensagem_sucesso = '';
$mensagem_erro = '';

// 3. Processamento do Formulário (Cadastrar / Editar / Excluir)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // --- CADASTRAR OU EDITAR ---
    if ($acao === 'salvar') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $titulo = trim($_POST['titulo'] ?? '');
        $artista = trim($_POST['artista'] ?? '');
        $categoria = $_POST['categoria'] ?? 'TEMA';
        $subcategoria = trim($_POST['subcategoria'] ?? '');
        $letra = trim($_POST['letra'] ?? '');

        if (!empty($titulo) && !empty($letra)) {
            if ($id > 0) {
                // Atualizar Música existente estritamente nesta edição
                $stmt = $conn->prepare("UPDATE apostilas_musicas SET titulo = ?, artista = ?, categoria = ?, subcategoria = ?, letra = ? WHERE id = ? AND edicao_id = ?");
                $stmt->bind_param("sssssii", $titulo, $artista, $categoria, $subcategoria, $letra, $id, $edicao_id);
                if ($stmt->execute()) {
                    $mensagem_sucesso = "Música atualizada com sucesso!";
                } else {
                    $mensagem_erro = "Erro ao atualizar música.";
                }
            } else {
                // Inserir Nova Música vinculada estritamente a esta edição
                $stmt = $conn->prepare("INSERT INTO apostilas_musicas (edicao_id, titulo, artista, categoria, subcategoria, letra) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssss", $edicao_id, $titulo, $artista, $categoria, $subcategoria, $letra);
                if ($stmt->execute()) {
                    $mensagem_sucesso = "Música adicionada com sucesso!";
                } else {
                    $mensagem_erro = "Erro ao cadastrar música.";
                }
            }
        } else {
            $mensagem_erro = "Por favor, preencha pelo menos o Título e a Letra da música.";
        }
    }

    // --- EXCLUIR MÚSICA ---
    if ($acao === 'excluir') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM apostilas_musicas WHERE id = ? AND edicao_id = ?");
            $stmt->bind_param("ii", $id, $edicao_id);
            if ($stmt->execute()) {
                $mensagem_sucesso = "Música removida com sucesso!";
            } else {
                $mensagem_erro = "Erro ao remover música.";
            }
        }
    }
}

// 4. Busca Músicas Cadastradas ESTRITAMENTE para esta edição
$stmt = $conn->prepare("SELECT * FROM apostilas_musicas WHERE edicao_id = ? ORDER BY categoria, subcategoria, titulo ASC");
$stmt->bind_param("i", $edicao_id);
$stmt->execute();
$todas_musicas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Busca dados amigáveis da edição para o cabeçalho
$nome_completo_edicao = "Edição #" . $edicao_id;
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
            $nome_completo_edicao = trim("$mod - $ed");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Músicas - ÉFATA</title>
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

        .text-gold { color: var(--efata-gold); }
        .text-dark-blue { color: var(--efata-dark); }

        .btn-gold {
            background-color: var(--efata-gold);
            color: #ffffff;
            font-weight: 600;
            border: none;
        }
        .btn-gold:hover {
            background-color: #B38E48;
            color: #ffffff;
        }

        .btn-outline-gold {
            border: 1px solid var(--efata-gold);
            color: #ffffff;
        }
        .btn-outline-gold:hover {
            background-color: var(--efata-gold);
            color: #ffffff;
        }

        .card-custom {
            background: #ffffff;
            border: 1px solid var(--efata-border);
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
        }

        .form-control, .form-select {
            border: 1px solid var(--efata-border);
            border-radius: 8px;
            padding: 10px 14px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--efata-gold);
            box-shadow: 0 0 0 0.25rem rgba(197, 160, 89, 0.2);
        }

        .table-custom th {
            background-color: var(--efata-dark);
            color: #ffffff;
            font-weight: 600;
        }

        .modal-header {
            background-color: var(--efata-dark);
            color: #ffffff;
            border-bottom: 2px solid var(--efata-gold);
        }
    </style>
</head>
<body>

    <!-- Header Topbar ÉFATA -->
    <header class="topbar-efata py-3 px-4 d-flex align-items-center justify-content-between sticky-top shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <a href="apostila-musica.php?edicao_id=<?= $edicao_id ?>" class="btn btn-sm btn-outline-gold rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i class="bi bi-arrow-left fs-6"></i>
            </a>
            <div>
                <h5 class="fw-bold m-0 text-white">Gerenciar Músicas</h5>
                <small class="text-gold"><?= htmlspecialchars($nome_completo_edicao) ?></small>
            </div>
        </div>

        <button type="button" class="btn btn-gold btn-sm px-3 py-2 rounded-3 d-flex align-items-center gap-2" onclick="abrirModalCadastro()">
            <i class="bi bi-plus-lg fs-6"></i> Nova Música
        </button>
    </header>

    <main class="container py-4">

        <!-- Mensagens de Feedback -->
        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($mensagem_sucesso) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensagem_erro)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($mensagem_erro) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Tabela Ocupando Toda a Largura -->
        <div class="card-custom p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold text-dark-blue m-0">
                    <i class="bi bi-music-note-list text-gold me-2"></i>Músicas Cadastradas Nesta Edição (<?= count($todas_musicas) ?>)
                </h5>
            </div>

            <?php if (empty($todas_musicas)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-music-note-beamed fs-1 d-block mb-3 text-gold"></i>
                    <h5>Nenhuma música cadastrada para esta edição.</h5>
                    <p class="small text-muted mb-3">Clique no botão abaixo para adicionar a primeira música.</p>
                    <button type="button" class="btn btn-gold px-4 py-2 rounded-3" onclick="abrirModalCadastro()">
                        <i class="bi bi-plus-lg me-1"></i> Adicionar Primeira Música
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle table-custom rounded-3 overflow-hidden">
                        <thead>
                            <tr>
                                <th>Título / Artista</th>
                                <th>Categoria</th>
                                <th>Subcategoria</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todas_musicas as $m): ?>
                                <tr>
                                    <td>
                                        <strong class="text-dark-blue fs-6"><?= htmlspecialchars($m['titulo']) ?></strong>
                                        <?php if (!empty($m['artista'])): ?>
                                            <br><small class="text-gold fw-semibold"><?= htmlspecialchars($m['artista']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-dark text-gold px-2 py-1"><?= htmlspecialchars($m['categoria']) ?></span>
                                    </td>
                                    <td>
                                        <span class="text-secondary"><?= htmlspecialchars($m['subcategoria'] ?: '-') ?></span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary me-1 px-3" onclick='editarMusica(<?= json_encode($m) ?>)'>
                                            <i class="bi bi-pencil-fill me-1"></i> Editar
                                        </button>
                                        <form action="gerenciar-musicas.php?edicao_id=<?= $edicao_id ?>" method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir a música \'<?= htmlspecialchars($m['titulo']) ?>\'?');">
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger px-2">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Modal de Formulario (Cadastrar / Editar) -->
    <div class="modal fade" id="modalMusica" tabindex="-1" aria-labelledby="modalMusicaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content rounded-4 overflow-hidden border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalMusicaLabel">
                        <i class="bi bi-plus-circle text-gold me-2"></i>Nova Música
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="gerenciar-musicas.php?edicao_id=<?= $edicao_id ?>" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="acao" value="salvar">
                        <input type="hidden" name="id" id="musica_id" value="0">

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">Título da Música *</label>
                                <input type="text" name="titulo" id="titulo" class="form-control" placeholder="Ex: Há Um Barco Esquecido na Praia" required>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">Artista / Cantor</label>
                                <input type="text" name="artista" id="artista" class="form-control" placeholder="Ex: Pe. Zezinho">
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">Categoria *</label>
                                <select name="categoria" id="categoria" class="form-select" required>
                                    <option value="TEMA">🔥 Tema</option>
                                    <option value="MISSA">⛪ Missa</option>
                                    <option value="MOMENTOS">🕊️ Pregações e Momentos</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">Subcategoria</label>
                                <input type="text" name="subcategoria" id="subcategoria" class="form-control" placeholder="Ex: Ofertório, Entrada, Comunhão...">
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Letra da Música *</label>
                                <textarea name="letra" id="letra" rows="8" class="form-control" placeholder="Cole a letra da música aqui..." required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light px-4 py-3">
                        <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-gold rounded-3 px-4">
                            <i class="bi bi-floppy-fill me-1"></i> Salvar Música
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let modalElement = document.getElementById('modalMusica');
        let bsModal = new bootstrap.Modal(modalElement);

        function abrirModalCadastro() {
            document.getElementById('modalMusicaLabel').innerHTML = '<i class="bi bi-plus-circle text-gold me-2"></i>Nova Música';
            document.getElementById('musica_id').value = 0;
            document.getElementById('titulo').value = '';
            document.getElementById('artista').value = '';
            document.getElementById('categoria').value = 'TEMA';
            document.getElementById('subcategoria').value = '';
            document.getElementById('letra').value = '';

            bsModal.show();
        }

        function editarMusica(m) {
            document.getElementById('modalMusicaLabel').innerHTML = '<i class="bi bi-pencil-square text-gold me-2"></i>Editar Música';
            document.getElementById('musica_id').value = m.id;
            document.getElementById('titulo').value = m.titulo;
            document.getElementById('artista').value = m.artista;
            document.getElementById('categoria').value = m.categoria;
            document.getElementById('subcategoria').value = m.subcategoria;
            document.getElementById('letra').value = m.letra;

            bsModal.show();
        }
    </script>
</body>
</html>
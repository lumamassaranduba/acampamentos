<?php
// gerenciar-musicas.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Controle de Acesso
$perfis_permitidos = ['COORDENADORA_GERAL', 'COORDENADOR', 'DIRETOR'];
$perfil_usuario = strtoupper(trim($_SESSION['usuario_perfil'] ?? $_SESSION['perfil'] ?? ''));

if (!in_array($perfil_usuario, $perfis_permitidos)) {
    header("Location: dashboard-geral.php");
    exit();
}

$nome_usuario = $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? 'Coordenador';

// 2. Captura a edição vinculada na URL
$edicao_id     = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$modalidade_id = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;

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
        $id           = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $titulo       = trim($_POST['titulo'] ?? '');
        $artista      = trim($_POST['artista'] ?? '');
        $categoria    = $_POST['categoria'] ?? 'TEMA';
        $subcategoria = trim($_POST['subcategoria'] ?? '');
        $letra        = trim($_POST['letra'] ?? '');

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
$stmt_info = $conn->prepare("SELECT e.nome AS edicao_nome, m.nome AS modalidade_nome, e.modalidade_id 
                            FROM edicoes e 
                            LEFT JOIN modalidades m ON e.modalidade_id = m.id 
                            WHERE e.id = ?");
if ($stmt_info) {
    $stmt_info->bind_param("i", $edicao_id);
    if ($stmt_info->execute()) {
        $res = $stmt_info->get_result()->fetch_assoc();
        if ($res) {
            $mod = $res['modalidade_nome'] ?? '';
            $ed  = $res['edicao_nome'] ?? '';
            if ($modalidade_id === 0 && !empty($res['modalidade_id'])) {
                $modalidade_id = intval($res['modalidade_id']);
            }
            $nome_completo_edicao = trim("$mod - $ed", " -");
        }
    }
}

$url_voltar = "apostila-musica.php?edicao_id={$edicao_id}" . ($modalidade_id > 0 ? "&modalidade_id={$modalidade_id}" : "");
$url_action = "gerenciar-musicas.php?edicao_id={$edicao_id}" . ($modalidade_id > 0 ? "&modalidade_id={$modalidade_id}" : "");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Gerenciar Músicas - ÉFATA</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --navy-dark: #0A192F;
            --navy-hover: #172A46;
            --gold-primary: #C5A059;
            --gold-hover: #B28D46;
            --bg-main: #F8FAFC;
            --slate-text: #334155;
            --border-color: #E2E8F0;
        }

        * { box-sizing: border-box; }

        html, body {
            max-width: 100%;
            overflow-x: hidden;
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-main);
            color: var(--navy-dark);
            margin: 0;
            padding: 0;
        }

        /* NAVBAR PADRONIZADA */
        .top-navbar {
            background-color: var(--navy-dark);
            border-bottom: 2px solid var(--gold-primary);
            padding: 0.75rem 1rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(10, 25, 47, 0.15);
        }

        .user-brand-block { min-width: 0; }

        .brand-icon {
            width: 40px;
            height: 40px;
            min-width: 40px;
            background-color: #ffffff;
            border: 2px solid var(--gold-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 2px;
        }

        .brand-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }

        .user-info-text {
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .btn-outline-danger-custom {
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #f87171;
            background: transparent;
            transition: all 0.2s ease;
        }
        .btn-outline-danger-custom:hover {
            background-color: #ef4444;
            color: #ffffff;
        }

        .text-gold { color: var(--gold-primary); }
        .text-dark-blue { color: var(--navy-dark); }

        .btn-gold {
            background-color: var(--gold-primary);
            color: #ffffff;
            font-weight: 600;
            border: 1.5px solid var(--gold-primary);
            transition: all 0.2s ease;
        }
        .btn-gold:hover {
            background-color: var(--gold-hover);
            border-color: var(--gold-hover);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(197, 160, 89, 0.3);
        }

        .btn-only-arrow {
            color: #475569;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background-color: #F1F5F9;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }
        .btn-only-arrow:hover {
            color: var(--navy-dark);
            background-color: #E2E8F0;
        }

        .card-custom {
            background: #ffffff;
            border: 1.5px solid var(--border-color);
            border-radius: 20px;
            box-shadow: 0 4px 16px rgba(10, 25, 47, 0.04);
        }

        .form-control, .form-select {
            border: 1.5px solid var(--border-color);
            border-radius: 10px;
            padding: 10px 14px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--gold-primary);
            box-shadow: 0 0 0 0.25rem rgba(197, 160, 89, 0.2);
        }

        /* TABELA DESKTOP */
        .table-custom th {
            background-color: var(--navy-dark);
            color: #ffffff;
            font-weight: 600;
            font-size: 0.88rem;
            border: none;
            padding: 12px 14px;
        }
        .table-custom th:first-child { border-top-left-radius: 12px; }
        .table-custom th:last-child { border-top-right-radius: 12px; }

        .table-custom td {
            padding: 12px 14px;
            vertical-align: middle;
        }

        .badge-cat-navy {
            background-color: var(--navy-dark);
            color: var(--gold-primary);
            font-weight: 700;
            font-size: 0.74rem;
            padding: 5px 10px;
            border-radius: 8px;
            letter-spacing: 0.3px;
        }

        .badge-subcat-pill {
            background-color: #F8FAFC;
            color: #475569;
            border: 1px solid #CBD5E1;
            font-weight: 600;
            font-size: 0.76rem;
            padding: 4px 10px;
            border-radius: 50px;
        }

        /* CARDS RESPONSIVOS PARA CELULAR E TABLET */
        .musica-mobile-card {
            background: #ffffff;
            border: 1.5px solid var(--border-color);
            border-left: 4px solid var(--gold-primary);
            border-radius: 16px;
            padding: 14px 16px;
            transition: all 0.2s ease;
        }
        .musica-mobile-card:hover {
            border-color: var(--gold-primary);
            box-shadow: 0 6px 16px rgba(10, 25, 47, 0.06);
        }

        .search-box-custom {
            background: #F8FAFC;
            border: 1.5px solid var(--border-color);
            border-radius: 50px;
            padding: 4px 14px;
        }
        .search-box-custom:focus-within {
            background: #ffffff;
            border-color: var(--gold-primary);
        }
        .search-box-custom input:focus { box-shadow: none !important; }

        .modal-header-custom {
            background-color: var(--navy-dark);
            color: #ffffff;
            border-bottom: 2px solid var(--gold-primary);
        }

        @media (max-width: 767.98px) {
            .btn-nova-musica-mobile {
                width: 100%;
                justify-content: center;
                padding-top: 10px;
                padding-bottom: 10px;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR PADRONIZADA -->
    <nav class="top-navbar d-flex align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2 gap-sm-3 user-brand-block">
            <div class="brand-icon shadow-sm">
                <img src="./uploads/logos/logoefata.png" alt="Logo Éfata" onerror="this.onerror=null; this.src='assets/img/logo.png';">
            </div>
            <div class="user-info-text">
                <h6 class="fw-bold m-0 text-white text-truncate" style="font-size: 0.92rem;"><?= htmlspecialchars($nome_usuario) ?></h6>
                <small class="text-light opacity-75 d-block text-truncate" style="font-size: 0.72rem;"><?= htmlspecialchars($perfil_usuario) ?></small>
            </div>
        </div>

        <button class="btn btn-outline-danger-custom btn-sm rounded-3 px-2 px-sm-3 py-2 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalConfirmarLogout" title="Sair">
            <i class="bi bi-box-arrow-right"></i>
            <span class="d-none d-md-inline">Sair</span>
        </button>
    </nav>

    <!-- CABEÇALHO DA PÁGINA -->
    <div class="bg-white border-bottom py-3">
        <div class="container d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3" style="max-width: 1140px;">
            <div class="d-flex align-items-center gap-3">
                <a href="<?= htmlspecialchars($url_voltar) ?>" class="btn-only-arrow" title="Voltar para Apostila">
                    <i class="bi bi-arrow-left fs-5"></i>
                </a>
                <div>
                    <h5 class="fw-bold m-0 text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-sliders text-gold"></i>Gerenciar Músicas
                    </h5>
                    <small class="text-muted fw-medium"><?= htmlspecialchars($nome_completo_edicao) ?></small>
                </div>
            </div>

            <button type="button" class="btn btn-gold btn-nova-musica-mobile rounded-pill px-4 py-2 btn-sm d-flex align-items-center gap-2" onclick="abrirModalCadastro()">
                <i class="bi bi-plus-lg fs-6"></i>
                <span>Nova Música</span>
            </button>
        </div>
    </div>

    <main class="container py-4" style="max-width: 1140px;">

        <!-- Mensagens de Feedback -->
        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($mensagem_sucesso) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensagem_erro)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($mensagem_erro) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Card Principal -->
        <div class="card-custom p-3 p-md-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-3 mb-4">
                <h6 class="fw-bold text-dark-blue m-0 d-flex align-items-center gap-2">
                    <i class="bi bi-music-note-list text-gold fs-5"></i>
                    <span>Músicas Cadastradas Nesta Edição (<?= count($todas_musicas) ?>)</span>
                </h6>

                <?php if (!empty($todas_musicas)): ?>
                    <div class="search-box-custom d-flex align-items-center" style="min-width: 240px;">
                        <i class="bi bi-search text-muted me-2"></i>
                        <input type="text" id="filtroGerenciar" class="form-control form-control-sm border-0 bg-transparent p-1" placeholder="Nome da música ou artista..." onkeyup="filtrarMusicasGerenciar()">
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($todas_musicas)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-music-note-beamed fs-1 d-block mb-3 text-gold"></i>
                    <h5>Nenhuma música cadastrada para esta edição.</h5>
                    <p class="small text-muted mb-3">Clique no botão abaixo para adicionar a primeira música.</p>
                    <button type="button" class="btn btn-gold px-4 py-2 rounded-pill" onclick="abrirModalCadastro()">
                        <i class="bi bi-plus-lg me-1"></i> Adicionar Primeira Música
                    </button>
                </div>
            <?php else: ?>

                <!-- 1. VISUALIZAÇÃO DESKTOP (TABELA LIMPA) -->
                <div class="d-none d-lg-block">
                    <table class="table table-hover align-middle table-custom mb-0">
                        <thead>
                            <tr>
                                <th style="width: 35%;">Título / Artista</th>
                                <th style="width: 15%;">Categoria</th>
                                <th style="width: 25%;">Subcategoria</th>
                                <th class="text-end" style="width: 25%;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todas_musicas as $m): ?>
                                <?php $json_m = htmlspecialchars(json_encode($m), ENT_QUOTES, 'UTF-8'); ?>
                                <tr class="item-musica-row" data-busca="<?= htmlspecialchars(mb_strtolower(($m['titulo'] ?? '') . ' ' . ($m['artista'] ?? '') . ' ' . ($m['categoria'] ?? '') . ' ' . ($m['subcategoria'] ?? ''), 'UTF-8')) ?>">
                                    <td>
                                        <strong class="text-dark-blue fs-6"><?= htmlspecialchars($m['titulo']) ?></strong>
                                        <?php if (!empty($m['artista'])): ?>
                                            <br><small class="text-gold fw-semibold"><?= htmlspecialchars($m['artista']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-cat-navy"><?= htmlspecialchars($m['categoria']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge-subcat-pill"><?= htmlspecialchars($m['subcategoria'] ?: '-') ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex align-items-center gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold" onclick='editarMusica(<?= $json_m ?>)'>
                                                <i class="bi bi-pencil-fill me-1"></i> Editar
                                            </button>
                                            <form action="<?= htmlspecialchars($url_action) ?>" method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir a música \'<?= htmlspecialchars($m['titulo'], ENT_QUOTES) ?>\'?');">
                                                <input type="hidden" name="acao" value="excluir">
                                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold">
                                                    <i class="bi bi-trash-fill me-1"></i> Excluir
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 2. VISUALIZAÇÃO CELULAR E TABLET (CARDS SEM ROLAGEM LATERAL) -->
                <div class="row g-3 d-lg-none">
                    <?php foreach ($todas_musicas as $m): ?>
                        <?php $json_m = htmlspecialchars(json_encode($m), ENT_QUOTES, 'UTF-8'); ?>
                        <div class="col-12 col-md-6 item-musica-card" data-busca="<?= htmlspecialchars(mb_strtolower(($m['titulo'] ?? '') . ' ' . ($m['artista'] ?? '') . ' ' . ($m['categoria'] ?? '') . ' ' . ($m['subcategoria'] ?? ''), 'UTF-8')) ?>">
                            <div class="musica-mobile-card h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="mb-2">
                                        <h6 class="fw-bold text-dark-blue m-0" style="word-break: break-word;"><?= htmlspecialchars($m['titulo']) ?></h6>
                                        <?php if (!empty($m['artista'])): ?>
                                            <small class="text-gold fw-semibold d-block mt-1"><?= htmlspecialchars($m['artista']) ?></small>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                                        <span class="badge-cat-navy"><?= htmlspecialchars($m['categoria']) ?></span>
                                        <span class="badge-subcat-pill"><?= htmlspecialchars($m['subcategoria'] ?: 'Geral') ?></span>
                                    </div>
                                </div>

                                <div class="d-grid gap-2 pt-2 border-top" style="grid-template-columns: 1fr 1fr;">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-2 fw-semibold d-flex align-items-center justify-content-center gap-1" onclick='editarMusica(<?= $json_m ?>)'>
                                        <i class="bi bi-pencil-fill"></i> Editar
                                    </button>

                                    <form action="<?= htmlspecialchars($url_action) ?>" method="POST" class="d-flex" onsubmit="return confirm('Tem certeza que deseja excluir a música \'<?= htmlspecialchars($m['titulo'], ENT_QUOTES) ?>\'?');">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill py-2 fw-semibold w-100 d-flex align-items-center justify-content-center gap-1">
                                            <i class="bi bi-trash-fill"></i> Excluir
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>
        </div>

    </main>

    <!-- Modal de Formulário (Cadastrar / Editar) -->
    <div class="modal fade" id="modalMusica" tabindex="-1" aria-labelledby="modalMusicaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content rounded-4 overflow-hidden border-0 shadow">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title fw-bold text-white" id="modalMusicaLabel">
                        <i class="bi bi-plus-circle text-gold me-2"></i>Nova Música
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="<?= htmlspecialchars($url_action) ?>" method="POST">
                    <div class="modal-body p-3 p-md-4">
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
                    <div class="modal-footer bg-light px-3 px-md-4 py-3 justify-content-between">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-gold rounded-pill px-4">
                            <i class="bi bi-floppy-fill me-1"></i> Salvar Música
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL CONFIRMAR LOGOUT -->
    <div class="modal fade" id="modalConfirmarLogout" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg text-center">
                <div class="modal-header modal-header-custom p-3 justify-content-center">
                    <h5 class="modal-title fw-bold fs-6 m-0 text-white d-flex align-items-center gap-2">
                        <i class="bi bi-box-arrow-right text-warning fs-5"></i> Confirmar Saída
                    </h5>
                </div>
                <div class="modal-body p-4">
                    <p class="text-secondary mb-0">Deseja realmente encerrar a sua sessão e sair do sistema?</p>
                </div>
                <div class="modal-footer bg-light p-3 border-top justify-content-center gap-2">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-3 btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <a href="logout.php" class="btn btn-danger rounded-3 px-3 btn-sm fw-semibold">Sair do Sistema</a>
                </div>
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
            document.getElementById('titulo').value = m.titulo || '';
            document.getElementById('artista').value = m.artista || '';
            document.getElementById('categoria').value = m.categoria || 'TEMA';
            document.getElementById('subcategoria').value = m.subcategoria || '';
            document.getElementById('letra').value = m.letra || '';

            bsModal.show();
        }

        function filtrarMusicasGerenciar() {
            const termo = document.getElementById('filtroGerenciar').value.toLowerCase().trim();
            document.querySelectorAll('.item-musica-row, .item-musica-card').forEach(el => {
                const texto = el.getAttribute('data-busca') || '';
                el.style.display = (termo === '' || texto.includes(termo)) ? '' : 'none';
            });
        }
    </script>
</body>
</html>
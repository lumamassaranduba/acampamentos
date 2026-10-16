<?php
// recreacao.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nome_usuario = $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? 'Valéria Guimarães';
$perfil       = $_SESSION['usuario_perfil'] ?? $_SESSION['perfil'] ?? 'COORDENADORA_GERAL';

$edicao_id_param = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$modalidade_id_param = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;

$mensagem_sucesso = '';
$mensagem_erro = '';

// 1. Criar tabela unificada para Recreação
$conn->query("
    CREATE TABLE IF NOT EXISTS `recreacao` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `edicao_id` INT DEFAULT 0,
        `tipo` ENUM('brincadeira', 'teatro', 'olimpiada') NOT NULL,
        `nome` VARCHAR(255) DEFAULT NULL,
        `explicacao` TEXT DEFAULT NULL,
        `momento` VARCHAR(255) DEFAULT NULL,
        `texto_teatro` LONGTEXT DEFAULT NULL,
        `tema` VARCHAR(255) DEFAULT NULL,
        `personagens` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 2. Processar Ações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];

    if ($acao === 'salvar_brincadeira') {
        $id = intval($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $explicacao = trim($_POST['explicacao'] ?? '');

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE recreacao SET nome = ?, explicacao = ? WHERE id = ?");
            $stmt->bind_param("ssi", $nome, $explicacao, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO recreacao (edicao_id, tipo, nome, explicacao) VALUES (?, 'brincadeira', ?, ?)");
            $stmt->bind_param("iss", $edicao_id_param, $nome, $explicacao);
        }
        if ($stmt->execute()) $mensagem_sucesso = "Brincadeira salva com sucesso!";
    }

    if ($acao === 'salvar_teatro') {
        $id = intval($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $momento = trim($_POST['momento'] ?? '');
        $texto_teatro = trim($_POST['texto_teatro'] ?? '');

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE recreacao SET nome = ?, momento = ?, texto_teatro = ? WHERE id = ?");
            $stmt->bind_param("sssi", $nome, $momento, $texto_teatro, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO recreacao (edicao_id, tipo, nome, momento, texto_teatro) VALUES (?, 'teatro', ?, ?, ?)");
            $stmt->bind_param("isss", $edicao_id_param, $nome, $momento, $texto_teatro);
        }
        if ($stmt->execute()) $mensagem_sucesso = "Teatro salvo com sucesso!";
    }

    if ($acao === 'salvar_olimpiada') {
        $id = intval($_POST['id'] ?? 0);
        $tema = trim($_POST['tema'] ?? '');
        $personagens = trim($_POST['personagens'] ?? '');
        $explicacao = trim($_POST['explicacao'] ?? '');

        if ($id > 0) {
            $stmt =$conn->prepare("UPDATE recreacao SET tema = ?, personagens = ?, explicacao = ? WHERE id = ?");
            $stmt->bind_param("sssi", $tema,$personagens, $explicacao,$id);
        } else {
            $stmt =$conn->prepare("INSERT INTO recreacao (edicao_id, tipo, tema, personagens, explicacao) VALUES (?, 'olimpiada', ?, ?, ?)");
            $stmt->bind_param("isss", $edicao_id_param,$tema, $personagens,$explicacao);
        }
        if ($stmt->execute())$mensagem_sucesso = "Olimpíada salva com sucesso!";
    }

    if ($acao === 'excluir_item') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt =$conn->prepare("DELETE FROM recreacao WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute())$mensagem_sucesso = "Item removido com sucesso!";
        }
    }
}

// 3. Buscar Dados
$brincadeiras =$conn->query("SELECT * FROM recreacao WHERE tipo = 'brincadeira' " . ($edicao_id_param ? "AND edicao_id = $edicao_id_param " : "") . "ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
$teatros      =$conn->query("SELECT * FROM recreacao WHERE tipo = 'teatro' " . ($edicao_id_param ? "AND edicao_id = $edicao_id_param " : "") . "ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
$olimpiadas   =$conn->query("SELECT * FROM recreacao WHERE tipo = 'olimpiada' " . ($edicao_id_param ? "AND edicao_id = $edicao_id_param " : "") . "ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recreação - ÉFATA</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --efata-dark: #0F172A; --efata-gold: #C5A059; --efata-bg: #F8FAFC; }
        body { font-family: 'Outfit', sans-serif; background-color: var(--efata-bg); }
        .navbar-custom { background-color: var(--efata-dark); padding: 10px 20px; border-bottom: 2px solid var(--efata-gold); }
        .logo-box { width: 42px; height: 42px; background: #fff; border-radius: 8px; border: 1px solid var(--efata-gold); display: flex; align-items: center; justify-content: center; }
        .logo-box img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .user-name { color: #ffffff; font-weight: 700; font-size: 0.95rem; }
        .user-role { color: #94A3B8; font-size: 0.75rem; font-weight: 600; }
        
        /* Abas Estilizadas */
        .nav-pills .nav-link { color: #64748B; font-weight: 600; border-radius: 12px; padding: 12px 24px; border: 1px solid #E2E8F0; background: #fff; }
        .nav-pills .nav-link.active { background-color: var(--efata-dark); color: #fff; border-color: var(--efata-dark); }
        
        .card-custom { background: #fff; border-radius: 20px; border: 1px solid #E2E8F0; }
        .btn-gold { background-color: var(--efata-gold); color: #fff; font-weight: 600; border: none; }
        .btn-gold:hover { background-color: #B38E48; color: #fff; }

        .texto-conteudo {
            white-space: pre-line;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .teatro-preview {
            max-height: 90px;
            overflow: hidden;
            position: relative;
            color: #475569;
        }
        .teatro-preview::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 40px;
            background: linear-gradient(transparent, #F8FAFC);
        }

        @media print {
            .no-print, .btn, .navbar-custom, .nav-pills, .alert { display: none !important; }
            body { background: #fff !important; color: #000 !important; font-size: 11pt; }
            .container { max-width: 100% !important; width: 100% !important; padding: 0 !important; }
            .card-custom { border: none !important; box-shadow: none !important; padding: 0 !important; }
            .teatro-preview { max-height: none !important; }
            .teatro-preview::after { display: none !important; }
            .item-print { page-break-inside: avoid; border-bottom: 1px solid #000; padding-bottom: 15px; margin-bottom: 15px; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top no-print">
        <div class="container-fluid p-0">
            <div class="d-flex align-items-center gap-2">
                <div class="logo-box">
                    <img src="./uploads/logos/logoefata.png" alt="Logo" onerror="this.onerror=null; this.src='https://via.placeholder.com/40';">
                </div>
                <div>
                    <div class="user-name"><?= htmlspecialchars($nome_usuario) ?></div>
                    <div class="user-role"><?= htmlspecialchars($perfil) ?></div>
                </div>
            </div>
            <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-3"><i class="bi bi-box-arrow-right"></i> Sair</a>
        </div>
    </nav>

    <!-- CABEÇALHO -->
    <div class="bg-white border-bottom py-3 mb-4 no-print">
        <div class="container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <?php if ($edicao_id_param > 0): ?>
                    <a href="edicao-detalhes.php?edicao_id=<?= $edicao_id_param ?>&modalidade_id=<?= $modalidade_id_param ?>" class="btn btn-sm btn-outline-secondary rounded-circle">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                <?php endif; ?>
                <div>
                    <h5 class="fw-bold m-0 text-dark"><i class="bi bi-controller text-warning me-2"></i>Recreação</h5>
                    <small class="text-muted">Brincadeiras, Teatros e olímpiadas</small>
                </div>
            </div>
            <button onclick="window.print()" class="btn btn-outline-dark btn-sm rounded-3"><i class="bi bi-printer me-1"></i> Imprimir Tudo</button>
        </div>
    </div>

    <main class="container py-2" style="max-width: 1100px;">
        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert alert-success alert-dismissible fade show no-print"><?= htmlspecialchars($mensagem_sucesso) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- NAVEGAÇÃO POR ABAS -->
        <ul class="nav nav-pills mb-4 gap-2 no-print" id="pills-tab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active d-flex align-items-center gap-2" id="pills-teatros-tab" data-bs-toggle="pill" data-bs-target="#pills-teatros" type="button" role="tab">
                    <i class="bi bi-masks"></i> Teatros <span class="badge bg-secondary rounded-pill ms-1"><?= count($teatros) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link d-flex align-items-center gap-2" id="pills-brincadeiras-tab" data-bs-toggle="pill" data-bs-target="#pills-brincadeiras" type="button" role="tab">
                    <i class="bi bi-controller"></i> Brincadeiras <span class="badge bg-secondary rounded-pill ms-1"><?= count($brincadeiras) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link d-flex align-items-center gap-2" id="pills-olimpiadas-tab" data-bs-toggle="pill" data-bs-target="#pills-olimpiadas" type="button" role="tab">
                    <i class="bi bi-trophy-fill text-warning"></i> Olimpíadas <span class="badge bg-secondary rounded-pill ms-1"><?= count($olimpiadas) ?></span>
                </button>
            </li>
        </ul>

        <!-- CONTEÚDO DAS ABAS -->
        <div class="tab-content" id="pills-tabContent">

            <!-- TAB 1: TEATROS (FOCO NA LEITURA CONFORTÁVEL) -->
            <div class="tab-pane fade show active" id="pills-teatros" role="tabpanel">
                <div class="card-custom p-4 shadow-sm">
                    <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3 no-print">
                        <h5 class="fw-bold m-0"><i class="bi bi-masks me-2 text-primary"></i>Teatros</h5>
                        <button class="btn btn-gold rounded-3" onclick="abrirModalTeatro()"><i class="bi bi-plus-lg me-1"></i> Cadastrar Teatro</button>
                    </div>

                    <?php if (empty($teatros)): ?>
                        <div class="text-center py-5 text-muted">Nenhum teatro cadastrado.</div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($teatros as$t): ?>
                                <div class="col-12 col-md-6 item-print">
                                    <div class="p-4 bg-light rounded-4 border h-100 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="fw-bold text-dark m-0 texto-conteudo"><?= htmlspecialchars($t['nome']) ?></h5>
                                                <div class="no-print flex-shrink-0">
                                                    <button class="btn btn-sm text-primary p-0 me-2" onclick='editarTeatro(<?= json_encode($t) ?>)' title="Editar"><i class="bi bi-pencil fs-5"></i></button>
                                                    <button class="btn btn-sm text-danger p-0" onclick="excluirItem(<?= $t['id'] ?>)" title="Excluir"><i class="bi bi-trash fs-5"></i></button>
                                                </div>
                                            </div>
                                            
                                            <span class="badge bg-primary mb-3">Momento: <?= htmlspecialchars($t['momento']) ?></span>
                                            
                                            <!-- Pré-visualização com corte visual -->
                                            <div class="teatro-preview small texto-conteudo mb-3">
                                                <?= htmlspecialchars($t['texto_teatro']) ?>
                                            </div>
                                        </div>

                                        <div class="no-print pt-2">
                                            <button class="btn btn-outline-dark w-100 rounded-3 fw-bold btn-sm" onclick='visualizarTeatroCompleto(<?= json_encode($t) ?>)'>
                                                <i class="bi bi-book me-1"></i> Ler Roteiro Completo
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 2: BRINCADEIRAS -->
            <div class="tab-pane fade" id="pills-brincadeiras" role="tabpanel">
                <div class="card-custom p-4 shadow-sm">
                    <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3 no-print">
                        <h5 class="fw-bold m-0"><i class="bi bi-controller me-2 text-primary"></i>Lista de Brincadeiras</h5>
                        <button class="btn btn-gold rounded-3" onclick="abrirModalBrincadeira()"><i class="bi bi-plus-lg me-1"></i> Cadastrar Brincadeira</button>
                    </div>

                    <?php if (empty($brincadeiras)): ?>
                        <div class="text-center py-5 text-muted">Nenhuma brincadeira cadastrada.</div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($brincadeiras as$b): ?>
                                <div class="col-12 col-md-6 item-print">
                                    <div class="p-4 bg-light rounded-4 border h-100">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="fw-bold text-dark m-0 texto-conteudo"><?= htmlspecialchars($b['nome']) ?></h5>
                                            <div class="no-print flex-shrink-0">
                                                <button class="btn btn-sm text-primary p-0 me-2" onclick='editarBrincadeira(<?= json_encode($b) ?>)'><i class="bi bi-pencil fs-5"></i></button>
                                                <button class="btn btn-sm text-danger p-0" onclick="excluirItem(<?= $b['id'] ?>)"><i class="bi bi-trash fs-5"></i></button>
                                            </div>
                                        </div>
                                        <p class="small text-muted m-0 texto-conteudo"><?= htmlspecialchars($b['explicacao']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 3: OLIMPÍADAS -->
            <div class="tab-pane fade" id="pills-olimpiadas" role="tabpanel">
                <div class="card-custom p-4 shadow-sm">
                    <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3 no-print">
                        <h5 class="fw-bold m-0"><i class="bi bi-trophy-fill me-2 text-warning"></i>Olimpíadas Cadastradas</h5>
                        <button class="btn btn-gold rounded-3" onclick="abrirModalOlimpiada()"><i class="bi bi-plus-lg me-1"></i> Cadastrar Olimpíada</button>
                    </div>

                    <?php if (empty($olimpiadas)): ?>
                        <div class="text-center py-5 text-muted">Nenhuma olimpíada cadastrada.</div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($olimpiadas as$o): ?>
                                <div class="col-12 col-md-6 item-print">
                                    <div class="p-4 bg-light rounded-4 border h-100">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="fw-bold text-dark m-0 texto-conteudo">Tema: <?= htmlspecialchars($o['tema']) ?></h5>
                                            <div class="no-print flex-shrink-0">
                                                <button class="btn btn-sm text-primary p-0 me-2" onclick='editarOlimpiada(<?= json_encode($o) ?>)'><i class="bi bi-pencil fs-5"></i></button>
                                                <button class="btn btn-sm text-danger p-0" onclick="excluirItem(<?= $o['id'] ?>)"><i class="bi bi-trash fs-5"></i></button>
                                            </div>
                                        </div>
                                        <div class="small mb-3 texto-conteudo"><strong>Personagens:</strong><br><?= htmlspecialchars($o['personagens']) ?></div>
                                        <div class="small text-muted texto-conteudo"><strong>Brincadeiras:</strong><br><?= htmlspecialchars($o['explicacao']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <!-- MODAL PARA LEITURA CONFORTÁVEL DO TEATRO COMPLETO -->
    <div class="modal fade" id="modalVerTeatro" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-header bg-dark text-white p-4">
                    <div>
                        <span class="badge bg-warning text-dark mb-1" id="v_t_momento"></span>
                        <h4 class="modal-title fw-bold" id="v_t_nome"></h4>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 fs-6 leading-relaxed texto-conteudo bg-white" id="v_t_texto">
                </div>
                <div class="modal-footer bg-light no-print">
                    <button onclick="window.print()" class="btn btn-outline-dark rounded-3"><i class="bi bi-printer me-1"></i> Imprimir Este Teatro</button>
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL BRINCADEIRA -->
    <div class="modal fade no-print" id="modalBrincadeira" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content rounded-4 border-0">
                <input type="hidden" name="acao" value="salvar_brincadeira">
                <input type="hidden" name="id" id="b_id" value="0">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold">Cadastrar Brincadeira</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nome da Brincadeira *</label>
                        <input type="text" name="nome" id="b_nome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Explicação da Brincadeira *</label>
                        <textarea name="explicacao" id="b_explicacao" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gold">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL TEATRO (CADASTRO / EDIÇÃO) -->
    <div class="modal fade no-print" id="modalTeatro" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form method="POST" class="modal-content rounded-4 border-0">
                <input type="hidden" name="acao" value="salvar_teatro">
                <input type="hidden" name="id" id="t_id" value="0">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold">Cadastrar Teatro</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nome do Teatro *</label>
                        <input type="text" name="nome" id="t_nome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Em que momento é usado *</label>
                        <input type="text" name="momento" id="t_momento" class="form-control" placeholder="Ex: Sábado à Noite / Abertura" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Texto do Teatro *</label>
                        <textarea name="texto_teatro" id="t_texto_teatro" class="form-control" rows="8" required placeholder="Cole ou digite aqui o roteiro completo da peça..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gold">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL OLIMPÍADA -->
    <div class="modal fade no-print" id="modalOlimpiada" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content rounded-4 border-0">
                <input type="hidden" name="acao" value="salvar_olimpiada">
                <input type="hidden" name="id" id="o_id" value="0">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold">Cadastrar Olimpíada</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tema da Olimpíada *</label>
                        <input type="text" name="tema" id="o_tema" class="form-control" placeholder="Ex: Heróis da Fé" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Personagens *</label>
                        <textarea name="personagens" id="o_personagens" class="form-control" rows="3" placeholder="Ex: Davi&#10;Golias&#10;Moisés" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Brincadeiras da Olimpíada *</label>
                        <textarea name="explicacao" id="o_explicacao" class="form-control" rows="4" placeholder="Lista das provas/brincadeiras..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gold">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" id="formExcluir" class="no-print">
        <input type="hidden" name="acao" value="excluir_item">
        <input type="hidden" name="id" id="excluir_id">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function visualizarTeatroCompleto(t) {
            document.getElementById('v_t_nome').innerText = t.nome;
            document.getElementById('v_t_momento').innerText = "Momento: " + t.momento;
            document.getElementById('v_t_texto').innerText = t.texto_teatro;
            new bootstrap.Modal(document.getElementById('modalVerTeatro')).show();
        }

        function abrirModalBrincadeira() {
            document.getElementById('b_id').value = '0';
            document.getElementById('b_nome').value = '';
            document.getElementById('b_explicacao').value = '';
            new bootstrap.Modal(document.getElementById('modalBrincadeira')).show();
        }
        function editarBrincadeira(d) {
            document.getElementById('b_id').value = d.id;
            document.getElementById('b_nome').value = d.nome;
            document.getElementById('b_explicacao').value = d.explicacao;
            new bootstrap.Modal(document.getElementById('modalBrincadeira')).show();
        }

        function abrirModalTeatro() {
            document.getElementById('t_id').value = '0';
            document.getElementById('t_nome').value = '';
            document.getElementById('t_momento').value = '';
            document.getElementById('t_texto_teatro').value = '';
            new bootstrap.Modal(document.getElementById('modalTeatro')).show();
        }
        function editarTeatro(d) {
            document.getElementById('t_id').value = d.id;
            document.getElementById('t_nome').value = d.nome;
            document.getElementById('t_momento').value = d.momento;
            document.getElementById('t_texto_teatro').value = d.texto_teatro;
            new bootstrap.Modal(document.getElementById('modalTeatro')).show();
        }

        function abrirModalOlimpiada() {
            document.getElementById('o_id').value = '0';
            document.getElementById('o_tema').value = '';
            document.getElementById('o_personagens').value = '';
            document.getElementById('o_explicacao').value = '';
            new bootstrap.Modal(document.getElementById('modalOlimpiada')).show();
        }
        function editarOlimpiada(d) {
            document.getElementById('o_id').value = d.id;
            document.getElementById('o_tema').value = d.tema;
            document.getElementById('o_personagens').value = d.personagens;
            document.getElementById('o_explicacao').value = d.explicacao;
            new bootstrap.Modal(document.getElementById('modalOlimpiada')).show();
        }

        function excluirItem(id) {
            if (confirm('Tem certeza de que deseja remover este item?')) {
                document.getElementById('excluir_id').value = id;
                document.getElementById('formExcluir').submit();
            }
        }
    </script>
</body>
</html>
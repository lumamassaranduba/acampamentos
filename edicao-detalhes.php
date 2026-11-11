<?php
// edicao-detalhes.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';

// 1. Redireciona se não estiver logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// 2. Captura o ID da edição (aceita edicao_id ou id na URL)
$edicao_id = 0;
if (isset($_GET['edicao_id']) && intval($_GET['edicao_id']) > 0) {
    $edicao_id = intval($_GET['edicao_id']);
} elseif (isset($_GET['id']) && intval($_GET['id']) > 0) {
    $edicao_id = intval($_GET['id']);
}

if ($edicao_id <= 0) {
    header("Location: dashboard-geral.php?erro=edicao_invalida");
    exit;
}

// 3. Busca os dados da Edição e da Modalidade (incluindo logo e lema)
$stmt_ed = $conn->prepare("
    SELECT e.*, m.nome AS modalidade_nome, m.cor, m.logo, m.lema
    FROM edicoes e
    INNER JOIN modalidades m ON e.modalidade_id = m.id
    WHERE e.id = ?
");

if (!$stmt_ed) {
    die("Erro na preparação do SQL: " . $conn->error);
}

$stmt_ed->bind_param("i", $edicao_id);
$stmt_ed->execute();
$edicao = $stmt_ed->get_result()->fetch_assoc();

if (!$edicao) {
    header("Location: dashboard-geral.php?erro=edicao_nao_encontrada");
    exit;
}

// 4. Valida permissões do usuário
$perfil       = strtoupper(trim($_SESSION['usuario_perfil'] ?? ''));
$permitidos   = $_SESSION['modalidades_permitidas'] ?? [];
$nome_usuario = $_SESSION['usuario_nome'] ?? 'Usuário';

if ($perfil !== 'COORDENADORA_GERAL' && $perfil !== 'COORDENADOR_GERAL' && !in_array($edicao['modalidade_id'], $permitidos)) {
    header("Location: dashboard-geral.php?erro=sem_permissao");
    exit;
}

// Variáveis de exibição
$modalidade_id  = intval($edicao['modalidade_id']);
$nomeModalidade = htmlspecialchars($edicao['modalidade_nome']);
$nomeEdicao     = !empty($edicao['nome']) ? htmlspecialchars($edicao['nome']) : "Edição";
$anoEdicao      = intval($edicao['ano'] ?? date('Y'));
$statusEdicao   = strtolower($edicao['status'] ?? 'inativa');
$isAtiva        = ($statusEdicao === 'ativa');
$is_inativa     = !$isAtiva;
$cor            = !empty($edicao['cor']) ? $edicao['cor'] : '#C5A059';

// Trata a imagem da Logo do Acampamento
$nomeLimpo = mb_strtolower(trim($nomeModalidade), 'UTF-8');
$nomeLimpo = preg_replace('/[áàãâä]/u', 'a', $nomeLimpo);
$nomeLimpo = preg_replace('/[éèêë]/u', 'e', $nomeLimpo);
$nomeLimpo = preg_replace('/[íìîï]/u', 'i', $nomeLimpo);
$nomeLimpo = preg_replace('/[óòõôö]/u', 'o', $nomeLimpo);
$nomeLimpo = preg_replace('/[úùûü]/u', 'u', $nomeLimpo);

$extensoes = ['png', 'jpg', 'jpeg', 'webp'];
$caminhoLogo = null;

if (!empty($edicao['logo']) && file_exists($edicao['logo'])) {
    $caminhoLogo = $edicao['logo'];
} else {
    foreach ($extensoes as $e) {
        if (file_exists('uploads/logos/' . $nomeLimpo . '.' . $e)) {
            $caminhoLogo = 'uploads/logos/' . $nomeLimpo . '.' . $e;
            break;
        }
    }
}

// 5. BLOQUEIO DE SEGURANÇA NO BACKEND (PHP)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_inativa) {
    $_SESSION['mensagem_erro'] = "Esta edição está inativa e encontra-se em modo de leitura estrita. Alterações não são permitidas.";
    header("Location: edicao-detalhes.php?edicao_id={$edicao_id}&modalidade_id={$modalidade_id}");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $nomeModalidade ?> <?= $anoEdicao ?> - Painel da Edição</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --navy-dark: #0A192F;
            --navy-light: #1E293B;
            --gold-primary: #C5A059;
            --gold-hover: #B28D46;
            --bg-main: #F8FAFC;
            --border-color: #E2E8F0;
            --text-primary: #0F172A;
            --text-muted: #64748B;
        }

        body { 
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif; 
            background-color: var(--bg-main); 
            color: var(--text-primary); 
            min-height: 100vh;
        }

        /* Top Navbar Padronizada */
        .top-navbar {
            background-color: var(--navy-dark);
            border-bottom: 2px solid var(--gold-primary);
            padding: 0.75rem 1.25rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(10, 25, 47, 0.15);
        }

        .user-brand-block {
            min-width: 0;
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            min-width: 42px;
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

        /* Conteúdo Principal */
        .main-content { 
            padding: 2rem 1.25rem; 
            width: 100%; 
            max-width: 1300px; 
            margin: 0 auto; 
            box-sizing: border-box;
        }

        /* Card Cabeçalho da Edição */
        .card-header-edicao {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        }

        .logo-acampamento-circle {
            width: 54px;
            height: 54px;
            min-width: 54px;
            border-radius: 50%;
            background-color: #ffffff;
            border: 2px solid var(--gold-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 3px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .logo-acampamento-circle img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }

        /* Modulos Grid */
        .modulos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.25rem;
            margin-top: 1rem;
        }

        .card-modulo {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }

        .card-modulo:hover {
            border-color: var(--gold-primary);
            box-shadow: 0 8px 20px rgba(10, 25, 47, 0.08);
            transform: translateY(-3px);
            color: var(--navy-dark);
        }

        .card-modulo-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background-color: #F1F5F9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            color: var(--navy-dark);
            flex-shrink: 0;
            transition: all 0.25s ease;
        }

        .card-modulo:hover .card-modulo-icon {
            background-color: var(--navy-dark);
            color: var(--gold-primary);
        }

        .modal-header-custom {
            background-color: var(--navy-dark);
            color: #ffffff;
            border-bottom: 2px solid var(--gold-primary);
        }

        .banner-inativa {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #475569;
        }

        @media (max-width: 576px) {
            .top-navbar {
                padding: 0.65rem 0.85rem;
            }

            .main-content {
                padding: 1.25rem 0.85rem;
            }

            .modulos-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 0.85rem;
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
            <small class="text-light opacity-75 d-block text-truncate" style="font-size: 0.72rem;"><?= htmlspecialchars($perfil) ?></small>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 nav-actions">
        <button class="btn btn-outline-danger-custom btn-sm rounded-3 px-2 px-sm-3 py-2 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalConfirmarLogout" title="Sair">
            <i class="bi bi-box-arrow-right"></i>
            <span class="d-none d-md-inline">Sair</span>
        </button>
    </div>
</nav>

<main class="main-content">

    <!-- ALERTAS DE SESSÃO -->
    <?php if (isset($_SESSION['mensagem_erro'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($_SESSION['mensagem_erro']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem_erro']); ?>
    <?php endif; ?>

    <!-- BANNER DE EDIÇÃO INATIVA (MODO LEITURA ESTRITA) -->
    <?php if ($is_inativa): ?>
        <div class="alert banner-inativa d-flex align-items-center gap-3 rounded-4 shadow-sm mb-4 p-3">
            <i class="bi bi-lock-fill fs-3 text-secondary"></i>
            <div>
                <strong class="d-block text-dark">Modo Estrito de Leitura (Histórico)</strong>
                Esta edição foi marcada como <strong>Inativa</strong>. As informações estão visíveis apenas para consulta e qualquer tentativa de alteração será bloqueada.
            </div>
        </div>
    <?php endif; ?>

    <!-- CABEÇALHO DA EDIÇÃO COM LOGO E BOTÃO VOLTAR -->
    <div class="card-header-edicao mb-4 d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard-acampamento.php?modalidade_id=<?= $modalidade_id ?>" class="btn text-dark border-0 p-0 fs-4 d-flex align-items-center justify-content-center me-1" title="Voltar ao Acampamento">
                <i class="bi bi-arrow-left"></i>
            </a>

            <!-- LOGO DO ACAMPAMENTO -->
            <div class="logo-acampamento-circle">
                <?php if ($caminhoLogo): ?>
                    <img src="<?= $caminhoLogo ?>?v=<?= time() ?>" alt="Logo <?= $nomeModalidade ?>">
                <?php else: ?>
                    <i class="bi bi-shield-check fs-3 text-warning"></i>
                <?php endif; ?>
            </div>

            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h3 class="fw-bold m-0 text-dark"><?= $nomeModalidade ?> - <?= $nomeEdicao ?> (<?= $anoEdicao ?>)</h3>
                    <?php if ($isAtiva): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small">Ativa</span>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary border rounded-pill px-2.5 py-1 small">Inativa</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- GRID DE CARDS INTERNOS DA EDIÇÃO -->
    <div class="modulos-grid">
        
        <!-- 1. Equipe de Trabalho -->
        <a href="equipe-hub.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-people-fill"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Equipe de Trabalho</h6>
                <span class="text-muted small">Servos</span>
            </div>
        </a>

        <!-- 2. Equipes-Campistas -->
        <a href="equipes-campistas.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-person-badge-fill"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Equipes-Campistas</h6>
                <span class="text-muted small">Divisão dos campistas</span>
            </div>
        </a>

        <!-- 3. Histórico Geral -->
        <a href="historico-campistas.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-journal-bookmark-fill"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Histórico Geral</h6>
                <span class="text-muted small">Ficha e histórico de serviço</span>
            </div>
        </a>

        <!-- 4. Cronograma -->
        <a href="cronograma.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-calendar-week"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Cronograma</h6>
                <span class="text-muted small">Horários</span>
            </div>
        </a>

        <!-- 5. Espiritualidade -->
        <a href="espiritualidade.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-heart-pulse-fill"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Espiritualidades</h6>
                <span class="text-muted small">Preparação e vivência</span>
            </div>
        </a>

        <!-- 6. Apostila de Música -->
        <a href="apostila-musica.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-music-note-list"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Apostila de Música</h6>
                <span class="text-muted small">Letras</span>
            </div>
        </a>

        <!-- 7. Pregações -->
        <a href="pregacoes-dinamicas.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-book-half"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Pregações</h6>
                <span class="text-muted small">Dinâmicas e momentos</span>
            </div>
        </a>

        <!-- 8. Contas -->
        <a href="contas.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-cart-check"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Contas</h6>
                <span class="text-muted small">Lista de compras, fornecedores e gastos</span>
            </div>
        </a>

        <!-- 9. Missa -->
        <a href="missa.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-bell-fill"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Missa</h6>
                <span class="text-muted small">Liturgia</span>
            </div>
        </a>

        <!-- 10. Recreação -->
        <a href="recreacao.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-controller"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Recreação</h6>
                <span class="text-muted small">Brincadeiras - teatros - olímpiadas</span>
            </div>
        </a>

        <!-- 11. Apostila da Manutenção -->
        <a href="apostila-manutencao.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-tools"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Apostila da Manutenção</h6>
                <span class="text-muted small">Instruções dos desafios</span>
            </div>
        </a>

        <!-- 12. Pasta dos Desafios -->
        <a href="pasta-desafios.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-trophy-fill"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Pasta dos Desafios</h6>
                <span class="text-muted small">Desafios</span>
            </div>
        </a>

    </div>

</main>

<!-- MODAL LOGOUT -->
<div class="modal fade" id="modalConfirmarLogout" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg text-center">
            <div class="modal-header modal-header-custom p-3 justify-content-center">
                <h5 class="modal-title fw-bold fs-6 m-0 text-white d-flex align-items-center gap-2">
                    <i class="bi bi-box-arrow-right text-warning fs-5"></i> Confirmar Saída
                </h5>
            </div>
            <div class="modal-body p-4">
                <p class="text-secondary mb-0">Deseja realmente encerrar sua sessão e sair do sistema?</p>
            </div>
            <div class="modal-footer bg-light p-3 border-top justify-content-center gap-2">
                <button type="button" class="btn btn-outline-secondary rounded-3 px-3 btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <a href="logout.php" class="btn btn-danger rounded-3 px-3 btn-sm fw-semibold">Sair do Sistema</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- BLOQUEIO VIA JAVASCRIPT SE A EDIÇÃO FOR INATIVA -->
<?php if ($is_inativa): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const elementosControle = document.querySelectorAll('form input, form select, form textarea, form button, button[type="submit"]');
    elementosControle.forEach(el => {
        el.disabled = true;
    });

    const botoesAcao = document.querySelectorAll('.btn-salvar, .btn-editar, .btn-excluir, .btn-adicionar');
    botoesAcao.forEach(el => {
        el.style.display = 'none';
    });

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            alert('Esta edição está inativa. Operações de alteração não são permitidas.');
            return false;
        });
    });
});
</script>
<?php endif; ?>

</body>
</html>
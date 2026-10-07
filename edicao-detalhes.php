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

// Caminho para a imagem da sua Logo
$caminho_logo = 'assets/img/logo.png'; 

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

// 3. Busca os dados da Edição e da Modalidade
$stmt_ed = $conn->prepare("
    SELECT e.*, m.nome AS modalidade_nome, m.cor
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
$perfil          = $_SESSION['usuario_perfil'] ?? '';
$permitidos      = $_SESSION['modalidades_permitidas'] ?? [];
$nome_usuario    = $_SESSION['usuario_nome'] ?? 'Valéria Guimarães';

if ($perfil !== 'COORDENADORA_GERAL' && !in_array($edicao['modalidade_id'], $permitidos)) {
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
$cor            = !empty($edicao['cor']) ? $edicao['cor'] : '#292524';

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
            --cor-modalidade: <?= $cor ?>;
            --nav-bg: #0d1b2a;
            --nav-yellow: #e5b338;
            --nav-red: #e63946;
        }

        body { 
            font-family: 'Outfit', sans-serif; 
            background-color: #fcfbf9; 
            color: #1c1917; 
            margin: 0; 
            padding: 0; 
        }

        /* Estilização da Navbar */
        .navbar-custom {
            background-color: var(--nav-bg);
            padding: 0.85rem 2rem;
            color: #ffffff;
        }

        .user-profile-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Container da Logo */
        .logo-box {
            width: 48px;
            height: 48px;
            border: 1.5px solid var(--nav-yellow);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background-color: rgba(255, 255, 255, 0.05);
            flex-shrink: 0;
        }

        .logo-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            padding: 4px;
        }

        .user-info-text .user-name {
            font-weight: 700;
            font-size: 1.05rem;
            color: #ffffff;
            line-height: 1.2;
        }

        .user-info-text .user-role {
            font-size: 0.78rem;
            color: #94a3b8;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        /* Botão de Sair */
        .btn-nav-outline-red {
            background-color: transparent;
            color: #f87171;
            border: 1px solid var(--nav-red);
            font-weight: 600;
            border-radius: 10px;
            padding: 0.45rem 1.1rem;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-nav-outline-red:hover {
            background-color: rgba(230, 57, 70, 0.1);
            color: #f87171;
        }

        /* Conteúdo Principal */
        .main-content { 
            padding: 32px 48px; 
            width: 100%; 
            max-width: 1400px; 
            margin: 0 auto; 
            box-sizing: border-box;
        }

        .modulos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }

        .card-modulo {
            background: #ffffff;
            border: 1px solid #e7e5e4;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            text-decoration: none;
            color: #1c1917;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }

        .card-modulo:hover {
            border-color: var(--cor-modalidade);
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
            transform: translateY(-3px);
            color: var(--cor-modalidade);
        }

        .card-modulo-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background-color: #f5f5f4;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: #44403c;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .card-modulo:hover .card-modulo-icon {
            background-color: var(--cor-modalidade);
            color: #ffffff;
        }

        .banner-inativa {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #475569;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
            }
            .navbar-custom {
                padding: 0.85rem 1rem;
            }
            .modulos-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 12px;
            }
        }
    </style>
</head>
<body>

<!-- NAVBAR (SEM OS BOTÕES DE INSERIR E ACESSOS) -->
<nav class="navbar navbar-expand-lg navbar-custom sticky-top">
    <div class="container-fluid p-0">
        
        <!-- LADO ESQUERDO: LOGO DO PROJETO + PERFIL DO USUÁRIO -->
        <div class="user-profile-container">
            <div class="logo-box">
                <img src="./uploads/logos/logoefata.png" alt="Logo do Projeto" onerror="this.onerror=null; this.src='https://via.placeholder.com/48?text=LOGO';">
            </div>
            <div class="user-info-text">
                <div class="user-name"><?= htmlspecialchars($nome_usuario) ?></div>
                <div class="user-role"><?= htmlspecialchars($perfil) ?></div>
            </div>
        </div>

        <!-- LADO DIREITO: SOMENTE BOTÃO SAIR -->
        <div class="d-flex align-items-center gap-2 ms-auto">
            <a href="logout.php" class="btn-nav-outline-red">
                <i class="bi bi-box-arrow-right"></i> Sair
            </a>
        </div>

    </div>
</nav>

<main class="main-content">

    <!-- ALERTAS DE SESSÃO -->
    <?php if (isset($_SESSION['mensagem_erro'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
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

    <!-- CABEÇALHO DA EDIÇÃO -->
    <div class="bg-white p-4 rounded-4 border mb-4 shadow-sm d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3" style="border-color: #e7e5e4 !important;">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard-acampamento.php?modalidade_id=<?= $modalidade_id ?>" class="btn btn-light btn-sm rounded-3 d-flex align-items-center gap-2 border text-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>

            <div>
                <div class="d-flex align-items-center gap-2">
                    <h3 class="fw-bold m-0 text-dark"><?= $nomeModalidade ?> - <?= $nomeEdicao ?> (<?= $anoEdicao ?>)</h3>
                    <?php if ($isAtiva): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small">Ativa</span>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary border rounded-pill px-2.5 py-1 small">Inativa</span>
                    <?php endif; ?>
                </div>
                <span class="text-muted small">Gerencie todos os recursos e conteúdos desta edição</span>
            </div>
        </div>
    </div>

    <!-- TÍTULO DA SEÇÃO -->
    <h5 class="fw-bold text-dark mb-3">Módulos de Gestão</h5>

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
                <span class="text-muted small">Campistas e tribos/equipes</span>
            </div>
        </a>

        <!-- 3. Histórico Geral (NOVO CARD) -->
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
                <h6 class="fw-bold m-0">Espiritualidade</h6>
                <span class="text-muted small">Oração, capela e momentos</span>
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

        <!-- 8. Lista de Compras -->
        <a href="compras.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-cart-check"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Lista de Compras</h6>
                <span class="text-muted small">Mercado e materiais</span>
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
        <a href="manutencao.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-tools"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Apostila da Manutenção</h6>
                <span class="text-muted small">Instruções dos desafios</span>
            </div>
        </a>

        <!-- 12. Pasta dos Desafios -->
        <a href="desafios.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-trophy-fill"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Pasta dos Desafios</h6>
                <span class="text-muted small">Desafios- comunidades</span>
            </div>
        </a>

    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- BLOQUEIO VIA JAVASCRIPT SE A EDIÇÃO FOR INATIVA -->
<?php if ($is_inativa): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Desativa todos os campos de formulário e botões de ação/salvamento
    const elementosControle = document.querySelectorAll('form input, form select, form textarea, form button, button[type="submit"]');
    elementosControle.forEach(el => {
        el.disabled = true;
    });

    // 2. Oculta botões de adicionar, editar ou excluir dinâmicos
    const botoesAcao = document.querySelectorAll('.btn-salvar, .btn-editar, .btn-excluir, .btn-adicionar');
    botoesAcao.forEach(el => {
        el.style.display = 'none';
    });

    // 3. Intercepta envios de formulário via JS
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
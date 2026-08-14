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
$perfil     = $_SESSION['usuario_perfil'] ?? '';
$permitidos = $_SESSION['modalidades_permitidas'] ?? [];

if ($perfil !== 'COORDENADORA_GERAL' && !in_array($edicao['modalidade_id'], $permitidos)) {
    header("Location: dashboard-geral.php?erro=sem_permissao");
    exit;
}

// Variáveis de exibição
$nomeModalidade = htmlspecialchars($edicao['modalidade_nome']);
$nomeEdicao     = !empty($edicao['nome']) ? htmlspecialchars($edicao['nome']) : "Edição";
$anoEdicao      = intval($edicao['ano'] ?? date('Y'));
$statusEdicao   = strtolower($edicao['status'] ?? 'inativa');
$isAtiva        = ($statusEdicao === 'ativa');
$cor            = !empty($edicao['cor']) ? $edicao['cor'] : '#292524';
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
        body { font-family: 'Outfit', sans-serif; background-color: #fcfbf9; color: #1c1917; margin: 0; padding: 0; }
        
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
            border-color: <?= $cor ?>;
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
            transform: translateY(-3px);
            color: <?= $cor ?>;
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
            background-color: <?= $cor ?>;
            color: #ffffff;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
            }
            .modulos-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 12px;
            }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- CABEÇALHO DA EDIÇÃO -->
    <div class="bg-white p-4 rounded-4 border mb-4 shadow-sm d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3" style="border-color: #e7e5e4 !important;">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard-acampamento.php?modalidade_id=<?= $edicao['modalidade_id'] ?>" class="btn btn-light btn-sm rounded-3 d-flex align-items-center gap-2 border text-secondary">
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
        <a href="equipe-hub.php?edicao_id=<?= $edicao_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-people-fill"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Equipe de Trabalho</h6>
                <span class="text-muted small">Servos</span>
            </div>
        </a>

        <!-- 2. Cronograma -->
        <a href="cronograma.php?edicao_id=<?= $edicao_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-calendar-week"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Cronograma</h6>
                <span class="text-muted small">Horários</span>
            </div>
        </a>

       <!-- 3. Apostila de Música (CORRIGIDO) -->
        <a href="apostila-musica.php?edicao_id=<?= $edicao_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-music-note-list"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Apostila de Música</h6>
                <span class="text-muted small">Letras</span>
            </div>
        </a>

        <!-- 4. Pregações -->
        <a href="pregacoes.php?edicao_id=<?= $edicao_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-book-half"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Pregações</h6>
                <span class="text-muted small">Dinâmicas e momentos</span>
            </div>
        </a>

        <!-- 5. Lista de Compras -->
        <a href="compras.php?edicao_id=<?= $edicao_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-cart-check"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Lista de Compras</h6>
                <span class="text-muted small">Mercado e materiais</span>
            </div>
        </a>

        <!-- 6. Missa -->
        <a href="missa.php?edicao_id=<?= $edicao_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-bell-fill"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Missa</h6>
                <span class="text-muted small">Liturgia</span>
            </div>
        </a>

        <!-- 7. Recreação -->
        <a href="recreacao.php?edicao_id=<?= $edicao_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-controller"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Recreação</h6>
                <span class="text-muted small">Brincadeiras - teatros - olímpiadas</span>
            </div>
        </a>

        <!-- 8. Apostila da Manutenção -->
        <a href="manutencao.php?edicao_id=<?= $edicao_id ?>" class="card-modulo">
            <div class="card-modulo-icon">
                <i class="bi bi-tools"></i>
            </div>
            <div>
                <h6 class="fw-bold m-0">Apostila da Manutenção</h6>
                <span class="text-muted small">Instruções dos desafios</span>
            </div>
        </a>

        <!-- 9. Pasta dos Desafios -->
        <a href="desafios.php?edicao_id=<?= $edicao_id ?>" class="card-modulo">
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
</body>
</html>
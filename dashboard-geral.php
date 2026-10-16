<?php
// dashboard-geral.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$perfil     = strtoupper(trim($_SESSION['usuario_perfil'] ?? ''));
$permitidos = $_SESSION['modalidades_permitidas'] ?? [];

$perfis_gerais = ['COORDENADORA_GERAL', 'COORDENADOR_GERAL', 'GERAL', 'ADMIN'];
$eh_geral      = in_array($perfil, $perfis_gerais, true);

// MONTAGEM DA CONSULTA SQL DAS MODALIDADES (CARDS)
if ($eh_geral) {
    // Geral / Admin: Exibe todas as modalidades
    $sql = "SELECT * FROM modalidades WHERE id > 0 ORDER BY nome ASC";
    $stmt = $conn->prepare($sql);
} else {
    // Diretor / Coordenador: Exibe apenas acampamentos permitidos
    if (empty($permitidos)) {
        $modalidades = [];
    } else {
        $placeholders = implode(',', array_fill(0, count($permitidos), '?'));
        $types        = str_repeat('i', count($permitidos));
        
        $sql  = "SELECT * FROM modalidades WHERE id IN ($placeholders) ORDER BY nome ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$permitidos);
    }
}

if (isset($stmt)) {
    $stmt->execute();
    $result = $stmt->get_result();
    $modalidades = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Geral - Éfata</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --navy-dark: #0A192F;
            --navy-light: #1E293B;
            --gold-primary: #D4AF37;
            --gold-hover: #B59228;
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

        /* Navbar */
        .top-navbar {
            background-color: var(--navy-dark);
            border-bottom: 2px solid var(--gold-primary);
            padding: 0.9rem 2rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(10, 25, 47, 0.15);
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--gold-primary);
            border: 1px solid rgba(212, 175, 55, 0.4);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        .main-container {
            padding: 2.5rem 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Botões Navbar */
        .btn-gold {
            background-color: var(--gold-primary);
            color: var(--navy-dark);
            font-weight: 600;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-gold:hover {
            background-color: var(--gold-hover);
            color: var(--navy-dark);
            transform: translateY(-1px);
        }

        .btn-outline-gold {
            border: 1px solid var(--gold-primary);
            color: var(--gold-primary);
            font-weight: 500;
            background: transparent;
            transition: all 0.2s ease;
        }

        .btn-outline-gold:hover {
            background-color: var(--gold-primary);
            color: var(--navy-dark);
        }

        .btn-outline-danger-custom {
            border: 1px solid rgba(239, 68, 68, 0.6);
            color: #f87171;
            background: transparent;
            transition: all 0.2s ease;
        }

        .btn-outline-danger-custom:hover {
            background-color: #ef4444;
            color: #ffffff;
        }

        /* Design do Card Limpo */
        .card-modern {
            background: #ffffff;
            border: 1px solid #E2E8F0;
            border-radius: 20px;
            padding: 12px;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .card-modern:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px -6px rgba(10, 25, 47, 0.12);
            border-color: #CBD5E1;
        }

        /* Área da Imagem */
        .card-banner-box {
            width: 100%;
            height: 180px;
            border-radius: 14px;
            background-color: #F1F5F9;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-banner-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 12px;
            transition: transform 0.3s ease;
        }

        .card-modern:hover .card-banner-box img {
            transform: scale(1.04);
        }

        /* Conteúdo e Informações */
        .card-info {
            padding: 14px 4px 4px 4px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            justify-content: space-between;
        }

        .card-title-text {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
            line-height: 1.3;
        }

        .card-subtitle-text {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
            min-height: 38px;
        }

        /* Botão Acessar Painel */
        .btn-card-action {
            background-color: var(--navy-dark);
            color: #ffffff;
            font-weight: 600;
            border-radius: 12px;
            border: none;
            padding: 0.6rem 1rem;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            text-decoration: none;
        }

        .btn-card-action:hover {
            background-color: var(--gold-primary);
            color: var(--navy-dark) !important;
        }

        .modal-header-custom {
            background-color: var(--navy-dark);
            color: #ffffff;
            border-bottom: 2px solid var(--gold-primary);
        }

        @media (max-width: 768px) {
            .top-navbar {
                padding: 0.8rem 1rem;
            }
            .main-container {
                padding: 1.5rem 1rem;
            }
            .card-banner-box {
                height: 160px;
            }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="top-navbar d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-3">
        <div class="brand-icon shadow-sm">
            <i class="bi bi-shield-check"></i>
        </div>
        <div>
            <h6 class="fw-bold m-0 text-white"><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário') ?></h6>
            <small class="text-light opacity-75 d-block" style="font-size: 0.78rem;"><?= htmlspecialchars($perfil) ?></small>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">
        <?php if ($eh_geral): ?>
            <button class="btn btn-gold btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalNovaEdicao">
                <i class="bi bi-plus-lg"></i>
                <span class="d-none d-md-inline">Inserir Acampamento</span>
            </button>

            <a href="gestao-acessos.php" class="btn btn-outline-gold btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                <i class="bi bi-key-fill"></i>
                <span class="d-none d-md-inline">Acessos</span>
            </a>
        <?php endif; ?>

        <button class="btn btn-outline-danger-custom btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalConfirmarLogout">
            <i class="bi bi-box-arrow-right"></i>
            <span class="d-none d-md-inline">Sair</span>
        </button>
    </div>
</nav>

<div class="main-container">
    <div class="text-center mb-4">
        <h2 class="fw-bold text-dark m-0 mb-1" style="font-size: 1.8rem;">Selecione o Acampamento</h2>
        <p class="text-muted m-0" style="font-size: 0.9rem;">Escolha um abaixo para gerenciar equipes, cronograma e espiritualidade.</p>
    </div>

    <!-- GRID DE CARDS -->
    <div class="row g-4 justify-content-center">
        <?php if (!empty($modalidades)): ?>
            <?php foreach ($modalidades as $mod): 
                $corMod = !empty($mod['cor']) ? $mod['cor'] : '#D4AF37';
                $nomeMod = htmlspecialchars($mod['nome']);

                $nomeLimpo = mb_strtolower(trim($mod['nome']), 'UTF-8');
                $nomeLimpo = preg_replace('/[áàãâä]/u', 'a', $nomeLimpo);
                $nomeLimpo = preg_replace('/[éèêë]/u', 'e', $nomeLimpo);
                $nomeLimpo = preg_replace('/[íìîï]/u', 'i', $nomeLimpo);
                $nomeLimpo = preg_replace('/[óòõôö]/u', 'o', $nomeLimpo);
                $nomeLimpo = preg_replace('/[úùûü]/u', 'u', $nomeLimpo);

                $extensoes = ['png', 'jpg', 'jpeg', 'webp'];
                $caminhoLogo = null;

                if (!empty($mod['logo']) && file_exists($mod['logo'])) {
                    $caminhoLogo = $mod['logo'];
                } else {
                    foreach ($extensoes as $e) {
                        if (file_exists('uploads/logos/' . $nomeLimpo . '.' . $e)) {
                            $caminhoLogo = 'uploads/logos/' . $nomeLimpo . '.' . $e;
                            break;
                        }
                    }
                }
            ?>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <div class="card-modern">
                        <div class="card-banner-box">
                            <?php if ($caminhoLogo): ?>
                                <img src="<?= $caminhoLogo ?>?v=<?= time() ?>" alt="<?= $nomeMod ?>">
                            <?php else: ?>
                                <i class="bi bi-shield-fill fs-1 text-secondary opacity-50"></i>
                            <?php endif; ?>
                        </div>

                        <div class="card-info">
                            <div>
                                <h3 class="card-title-text"><?= $nomeMod ?></h3>
                                <p class="card-subtitle-text">
                                    <?= !empty($mod['lema']) ? htmlspecialchars($mod['lema']) : (!empty($mod['publico_alvo']) ? htmlspecialchars($mod['publico_alvo']) : 'Acampamento Paroquial') ?>
                                </p>
                            </div>

                            <a href="dashboard-acampamento.php?modalidade_id=<?= $mod['id'] ?>" 
                               class="btn btn-card-action w-100 d-flex align-items-center justify-content-center gap-2">
                                <span>Acessar Painel</span>
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-light border rounded-4 p-5 text-center text-secondary shadow-sm">
                    <i class="bi bi-house-exclamation fs-1 d-block mb-3 text-warning opacity-75"></i>
                    <h5 class="fw-bold text-dark">Nenhum acampamento encontrado</h5>
                    <p class="m-0 text-muted">Nenhum acampamento vinculado ao seu perfil no momento.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL NOVO ACAMPAMENTO -->
<?php if ($eh_geral): ?>
<div class="modal fade" id="modalNovaEdicao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
            <div class="modal-header modal-header-custom p-4">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-plus-circle-fill text-warning"></i> Inserir Novo Acampamento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="php/cadastrar-acampamento.php" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-medium text-dark">Nome do Acampamento</label>
                        <input type="text" name="nome" class="form-control rounded-3" placeholder="Ex: MIRIM" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-medium text-dark">Lema</label>
                        <input type="text" name="lema" class="form-control rounded-3" placeholder="Ex: Frase do acampamento">
                    </div>
                </div>
                <div class="modal-footer bg-light p-3 border-top">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gold rounded-3 px-4">Salvar Acampamento</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

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
</body>
</html>
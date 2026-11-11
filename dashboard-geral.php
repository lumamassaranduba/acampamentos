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
    $sql = "SELECT * FROM modalidades WHERE id > 0 ORDER BY nome ASC";
    $stmt = $conn->prepare($sql);
} else {
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

        /* Navbar Responsiva */
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
            min-width: 0; /* Previne overflow de texto flex */
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

        .main-container {
            padding: 2rem 1.25rem;
            max-width: 1300px;
            margin: 0 auto;
        }

        /* Botões Navbar */
        .btn-gold {
            background-color: var(--gold-primary);
            color: #ffffff;
            font-weight: 600;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-gold:hover {
            background-color: var(--gold-hover);
            color: #ffffff;
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
            color: #ffffff;
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

        /* Cards Elegantes */
        .card-acampamento {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.5rem 1.25rem 1.25rem 1.25rem;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            height: 100%;
            position: relative;
        }

        .card-acampamento:hover {
            transform: translateY(-6px);
            box-shadow: 0 15px 30px rgba(10, 25, 47, 0.1);
            border-color: var(--gold-primary);
        }

        .logo-circle-wrapper {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: #ffffff;
            border: 3px solid var(--gold-primary);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 1.2rem;
            padding: 6px;
            transition: transform 0.3s ease;
        }

        .card-acampamento:hover .logo-circle-wrapper {
            transform: scale(1.05);
        }

        .logo-circle-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }

        .card-content {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            justify-content: space-between;
            width: 100%;
        }

        .card-acampamento-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--navy-dark);
            margin-bottom: 0.3rem;
        }

        .card-acampamento-subtitle {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 1.25rem;
            min-height: 38px;
            line-height: 1.4;
        }

        .btn-card-primary {
            background-color: var(--navy-dark);
            color: #ffffff;
            font-weight: 600;
            border-radius: 12px;
            border: 1px solid var(--navy-dark);
            padding: 0.65rem 1rem;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-card-primary:hover {
            background-color: var(--gold-primary);
            border-color: var(--gold-primary);
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(197, 160, 89, 0.3);
        }

        .modal-header-custom {
            background-color: var(--navy-dark);
            color: #ffffff;
            border-bottom: 2px solid var(--gold-primary);
        }

        /* REGRAS ESPECÍFICAS PARA SMARTPHONES / CELULARES */
        @media (max-width: 576px) {
            .top-navbar {
                padding: 0.65rem 0.85rem;
            }

            .brand-icon {
                width: 36px;
                height: 36px;
                min-width: 36px;
            }

            .user-info-text h6 {
                font-size: 0.82rem !important;
            }

            .user-info-text small {
                font-size: 0.68rem !important;
            }

            .nav-actions {
                gap: 0.35rem !important;
            }

            .nav-actions .btn {
                padding: 0.4rem 0.6rem !important;
            }

            .logo-circle-wrapper {
                width: 90px;
                height: 90px;
            }

            .main-container {
                padding: 1.25rem 0.85rem;
            }
        }
    </style>
</head>
<body>

<!-- NAVBAR COM RESPIRO AUMENTADO -->
<nav class="top-navbar d-flex align-items-center justify-content-between gap-2">
    <div class="d-flex align-items-center gap-2 gap-sm-3 user-brand-block">
        <div class="brand-icon shadow-sm">
            <img src="./uploads/logos/logoefata.png" alt="Logo Éfata" onerror="this.onerror=null; this.src='assets/img/logo.png';">
        </div>
        <div class="user-info-text">
            <h6 class="fw-bold m-0 text-white text-truncate" style="font-size: 0.92rem;"><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário') ?></h6>
            <small class="text-light opacity-75 d-block text-truncate" style="font-size: 0.72rem;"><?= htmlspecialchars($perfil) ?></small>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 nav-actions">
        <?php if ($eh_geral): ?>
            <button class="btn btn-gold btn-sm rounded-3 px-2 px-sm-3 py-2 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalNovaEdicao" title="Inserir Acampamento">
                <i class="bi bi-plus-lg"></i>
                <span class="d-none d-md-inline">Inserir Acampamento</span>
            </button>

            <a href="gestao-acessos.php" class="btn btn-outline-gold btn-sm rounded-3 px-2 px-sm-3 py-2 d-flex align-items-center gap-2" title="Acessos">
                <i class="bi bi-key-fill"></i>
                <span class="d-none d-md-inline">Acessos</span>
            </a>
        <?php endif; ?>

        <button class="btn btn-outline-danger-custom btn-sm rounded-3 px-2 px-sm-3 py-2 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalConfirmarLogout" title="Sair">
            <i class="bi bi-box-arrow-right"></i>
            <span class="d-none d-md-inline">Sair</span>
        </button>
    </div>
</nav>

<div class="main-container">
    <div class="text-center mb-4 pb-2">
        <h2 class="fw-bold text-dark m-0 mb-1" style="font-size: 1.65rem;">Selecione o Acampamento</h2>
        <p class="text-muted m-0" style="font-size: 0.88rem;">Escolha um acampamento abaixo para gerenciar os módulos e edições.</p>
    </div>

    <!-- GRID DE CARDS -->
    <div class="row g-3 g-sm-4 justify-content-center">
        <?php if (!empty($modalidades)): ?>
            <?php foreach ($modalidades as $mod): 
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
                    <div class="card-acampamento">
                        <div class="logo-circle-wrapper">
                            <?php if ($caminhoLogo): ?>
                                <img src="<?= $caminhoLogo ?>?v=<?= time() ?>" alt="<?= $nomeMod ?>">
                            <?php else: ?>
                                <i class="bi bi-shield-fill fs-1 text-secondary opacity-50"></i>
                            <?php endif; ?>
                        </div>

                        <div class="card-content">
                            <div>
                                <h3 class="card-acampamento-title"><?= $nomeMod ?></h3>
                                <p class="card-acampamento-subtitle">
                                    <?= !empty($mod['lema']) ? htmlspecialchars($mod['lema']) : (!empty($mod['publico_alvo']) ? htmlspecialchars($mod['publico_alvo']) : 'Acampamento Paroquial') ?>
                                </p>
                            </div>

                            <a href="dashboard-acampamento.php?modalidade_id=<?= $mod['id'] ?>" class="btn btn-card-primary w-100">
                                <span>Acessar Painel</span>
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-light border rounded-4 p-4 p-sm-5 text-center text-secondary shadow-sm">
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
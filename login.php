<?php
// login.php
require_once 'config/database.php';
session_start();

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['usuario'] ?? '');
    $senha_input = trim($_POST['senha'] ?? '');


    if (!empty($login_input) && !empty($senha_input)) {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ? OR nome = ? LIMIT 1");
        $stmt->bind_param("ss", $login_input, $login_input);
        $stmt->execute();
        $usr = $stmt->get_result()->fetch_assoc();
 
        if ($usr && password_verify($senha_input, $usr['senha'])) {
            if (isset($usr['ativo']) && $usr['ativo'] == 0) {
                $erro = "Sua conta está inativa. Procure a coordenação.";
            } else {
                $_SESSION['usuario_id']     = $usr['id'];
                $_SESSION['usuario_nome']   = $usr['nome'];
                $_SESSION['usuario_perfil'] = $usr['perfil'];

                // Carrega todos os acampamentos liberados
                $stmt_mods = $conn->prepare("SELECT modalidade_id FROM usuario_modalidade WHERE usuario_id = ?");
                $stmt_mods->bind_param("i", $usr['id']);
                $stmt_mods->execute();
                $res_mods = $stmt_mods->get_result();

                $modalidades_permitidas = [];
                while ($row = $res_mods->fetch_assoc()) {
                    $modalidades_permitidas[] = (int)$row['modalidade_id'];
                }

                $_SESSION['modalidades_permitidas'] = $modalidades_permitidas;

                // Redirecionamento
                if ($usr['perfil'] === 'COORDENADORA_GERAL') {
                    header("Location: dashboard-geral.php");
                } elseif (count($modalidades_permitidas) === 1) {
                    // Direto para o único acampamento permitido
                    header("Location: dashboard-acampamento.php?modalidade_id=" . $modalidades_permitidas[0]);
                } else {
                    // Se tiver 2 ou mais acampamentos, vai para o hub de seleção
                    header("Location: dashboard-geral.php");
                }
                exit;
            }
        } else {
            $erro = "Usuário/E-mail ou senha incorretos!";
        }
    } else {
        $erro = "Preencha todos os campos.";
    }
}

// Lógica para detectar o arquivo 'logoefata.png'
$caminho_logo = '';
$arquivos_possiveis = [
    'uploads/logos/logoefata.png',
    'uploads/logos/logoefata.jpg',
    'uploads/logos/logoefata.webp',
    'uploads/logos/logoefata_png',
    'uploads/logos/chama_logoefata.png'
];

foreach ($arquivos_possiveis as $arquivo) {
    if (file_exists($arquivo)) {
        $caminho_logo = $arquivo;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Éfata - Gestão de Acampamentos</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --brand-navy: #0F172A;
            --brand-gold: #C5A059;
            --brand-gold-hover: #B08D4B;
            --brand-light: #F8FAFC;
            --brand-slate: #334155;
            --brand-border: #E2E8F0;
        }

        body { 
            font-family: 'Outfit', sans-serif; 
            background-color: var(--brand-light); 
            color: var(--brand-navy);
            margin: 0;
            padding: 0;
        }

        .login-wrapper {
            min-height: 100vh;
            display: flex;
        }

        /* Lado Esquerdo - Painel Azul-Marinho com Fundo do Ostensório */
        .brand-section {
            background-color: var(--brand-navy);
            color: #ffffff;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            text-align: center;
            position: relative;
            border-right: 4px solid var(--brand-gold);
            overflow: hidden;
            z-index: 1;
        }

        /* Imagem de Fundo Menos Transparente e Reposicionada para Cima */
        .brand-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: linear-gradient(rgba(15, 23, 42, 0.65), rgba(15, 23, 42, 0.65)), url('uploads/logos/fundo_ostensorio.jpg');
            background-size: cover;
            background-position: center 25%; /* Puxa a imagem mais para cima */
            background-repeat: no-repeat;
            opacity: 0.60; /* Menos transparente para aparecer mais */
            z-index: -1;
        }

        .brand-badge {
            background: rgba(15, 23, 42, 0.6);
            color: var(--brand-gold);
            border: 1px solid var(--brand-gold);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 24px;
            backdrop-filter: blur(4px);
        }

        /* Área de exibição da Logo */
        .brand-logo-container {
            width: 160px;
            height: 160px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 50%;
            border: 2px solid var(--brand-gold);
            padding: 6px;
            overflow: hidden;
            transition: all 0.3s ease;
            backdrop-filter: blur(4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
        }

        .brand-logo-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            filter: drop-shadow(0px 4px 10px rgba(0,0,0,0.4));
        }

        .brand-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #ffffff;
            text-shadow: 0 2px 8px rgba(0,0,0,0.7);
        }

        .brand-subtitle {
            color: var(--brand-gold);
            font-size: 0.95rem;
            font-weight: 500;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 12px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.7);
        }

        .brand-tagline {
            color: #FFFFFF;
            max-width: 340px;
            font-size: 0.90rem;
            line-height: 1.5;
            font-style: italic;
            text-shadow: 0 2px 4px rgba(0,0,0,0.8);
            font-weight: 500;
        }

        /* Lado Direito - Formulário */
        .form-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background-color: #ffffff;
        }

        .form-container {
            width: 100%;
            max-width: 380px;
        }

        .form-label {
            color: var(--brand-navy) !important;
            font-weight: 600 !important;
        }

        .form-control {
            border-color: var(--brand-border);
            color: var(--brand-navy);
            padding: 12px 14px;
        }

        .form-control:focus {
            border-color: var(--brand-gold);
            box-shadow: 0 0 0 0.25rem rgba(197, 160, 89, 0.20);
        }

        .btn-brand {
            background-color: var(--brand-gold);
            color: #ffffff;
            border: none;
            padding: 12px;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
        }

        .btn-brand:hover, .btn-brand:focus {
            background-color: var(--brand-gold-hover);
            color: #ffffff;
        }

        /* Adaptação Mobile */
        @media (max-width: 768px) {
            .login-wrapper {
                flex-direction: column;
            }

            .brand-section {
                padding: 40px 20px;
                border-right: none;
                border-bottom: 4px solid var(--brand-gold);
            }

            .brand-title {
                font-size: 2rem;
            }

            .brand-logo-container {
                width: 120px;
                height: 120px;
            }

            .form-section {
                padding: 40px 20px;
                background-color: var(--brand-light);
            }

            .form-container {
                background: #ffffff;
                padding: 28px;
                border-radius: 16px;
                border: 1px solid var(--brand-border);
                box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05);
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Lado Esquerdo: Marca & Logo Éfata -->
        <div class="brand-section">
            <div class="brand-badge">Gestão de Acampamentos</div>

            <!-- Espaço dedicado para a Imagem da Logo -->
            <div class="brand-logo-container">
                <?php if (!empty($caminho_logo)): ?>
                    <img src="<?= $caminho_logo ?>" alt="Logo Éfata" class="brand-logo-img">
                <?php else: ?>
                    <span class="text-muted small">Coloque a logo em<br><code>uploads/logos/logoefata.png</code></span>
                <?php endif; ?>
            </div>

            <p class="brand-tagline">"Abre-te para uma nova forma de organização paroquial."</p>
        </div>

        <!-- Lado Direito: Formulário de Login -->
        <div class="form-section">
            <div class="form-container">
                <h4 class="fw-bold mb-1">Acessar Conta</h4>
                <p class="text-muted small mb-4">Insira suas credenciais para continuar</p>

                <?php if (!empty($erro)): ?>
                    <div class="alert alert-danger rounded-3 py-2 text-center small"><?= htmlspecialchars($erro) ?></div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label small">Usuário ou E-mail</label>
                        <input type="text" name="usuario" class="form-control rounded-3" required autofocus placeholder="Digite seu usuário">
                    </div>
                    <div class="mb-4">
                        <label class="form-label small">Senha</label>
                        <input type="password" name="senha" class="form-control rounded-3" required placeholder="Digite sua senha">
                    </div>
                    <button type="submit" class="btn btn-brand w-100 rounded-3">Entrar</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
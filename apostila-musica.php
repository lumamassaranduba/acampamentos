<?php
// apostila-musica.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Controle de Acesso: Coordenadores Gerais, Coordenadores e Diretores
$perfis_permitidos = ['COORDENADORA_GERAL', 'COORDENADOR', 'DIRETOR'];
$perfil_usuario = $_SESSION['usuario_perfil'] ?? '';

if (!in_array($perfil_usuario, $perfis_permitidos)) {
    header("Location: dashboard-geral.php");
    exit();
}

// 2. Captura a edição vinculada na URL
$edicao_id = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;

// 3. Busca músicas específicas DESTA EDIÇÃO e também músicas sem edição definida (NULL / 0)
$musicas = [
    'TEMA' => [],
    'MISSA' => [],
    'MOMENTOS' => []
];

if ($edicao_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM apostilas_musicas WHERE edicao_id = ? OR edicao_id IS NULL OR edicao_id = 0 ORDER BY categoria, subcategoria, id ASC");
    $stmt->bind_param("i", $edicao_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $cat = $row['categoria'];
            $sub = !empty($row['subcategoria']) ? $row['subcategoria'] : 'Geral';
            $musicas[$cat][$sub][] = $row;
        }
    }
}

// 4. Busca nome amigável da edição para o cabeçalho
$nome_completo_edicao = "";
if ($edicao_id > 0) {
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
}

// Função para renderizar os cards das músicas
function renderCategoria($subcategorias) {
    if (empty($subcategorias)) {
        echo '<div class="text-center py-5 text-muted"><i class="bi bi-music-note-beamed fs-2 d-block mb-2 text-gold"></i>Nenhuma música cadastrada para esta edição.</div>';
        return;
    }

    foreach ($subcategorias as $subcat => $lista) {
        echo '<div class="secao-subcategoria mb-4">';
        if ($subcat !== 'Geral') {
            echo '<h6 class="fw-bold text-uppercase mb-3 d-flex align-items-center gap-2 subcat-titulo">';
            echo '   <span class="badge badge-subcat rounded-pill px-3 py-2">' . htmlspecialchars($subcat) . '</span>';
            echo '</h6>';
        }
        
        echo '<div class="row g-3 align-items-stretch">';
        foreach ($lista as $m) {
            echo '<div class="col-12 col-md-6 col-lg-4 card-musica-item d-flex" ';
            echo '     data-titulo="' . htmlspecialchars(mb_strtolower($m['titulo'] ?? '')) . '" ';
            echo '     data-artista="' . htmlspecialchars(mb_strtolower($m['artista'] ?? '')) . '" ';
            echo '     data-letra="' . htmlspecialchars(mb_strtolower($m['letra'] ?? '')) . '">';
            echo '  <div class="card-musica p-4 w-100 d-flex flex-column justify-content-between">';
            echo '      <div>';
            echo '          <div class="d-flex justify-content-between align-items-start mb-2 gap-2">';
            echo '              <h6 class="fw-bold m-0 text-dark-blue">' . htmlspecialchars($m['titulo']) . '</h6>';
            if (!empty($m['artista'])) {
                echo '              <small class="text-gold fw-semibold fs-7 text-nowrap">' . htmlspecialchars($m['artista']) . '</small>';
            }
            echo '          </div>';
            echo '          <hr class="my-2 border-gold-subtle">';
            echo '          <div class="letra-box mt-2">' . htmlspecialchars($m['letra']) . '</div>';
            echo '      </div>';
            echo '  </div>';
            echo '</div>';
        }
        echo '</div>'; 
        echo '</div>'; 
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apostila de Músicas - ÉFATA</title>
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

        .text-dark-blue { color: var(--efata-dark); }
        .text-gold { color: var(--efata-gold); }
        .border-gold-subtle { border-color: rgba(197, 160, 89, 0.25) !important; }

        .btn-gold {
            background-color: var(--efata-gold);
            color: #ffffff;
            font-weight: 600;
            border: none;
            transition: all 0.2s ease;
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

        .input-efata {
            border: 1px solid var(--efata-border);
            background-color: #ffffff;
            color: var(--efata-dark);
        }
        .input-efata:focus {
            border-color: var(--efata-gold);
            box-shadow: 0 0 0 0.25rem rgba(197, 160, 89, 0.2);
            outline: none;
        }

        .nav-pills-efata {
            background: #ffffff;
            border: 1px solid var(--efata-border);
            border-radius: 16px;
            padding: 6px;
        }
        .nav-pills-efata .nav-link { 
            color: var(--efata-slate); 
            font-weight: 600; 
            border-radius: 12px; 
            padding: 10px 20px; 
        }
        .nav-pills-efata .nav-link.active { 
            background-color: var(--efata-dark); 
            color: #ffffff; 
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.15);
        }

        .card-musica { 
            background: #ffffff; 
            border: 1px solid var(--efata-border); 
            border-radius: 20px; 
            transition: all 0.25s ease; 
        }
        .card-musica:hover { 
            border-color: var(--efata-gold); 
            box-shadow: 0 8px 20px rgba(197, 160, 89, 0.12);
            transform: translateY(-2px);
        }

        .letra-box { 
            white-space: pre-line; 
            font-size: 0.95rem; 
            color: var(--efata-slate); 
            line-height: 1.6; 
        }

        .badge-subcat { 
            background-color: #ffffff; 
            color: var(--efata-gold); 
            font-weight: 600; 
            border: 1px solid var(--efata-gold); 
        }
    </style>
</head>
<body>

    <!-- Header ÉFATA -->
    <header class="topbar-efata py-3 px-4 d-flex align-items-center justify-content-between sticky-top shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <a href="<?= $edicao_id > 0 ? "edicao-detalhes.php?id=$edicao_id" : "dashboard-geral.php" ?>" class="btn btn-sm btn-outline-gold rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i class="bi bi-arrow-left fs-6"></i>
            </a>
            <div>
                <h5 class="fw-bold m-0 text-white">Apostila de Músicas</h5>
                <?php if (!empty($nome_completo_edicao)): ?>
                    <small class="text-gold"><?= htmlspecialchars($nome_completo_edicao) ?></small>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end align-items-center" style="max-width: 60%;">
            <input type="text" id="inputBusca" class="form-control form-control-sm input-efata rounded-3 px-3 py-2 w-auto flex-grow-1" placeholder="Buscar música, artista ou letra...">
            
            <!-- Botão Baixar PDF -->
            <a href="gerar-pdf-musicas.php?edicao_id=<?= $edicao_id ?>" target="_blank" class="btn btn-sm btn-outline-gold rounded-3 px-3 py-2 d-flex align-items-center gap-1 text-nowrap">
                <i class="bi bi-file-earmark-pdf-fill"></i> <span>Baixar PDF</span>
            </a>

            <!-- Botão Adicionar / Gerenciar Músicas (Sempre Visível) -->
            <a href="gerenciar-musicas.php?edicao_id=<?= $edicao_id ?>" class="btn btn-sm btn-gold rounded-3 px-3 py-2 d-flex align-items-center gap-1 text-nowrap">
                <i class="bi bi-plus-circle-fill"></i> <span>Adicionar música</span>
            </a>
        </div>
    </header>

    <main class="container py-4">
        <ul class="nav nav-pills nav-justified nav-pills-efata mb-4 shadow-sm" id="pills-tab" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="tab-tema" data-bs-toggle="pill" data-bs-target="#content-tema" type="button">
                    🔥 Tema
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tab-missa" data-bs-toggle="pill" data-bs-target="#content-missa" type="button">
                    ⛪ Missa
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tab-momentos" data-bs-toggle="pill" data-bs-target="#content-momentos" type="button">
                    🕊️ Pregações e Momentos
                </button>
            </li>
        </ul>

        <div class="tab-content" id="pills-tabContent">
            <div class="tab-pane fade show active" id="content-tema">
                <?php renderCategoria($musicas['TEMA']); ?>
            </div>
            <div class="tab-pane fade" id="content-missa">
                <?php renderCategoria($musicas['MISSA']); ?>
            </div>
            <div class="tab-pane fade" id="content-momentos">
                <?php renderCategoria($musicas['MOMENTOS']); ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('inputBusca').addEventListener('input', function() {
            const termo = this.value.toLowerCase().trim();
            const cards = document.querySelectorAll('.card-musica-item');

            cards.forEach(card => {
                const titulo = card.getAttribute('data-titulo') || '';
                const artista = card.getAttribute('data-artista') || '';
                const letra = card.getAttribute('data-letra') || '';

                if (termo === '' || titulo.includes(termo) || artista.includes(termo) || letra.includes(termo)) {
                    card.classList.remove('d-none');
                    card.classList.add('d-flex');
                } else {
                    card.classList.remove('d-flex');
                    card.classList.add('d-none');
                }
            });

            document.querySelectorAll('.secao-subcategoria').forEach(secao => {
                const visiveis = secao.querySelectorAll('.card-musica-item:not(.d-none)').length;
                secao.style.display = visiveis === 0 && termo !== '' ? 'none' : 'block';
            });
        });
    </script>
</body>
</html>
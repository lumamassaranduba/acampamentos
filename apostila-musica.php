<?php
// apostila-musica.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Controle de Acesso: Coordenadores Gerais, Coordenadores e Diretores
$perfis_permitidos = ['COORDENADORA_GERAL', 'COORDENADOR', 'DIRETOR'];
$perfil_usuario = strtoupper(trim($_SESSION['usuario_perfil'] ?? $_SESSION['perfil'] ?? ''));

if (!in_array($perfil_usuario, $perfis_permitidos)) {
    header("Location: dashboard-geral.php");
    exit();
}

$nome_usuario = $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? 'Valéria Guimarães';

// 2. Captura parâmetros da URL
$edicao_id     = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$modalidade_id = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;

// 3. Busca músicas específicas DESTA EDIÇÃO e também músicas sem edição definida (NULL / 0)
$musicas = [
    'TEMA'     => [],
    'MISSA'    => [],
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
}

// Função para renderizar os cards das músicas
function renderCategoria($subcategorias) {
    if (empty($subcategorias)) {
        echo '<div class="text-center py-5 bg-white rounded-4 border p-4 shadow-sm text-muted">
                <i class="bi bi-music-note-beamed fs-1 d-block mb-2" style="color: var(--gold-primary);"></i>
                <p class="mb-0 fw-semibold">Nenhuma música cadastrada nesta categoria.</p>
              </div>';
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
            echo '     data-titulo="' . htmlspecialchars(mb_strtolower($m['titulo'] ?? '', 'UTF-8')) . '" ';
            echo '     data-artista="' . htmlspecialchars(mb_strtolower($m['artista'] ?? '', 'UTF-8')) . '" ';
            echo '     data-letra="' . htmlspecialchars(mb_strtolower($m['letra'] ?? '', 'UTF-8')) . '">';
            echo '  <div class="card-musica p-3 p-sm-4 w-100 d-flex flex-column justify-content-between">';
            echo '      <div>';
            echo '          <div class="d-flex justify-content-between align-items-start mb-2 gap-2">';
            echo '              <h6 class="fw-bold m-0 text-dark-blue" style="word-break: break-word;">' . htmlspecialchars($m['titulo']) . '</h6>';
            if (!empty($m['artista'])) {
                echo '              <small class="text-gold fw-semibold text-end flex-shrink-0" style="font-size: 0.8rem; max-width: 45%;">' . htmlspecialchars($m['artista']) . '</small>';
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

$url_voltar = "dashboard-geral.php";
if ($edicao_id > 0) {
    $url_voltar = "edicao-detalhes.php?edicao_id={$edicao_id}" . ($modalidade_id > 0 ? "&modalidade_id={$modalidade_id}" : "");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Apostila de Músicas - ÉFATA</title>
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

        /* BOTÕES DA PALETA */
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

        .btn-outline-navy {
            border: 1.5px solid var(--navy-dark);
            color: var(--navy-dark);
            background: #ffffff;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-outline-navy:hover {
            background-color: var(--navy-dark);
            color: #ffffff;
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

        .modal-header-custom {
            background-color: var(--navy-dark);
            color: #ffffff;
            border-bottom: 2px solid var(--gold-primary);
        }

        .text-dark-blue { color: var(--navy-dark); }
        .text-gold { color: var(--gold-primary); }
        .border-gold-subtle { border-color: rgba(197, 160, 89, 0.25) !important; }

        /* BARRA DE BUSCA */
        .search-box-custom {
            background: #ffffff;
            border: 1.5px solid var(--border-color);
            border-radius: 50px;
            padding: 4px 16px;
            transition: all 0.2s ease;
        }
        .search-box-custom:focus-within {
            border-color: var(--gold-primary);
            box-shadow: 0 4px 14px rgba(197, 160, 89, 0.15);
        }
        .search-input-custom {
            font-size: 0.92rem;
            padding: 6px 8px;
        }
        .search-input-custom:focus { box-shadow: none !important; }

        /* ABAS DE CATEGORIA 100% RESPONSIVAS (CABEM NA TELA SEM CORTAR NO CELULAR) */
        .nav-pills-efata {
            background: #ffffff;
            border: 1.5px solid var(--border-color);
            border-radius: 16px;
            padding: 6px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
        }
        .nav-pills-efata .nav-item {
            min-width: 0;
        }
        .nav-pills-efata .nav-link {
            width: 100%;
            height: 100%;
            color: var(--slate-text);
            font-weight: 600;
            border-radius: 12px;
            padding: 10px 8px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-align: center;
            line-height: 1.2;
            transition: all 0.2s ease;
        }
        .nav-pills-efata .nav-link.active {
            background-color: var(--navy-dark);
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(10, 25, 47, 0.15);
        }

        /* CARDS DE MÚSICA */
        .card-musica {
            background: #ffffff;
            border: 1.5px solid var(--border-color);
            border-top: 4px solid var(--gold-primary);
            border-radius: 20px;
            transition: all 0.25s ease;
        }
        .card-musica:hover {
            border-color: var(--gold-primary);
            box-shadow: 0 8px 20px rgba(197, 160, 89, 0.14);
            transform: translateY(-2px);
        }

        .letra-box {
            white-space: pre-line;
            font-size: 0.93rem;
            color: var(--slate-text);
            line-height: 1.6;
            word-break: break-word;
        }

        .badge-subcat {
            background-color: #ffffff;
            color: #8C6A28;
            font-weight: 700;
            border: 1.5px solid var(--gold-primary);
        }

        /* RESPONSIVIDADE MOBILE E TABLET */
        .header-actions-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        @media (max-width: 767.98px) {
            .header-actions-group {
                display: grid;
                grid-template-columns: 1fr 1fr;
                width: 100%;
                padding-top: 12px;
                border-top: 1px solid #F1F5F9;
            }
            .header-actions-group .btn {
                justify-content: center;
                padding: 10px 8px;
                font-size: 0.85rem;
            }
            .nav-pills-efata .nav-link {
                flex-direction: column;
                padding: 8px 4px;
                font-size: 0.78rem;
                gap: 3px;
            }
        }
    </style>
</head>
<body>

    <!-- IFRAME OCULTO PARA IMPRESSÃO DIRETA DO FORMATO PDF SEM ABRIR NOVA GUIA -->
    <iframe id="iframeImpressaoPdf" style="position: fixed; width: 0; height: 0; border: 0; visibility: hidden;"></iframe>

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

        <div class="d-flex align-items-center gap-2 nav-actions">
            <button class="btn btn-outline-danger-custom btn-sm rounded-3 px-2 px-sm-3 py-2 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalConfirmarLogout" title="Sair">
                <i class="bi bi-box-arrow-right"></i>
                <span class="d-none d-md-inline">Sair</span>
            </button>
        </div>
    </nav>

    <!-- CABEÇALHO DA PÁGINA COM AÇÕES ORGANIZADAS -->
    <div class="bg-white border-bottom py-3 page-header-bar">
        <div class="container d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3" style="max-width: 1200px;">
            <div class="d-flex align-items-center gap-3">
                <a href="<?= htmlspecialchars($url_voltar) ?>" class="btn-only-arrow" title="Voltar">
                    <i class="bi bi-arrow-left fs-5"></i>
                </a>
                <div>
                    <h5 class="fw-bold m-0 text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-music-note-list" style="color: var(--gold-primary);"></i>Apostila de Músicas
                    </h5>
                    <?php if (!empty($nome_completo_edicao)): ?>
                        <small class="text-muted fw-medium"><?= htmlspecialchars($nome_completo_edicao) ?></small>
                    <?php else: ?>
                        <small class="text-muted">Repertório musical e letras</small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- BLOCO DE AÇÕES: IMPRIMIR DIRETO E GERENCIAR MÚSICAS -->
            <div class="header-actions-group">
                <button type="button" id="btnImprimirApostila" onclick="imprimirApostilaDireto()" class="btn btn-outline-navy rounded-pill px-3 px-sm-4 py-2 btn-sm d-flex align-items-center gap-2">
                    <i class="bi bi-printer-fill" style="color: var(--gold-primary);"></i>
                    <span id="textoBtnImprimir">Imprimir</span>
                </button>

                <a href="gerenciar-musicas.php?edicao_id=<?= $edicao_id ?><?= $modalidade_id > 0 ? '&modalidade_id=' . $modalidade_id : '' ?>" class="btn btn-gold rounded-pill px-3 px-sm-4 py-2 btn-sm d-flex align-items-center gap-2">
                    <i class="bi bi-sliders"></i>
                    <span>Gerenciar Músicas</span>
                </a>
            </div>
        </div>
    </div>

    <main class="container py-3 py-md-4" style="max-width: 1200px;">

        <!-- BARRA DE PESQUISA DEDICADA -->
        <div class="mb-3 mb-md-4 search-wrapper">
            <div class="search-box-custom d-flex align-items-center shadow-sm">
                <i class="bi bi-search text-muted fs-6 ms-1 me-2"></i>
                <input type="text" id="inputBusca" class="form-control border-0 bg-transparent search-input-custom" placeholder="Pesquisar música por título, artista ou letra..." autocomplete="off">
                <button type="button" id="btnLimparBusca" class="btn p-0 border-0 text-muted ms-2 me-1 d-none" onclick="limparBusca()" title="Limpar pesquisa">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        <!-- ABAS DE CATEGORIAS -->
        <ul class="nav nav-pills nav-pills-efata mb-4 shadow-sm" id="pills-tab" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="tab-tema" data-bs-toggle="pill" data-bs-target="#content-tema" type="button">
                    <span>🔥</span> <span>Tema</span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tab-missa" data-bs-toggle="pill" data-bs-target="#content-missa" type="button">
                    <span>⛪</span> <span>Missa</span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tab-momentos" data-bs-toggle="pill" data-bs-target="#content-momentos" type="button">
                    <span>🕊️</span> <span>Momentos</span>
                </button>
            </li>
        </ul>

        <!-- CONTEÚDO DAS ABAS -->
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
        const inputBusca = document.getElementById('inputBusca');
        const btnLimparBusca = document.getElementById('btnLimparBusca');

        function filtrarMusicas() {
            const termo = inputBusca.value.toLowerCase().trim();
            btnLimparBusca.classList.toggle('d-none', termo === '');

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
                secao.style.display = (visiveis === 0 && termo !== '') ? 'none' : 'block';
            });
        }

        function limparBusca() {
            inputBusca.value = '';
            filtrarMusicas();
            inputBusca.focus();
        }

        inputBusca.addEventListener('input', filtrarMusicas);

        function imprimirApostilaDireto() {
            const btn = document.getElementById('btnImprimirApostila');
            const texto = document.getElementById('textoBtnImprimir');
            const iframe = document.getElementById('iframeImpressaoPdf');

            btn.disabled = true;
            texto.innerText = 'Preparando...';

            iframe.onload = function () {
                btn.disabled = false;
                texto.innerText = 'Imprimir';
                try {
                    iframe.contentWindow.focus();
                    iframe.contentWindow.print();
                } catch (e) {
                    window.location.href = 'gerar-pdf-musicas.php?edicao_id=<?= $edicao_id ?>';
                }
            };

            iframe.src = 'gerar-pdf-musicas.php?edicao_id=<?= $edicao_id ?>&t=' + Date.now();
        }
    </script>
</body>
</html>
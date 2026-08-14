<?php
// equipes-trabalho.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$edicao_id = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;

if ($edicao_id === 0) {
    die("Edição não especificada.");
}

$mensagem_sucesso = '';
$mensagem_erro = '';

// PROCESSAMENTOS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // RF08: Criar Setor/Equipe
    if ($acao === 'criar_equipe') {
        $nome = trim($_POST['nome'] ?? '');
        if (!empty($nome)) {
            $stmt = $conn->prepare("INSERT INTO equipes_trabalho (edicao_id, nome) VALUES (?, ?)");
            $stmt->bind_param("is", $edicao_id, $nome);
            $stmt->execute();
            $mensagem_sucesso = "Equipe criada com sucesso!";
        }
    }

    // RF09 / RN03: Alocar Voluntário da Equipe Geral
    if ($acao === 'alocar_membro') {
        $equipe_id = intval($_POST['equipe_id'] ?? 0);
        $voluntario_id = intval($_POST['voluntario_id'] ?? 0);
        $e_coordenador = isset($_POST['e_coordenador']) ? 1 : 0;

        if ($equipe_id > 0 && $voluntario_id > 0) {
            $stmt_check = $conn->prepare("SELECT id FROM equipe_membros WHERE equipe_id = ? AND voluntario_id = ?");
            $stmt_check->bind_param("ii", $equipe_id, $voluntario_id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows == 0) {
                $stmt = $conn->prepare("INSERT INTO equipe_membros (equipe_id, voluntario_id, e_coordenador) VALUES (?, ?, ?)");
                $stmt->bind_param("iii", $equipe_id, $voluntario_id, $e_coordenador);
                $stmt->execute();
                $mensagem_sucesso = "Voluntário alocado com sucesso!";
            } else {
                $mensagem_erro = "Este voluntário já está alocado nesta equipe.";
            }
        }
    }

    // RF10 / RN04: Alternar Status de Coordenador
    if ($acao === 'toggle_coordenador') {
        $membro_id = intval($_POST['membro_id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);
        $stmt = $conn->prepare("UPDATE equipe_membros SET e_coordenador = ? WHERE id = ?");
        $stmt->bind_param("ii", $status, $membro_id);
        $stmt->execute();
        $mensagem_sucesso = "Status de liderança atualizado!";
    }

    // Remover Voluntário da Equipe
    if ($acao === 'remover_membro') {
        $membro_id = intval($_POST['membro_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM equipe_membros WHERE id = ?");
        $stmt->bind_param("i", $membro_id);
        $stmt->execute();
        $mensagem_sucesso = "Voluntário removido da equipe.";
    }
}

// BUSCAS
$stmt = $conn->prepare("SELECT * FROM equipes_trabalho WHERE edicao_id = ? ORDER BY nome ASC");
$stmt->bind_param("i", $edicao_id);
$stmt->execute();
$equipes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt_geral = $conn->prepare("SELECT id, nome, telefone FROM equipe_geral WHERE edicao_id = ? ORDER BY nome ASC");
$stmt_geral->bind_param("i", $edicao_id);
$stmt_geral->execute();
$voluntarios_geral = $stmt_geral->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Divisão de Equipes - ÉFATA</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --efata-dark: #0F172A; --efata-gold: #C5A059; --efata-bg: #F8FAFC; }
        body { font-family: 'Outfit', sans-serif; background-color: var(--efata-bg); }
        .topbar-efata { background-color: var(--efata-dark); border-bottom: 2px solid var(--efata-gold); color: #fff; }
        .btn-gold { background-color: var(--efata-gold); color: #fff; font-weight: 600; border: none; }
        .btn-gold:hover { background-color: #B38E48; color: #fff; }
        .card-equipe { background: #fff; border-radius: 16px; border: 1px solid #E2E8F0; }
        
        /* RN04: Destaque visual em Negrito para Coordenadores */
        .coordenador-item {
            font-weight: 700 !important;
            color: var(--efata-dark);
            background-color: #FFFBEB;
            border-left: 4px solid var(--efata-gold);
        }
    </style>
</head>
<body>

    <header class="topbar-efata py-3 px-4 d-flex align-items-center justify-content-between sticky-top">
        <div class="d-flex align-items-center gap-3">
            <a href="equipe-hub.php?edicao_id=<?= $edicao_id ?>" class="btn btn-sm btn-outline-light rounded-circle">
                <i class="bi bi-arrow-left"></i>
            </a>
            <h5 class="fw-bold m-0 text-white">2. Divisão das Equipes de Trabalho</h5>
        </div>
        <button class="btn btn-gold btn-sm px-3 rounded-3" data-bs-toggle="modal" data-bs-target="#modalNovaEquipe">
            <i class="bi bi-plus-lg me-1"></i> Criar Nova Equipe
        </button>
    </header>

    <main class="container py-4">

        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= $mensagem_sucesso ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <?php if (!empty($mensagem_erro)): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= $mensagem_erro ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="row g-4">
            <?php if (empty($equipes)): ?>
                <div class="col-12 text-center py-5 text-muted">
                    <h5>Nenhuma equipe cadastrada.</h5>
                    <p>Clique em "+ Criar Nova Equipe" para cadastrar um setor (Ex: Cozinha, Secretaria, Apoio...).</p>
                </div>
            <?php else: ?>
                <?php foreach ($equipes as $eq): ?>
                    <?php
                    $stmt_m = $conn->prepare("SELECT em.id as membro_id, em.e_coordenador, eg.nome, eg.telefone, eg.tamanho_camiseta 
                                             FROM equipe_membros em 
                                             JOIN equipe_geral eg ON em.voluntario_id = eg.id 
                                             WHERE em.equipe_id = ? 
                                             ORDER BY em.e_coordenador DESC, eg.nome ASC");
                    $stmt_m->bind_param("i", $eq['id']);
                    $stmt_m->execute();
                    $membros = $stmt_m->get_result()->fetch_all(MYSQLI_ASSOC);
                    ?>
                    <div class="col-12 col-md-6">
                        <div class="card-equipe p-4 h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold text-dark m-0"><i class="bi bi-diagram-3-fill text-warning me-2"></i><?= htmlspecialchars($eq['nome']) ?></h5>
                                    <span class="badge bg-dark"><?= count($membros) ?> Integrantes</span>
                                </div>

                                <!-- Lista dos Integrantes -->
                                <h6 class="fw-bold small text-muted mb-2">MEMBROS DA EQUIPE:</h6>
                                <ul class="list-group mb-3">
                                    <?php if (empty($membros)): ?>
                                        <li class="list-group-item text-center text-muted small py-3">Nenhum voluntário alocado nesta equipe.</li>
                                    <?php else: ?>
                                        <?php foreach ($membros as $m): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 <?= $m['e_coordenador'] ? 'coordenador-item' : '' ?>">
                                                <div>
                                                    <!-- RN04: Nome do Coordenador com destaque em Negrito -->
                                                    <?= htmlspecialchars($m['nome']) ?>
                                                    <?php if ($m['e_coordenador']): ?>
                                                        <span class="badge bg-warning text-dark ms-1"><i class="bi bi-star-fill"></i> Coordenador</span>
                                                    <?php endif; ?>
                                                    <br><small class="text-muted fw-normal"><?= htmlspecialchars($m['telefone']) ?> | Camiseta: <?= $m['tamanho_camiseta'] ?></small>
                                                </div>
                                                <div class="d-flex gap-1">
                                                    <form action="equipes-trabalho.php?edicao_id=<?= $edicao_id ?>" method="POST" class="d-inline">
                                                        <input type="hidden" name="acao" value="toggle_coordenador">
                                                        <input type="hidden" name="membro_id" value="<?= $m['membro_id'] ?>">
                                                        <input type="hidden" name="status" value="<?= $m['e_coordenador'] ? 0 : 1 ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-warning text-dark" title="Definir/Remover Liderança">
                                                            <i class="bi bi-star<?= $m['e_coordenador'] ? '-fill' : '' ?>"></i>
                                                        </button>
                                                    </form>
                                                    <form action="equipes-trabalho.php?edicao_id=<?= $edicao_id ?>" method="POST" class="d-inline" onsubmit="return confirm('Remover da equipe?');">
                                                        <input type="hidden" name="acao" value="remover_membro">
                                                        <input type="hidden" name="membro_id" value="<?= $m['membro_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>

                            <button class="btn btn-outline-dark btn-sm w-100 rounded-3 mt-2" onclick="abrirModalAlocar(<?= $eq['id'] ?>, '<?= htmlspecialchars(addslashes($eq['nome'])) ?>')">
                                <i class="bi bi-person-plus"></i> Alocar Voluntário da Equipe Geral
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Nova Equipe -->
    <div class="modal fade" id="modalNovaEquipe" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-diagram-3 text-warning me-2"></i>Nova Equipe / Setor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="equipes-trabalho.php?edicao_id=<?= $edicao_id ?>" method="POST">
                    <input type="hidden" name="acao" value="criar_equipe">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nome da Equipe *</label>
                            <input type="text" name="nome" class="form-control" required placeholder="Ex: Cozinha, Secretaria, Apoio, Intercessão...">
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-gold">Criar Equipe</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Alocar Voluntário (RF09 / RN03) -->
    <div class="modal fade" id="modalAlocar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-check text-warning me-2"></i>Alocar para: <span id="alocar_nome_equipe"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="equipes-trabalho.php?edicao_id=<?= $edicao_id ?>" method="POST">
                    <input type="hidden" name="acao" value="alocar_membro">
                    <input type="hidden" name="equipe_id" id="alocar_equipe_id">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Selecione o Voluntário da Equipe Geral *</label>
                            <select name="voluntario_id" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($voluntarios_geral as $vg): ?>
                                    <option value="<?= $vg['id'] ?>"><?= htmlspecialchars($vg['nome']) ?> (<?= htmlspecialchars($vg['telefone']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="e_coordenador" id="checkCoordenador" value="1">
                            <label class="form-check-label fw-semibold" for="checkCoordenador">
                                Definir como Coordenador da Equipe (RN04)
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-gold">Confirmar Alocação</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function abrirModalAlocar(id, nome) {
            document.getElementById('alocar_equipe_id').value = id;
            document.getElementById('alocar_nome_equipe').innerText = nome;
            new bootstrap.Modal(document.getElementById('modalAlocar')).show();
        }
    </script>
</body>
</html>
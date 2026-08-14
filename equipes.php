<?php
// equipes.php
require_once 'config/database.php';

// 1. Tratamento do ID
$modalidade_id = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 1;

// 2. Buscar dados da modalidade
$stmt = $conn->prepare("SELECT * FROM modalidades WHERE id = ?");
$stmt->bind_param("i", $modalidade_id);
$stmt->execute();
$acampamento = $stmt->get_result()->fetch_assoc();

if (!$acampamento) {
    $acampamento = [
        'nome' => 'Modalidade Não Encontrada',
        'icone' => 'bi-exclamation-triangle'
    ];
}

// 3. Buscar setores
$stmt_setores = $conn->prepare("SELECT * FROM setores WHERE modalidade_id = ? ORDER BY nome ASC");
$stmt_setores->bind_param("i", $modalidade_id);
$stmt_setores->execute();
$setores_res = $stmt_setores->get_result();
$setores = [];
while ($s = $setores_res->fetch_assoc()) {
    $setores[] = $s;
}

// 4. Buscar voluntários (SEM A COLUNA DE COR)
$query_voluntarios = "SELECT v.*, s.nome as setor_nome 
                      FROM voluntarios v 
                      LEFT JOIN setores s ON v.setor_id = s.id 
                      WHERE v.modalidade_id = ? 
                      ORDER BY v.nome ASC";
$stmt_vol = $conn->prepare($query_voluntarios);
$stmt_vol->bind_param("i", $modalidade_id);
$stmt_vol->execute();
$voluntarios = $stmt_vol->get_result();

$total_voluntarios = $voluntarios->num_rows;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipe de Trabalho - <?= htmlspecialchars($acampamento['nome']) ?></title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
        }
        .sidebar {
            width: 260px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #0f172a;
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            z-index: 100;
        }
        .main-content {
            margin-left: 260px;
            padding: 32px;
        }
        .nav-link-custom {
            color: #94a3b8;
            padding: 10px 14px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        .nav-link-custom:hover, .nav-link-custom.active {
            color: #ffffff;
            background-color: #1e293b;
        }
        .card-dash {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
        }
        .table-custom {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .table-custom th {
            background-color: #f8fafc;
            color: #64748b;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        .table-custom td {
            padding: 16px 20px;
            vertical-align: middle;
            color: #334155;
            font-size: 0.95rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .avatar-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #e2e8f0;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<!-- Sidebar Fixa -->
<aside class="sidebar">
    <a href="dashboard-geral.php" class="text-secondary text-decoration-none small mb-4 d-flex align-items-center gap-1">
        <i class="bi bi-arrow-left"></i> Voltar ao Hub Geral
    </a>
    
    <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom border-secondary border-opacity-25">
        <div class="rounded-3 p-2 text-white d-flex align-items-center justify-content-center bg-primary" style="width: 42px; height: 42px;">
            <i class="bi <?= htmlspecialchars($acampamento['icone'] ?? 'bi-tree') ?> fs-5"></i>
        </div>
        <div>
            <h5 class="fw-bold text-white m-0"><?= htmlspecialchars($acampamento['nome'] ?? 'Acampamento') ?></h5>
            <span class="text-secondary small">Painel de Gestão</span>
        </div>
    </div>

    <nav class="d-flex flex-column gap-1">
        <a href="dashboard-acampamento.php?modalidade_id=<?= $modalidade_id ?>" class="nav-link-custom">
            <i class="bi bi-grid-1x2"></i> Visão Geral
        </a>
        <a href="equipes.php?modalidade_id=<?= $modalidade_id ?>" class="nav-link-custom active">
            <i class="bi bi-people"></i> Equipe de Trabalho
        </a>
        <a href="reunioes.php?modalidade_id=<?= $modalidade_id ?>" class="nav-link-custom">
            <i class="bi bi-calendar-check"></i> Reuniões & Vivências
        </a>
        <a href="musicas.php?modalidade_id=<?= $modalidade_id ?>" class="nav-link-custom">
            <i class="bi bi-music-note-list"></i> Apostila de Músicas
        </a>
        <a href="cronograma.php?modalidade_id=<?= $modalidade_id ?>" class="nav-link-custom">
            <i class="bi bi-clock"></i> Cronograma Oficial
        </a>
    </nav>
</aside>

<!-- Conteúdo Principal -->
<main class="main-content">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold m-0 text-dark">Equipe de Trabalho</h3>
            <span class="text-secondary small">Gerencie os voluntários, papéis e alocações por setor</span>
        </div>
        
        <button class="btn btn-primary rounded-3 px-3 d-flex align-items-center gap-2 fw-medium" data-bs-toggle="modal" data-bs-target="#modalNovoVoluntario">
            <i class="bi bi-person-plus-fill"></i> Novo Voluntário
        </button>
    </div>

    <!-- Cards de Resumo -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card-dash d-flex align-items-center gap-3">
                <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-4">
                    <i class="bi bi-people-fill fs-3"></i>
                </div>
                <div>
                    <span class="text-secondary small fw-medium">Total de Voluntários</span>
                    <h3 class="fw-bold m-0"><?= $total_voluntarios ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-dash d-flex align-items-center gap-3">
                <div class="p-3 bg-success bg-opacity-10 text-success rounded-4">
                    <i class="bi bi-diagram-3-fill fs-3"></i>
                </div>
                <div>
                    <span class="text-secondary small fw-medium">Setores Ativos</span>
                    <h3 class="fw-bold m-0"><?= count($setores) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-dash d-flex align-items-center gap-3">
                <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-4">
                    <i class="bi bi-tag-fill fs-3"></i>
                </div>
                <div>
                    <span class="text-secondary small fw-medium">Gestão de Camisetas</span>
                    <h6 class="fw-bold m-0 text-dark">Controle por Tamanho</h6>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="table-custom shadow-sm">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Voluntário</th>
                    <th>Setor</th>
                    <th>Função / Cargo</th>
                    <th>Telefone</th>
                    <th>Camiseta</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($voluntarios->num_rows > 0): ?>
                    <?php while($vol = $voluntarios->fetch_assoc()): 
                        $nome = trim($vol['nome'] ?? '');
                        $nome_partes = explode(' ', $nome);
                        $iniciais = strtoupper(
                            substr($nome_partes[0] ?? '', 0, 1) . 
                            (isset($nome_partes[1]) ? substr($nome_partes[1], 0, 1) : '')
                        );
                        if (empty($iniciais)) { $iniciais = '?'; }
                    ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar-circle"><?= $iniciais ?></div>
                                    <div>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($vol['nome'] ?? '') ?></div>
                                        <div class="text-secondary small"><?= htmlspecialchars($vol['email'] ?? 'Sem e-mail cadastrado') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($vol['setor_nome'])): ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border fw-medium px-2 py-1 rounded">
                                        <i class="bi bi-dot"></i> <?= htmlspecialchars($vol['setor_nome']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-secondary border">Não Alocado</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($vol['funcao'] ?? 'Servidor') ?></td>
                            <td><?= htmlspecialchars($vol['telefone'] ?? '-') ?></td>
                            <td><span class="badge bg-secondary bg-opacity-10 text-secondary fw-semibold"><?= htmlspecialchars($vol['tamanho_camiseta'] ?? 'N/I') ?></span></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-light rounded-2 text-secondary me-1"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-light rounded-2 text-danger"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-secondary">
                            <i class="bi bi-people fs-1 d-block mb-2 text-slate-300"></i>
                            Nenhum voluntário cadastrado ainda para esta modalidade.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

<!-- Modal: Novo Voluntário -->
<div class="modal fade" id="modalNovoVoluntario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Cadastrar Voluntário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="equipe-salvar.php" method="POST">
                <input type="hidden" name="modalidade_id" value="<?= $modalidade_id ?>">
                
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Nome Completo</label>
                        <input type="text" name="nome" class="form-control rounded-3" placeholder="Ex: João da Silva" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Telefone / WhatsApp</label>
                            <input type="text" name="telefone" class="form-control rounded-3" placeholder="(00) 00000-0000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Tamanho Camiseta</label>
                            <select name="tamanho_camiseta" class="form-select rounded-3">
                                <option value="PP">PP</option>
                                <option value="P">P</option>
                                <option value="M" selected>M</option>
                                <option value="G">G</option>
                                <option value="GG">GG</option>
                                <option value="EXG">EXG</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium">Setor de Atuação</label>
                        <select name="setor_id" class="form-select rounded-3">
                            <option value="">Selecione um Setor...</option>
                            <?php foreach ($setores as $st): ?>
                                <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium">Função (Opcional)</label>
                        <input type="text" name="funcao" class="form-control rounded-3" placeholder="Ex: Coordenador, Apoio, Pregador">
                    </div>
                </div>

                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 fw-medium" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-3 fw-medium px-4">Salvar Voluntário</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
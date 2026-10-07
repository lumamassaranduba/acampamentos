<?php
// espiritualidade.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$edicao_id_param = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$modalidade_id_param = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;

$mensagem_sucesso = '';
$mensagem_erro = '';

// 2. EXCLUSÃO DE REGISTRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' AND isset($_POST['acao']) AND $_POST['acao'] === 'excluir_espiritualidade') {
    $esp_id = intval($_POST['espiritualidade_id'] ?? 0);
    if ($esp_id > 0) {
        $stmt_del = $conn->prepare("DELETE FROM espiritualidades WHERE id = ?");
        $stmt_del->bind_param("i", $esp_id);
        $stmt_del->execute();
        $mensagem_sucesso = "Momento excluído com sucesso!";
    }
}

// 3. CADASTRAR / EDITAR REGISTRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' AND isset($_POST['acao']) AND $_POST['acao'] === 'salvar_espiritualidade') {
    $esp_id = intval($_POST['espiritualidade_id'] ?? 0);
    $tipo = trim($_POST['tipo'] ?? 'Espiritualidade');
    $data_momento = !empty($_POST['data_momento']) ? $_POST['data_momento'] : NULL;
    $horario_momento = !empty($_POST['horario_momento']) ? $_POST['horario_momento'] : NULL;
    $animacao_responsavel = trim($_POST['animacao_responsavel'] ?? '');
    $oracao_inicial_responsavel = trim($_POST['oracao_inicial_responsavel'] ?? '');
    $pregacao_tema = trim($_POST['pregacao_tema'] ?? '');
    $pregacao_pregador = trim($_POST['pregacao_pregador'] ?? '');
    $detalhes = trim($_POST['detalhes'] ?? '');

    if ($esp_id > 0) {
        $stmt_u = $conn->prepare("UPDATE espiritualidades SET tipo=?, data_momento=?, horario_momento=?, animacao_responsavel=?, oracao_inicial_responsavel=?, pregacao_tema=?, pregacao_pregador=?, detalhes=? WHERE id=?");
        $stmt_u->bind_param("ssssssssi", $tipo, $data_momento, $horario_momento, $animacao_responsavel, $oracao_inicial_responsavel, $pregacao_tema, $pregacao_pregador, $detalhes, $esp_id);
        $stmt_u->execute();
        $mensagem_sucesso = "Registro atualizado com sucesso!";
    } else {
        $stmt_i = $conn->prepare("INSERT INTO espiritualidades (edicao_id, tipo, data_momento, horario_momento, animacao_responsavel, oracao_inicial_responsavel, pregacao_tema, pregacao_pregador, detalhes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_i->bind_param("issssssss", $edicao_id_param, $tipo, $data_momento, $horario_momento, $animacao_responsavel, $oracao_inicial_responsavel, $pregacao_tema, $pregacao_pregador, $detalhes);
        $stmt_i->execute();
        $mensagem_sucesso = "Registro criado com sucesso!";
    }
}

// 4. BUSCAR LISTA DE REGISTROS
$sql = "SELECT * FROM espiritualidades WHERE 1=1";
if ($edicao_id_param > 0) {
    $sql .= " AND (edicao_id = {$edicao_id_param} OR edicao_id IS NULL OR edicao_id = 0)";
}
$sql .= " ORDER BY data_momento ASC, horario_momento ASC, id DESC";
$res = $conn->query($sql);
$lista_espiritualidades = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espiritualidade e Vivência</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --efata-dark: #0F172A; --efata-gold: #C5A059; --efata-bg: #F8FAFC; }
        body { font-family: 'Outfit', sans-serif; background-color: var(--efata-bg); }
        .topbar-efata { background-color: var(--efata-dark); border-bottom: 2px solid var(--efata-gold); color: #fff; }
        .card-espiritualidade { background: #fff; border-radius: 14px; border: 1px solid #E2E8F0; padding: 14px; transition: all 0.2s; height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
        .card-espiritualidade:hover { border-color: var(--efata-gold); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .badge-tipo { font-size: 0.75rem; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
        .badge-espiritualidade { background-color: #EEF2FF; color: #4F46E5; }
        .badge-vivencia { background-color: #FEF3C7; color: #D97706; }
        .item-ordem { padding: 6px 10px; background-color: #F8FAFC; border-radius: 6px; border-left: 3px solid #CBD5E1; font-size: 0.825rem; }
    </style>
</head>
<body>

    <header class="topbar-efata py-3 px-4 d-flex align-items-center justify-content-between sticky-top">
        <div class="d-flex align-items-center gap-3">
            <?php if ($edicao_id_param > 0): ?>
                <a href="edicao-detalhes.php?edicao_id=<?= $edicao_id_param ?>&modalidade_id=<?= $modalidade_id_param ?>" class="btn btn-sm btn-outline-light rounded-circle">
                    <i class="bi bi-arrow-left"></i>
                </a>
            <?php endif; ?>
            <div>
                <h5 class="fw-bold m-0 text-white"><i class="bi bi-heart-pulse-fill text-warning me-2"></i>Espiritualidade & Vivência</h5>
                <small class="text-white-50">Cronograma dos momentos</small>
            </div>
        </div>

        <button class="btn btn-warning rounded-pill px-3 fw-bold text-dark d-flex align-items-center gap-2" onclick="abrirModalForm()">
            <i class="bi bi-plus-circle-fill"></i> Novo
        </button>
    </header>

    <main class="container py-4" style="max-width: 1000px;">

        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4 rounded-3 shadow-sm"><?= $mensagem_sucesso ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <?php if (empty($lista_espiritualidades)): ?>
            <div class="text-center py-5 bg-white rounded-4 border p-4 shadow-sm">
                <i class="bi bi-journal-x display-3 text-muted d-block mb-3"></i>
                <h5 class="fw-bold text-dark">Nenhum momento cadastrado</h5>
                <p class="text-muted small">Clique no botão "Novo" acima para criar uma Espiritualidade ou Vivência.</p>
            </div>
        <?php else: ?>
            <!-- Grid de 2 Colunas no Desktop -->
            <div class="row row-cols-1 row-cols-md-2 g-3">
                <?php foreach ($lista_espiritualidades as $item): ?>
                    <?php
                    $tipo_lc = strtolower($item['tipo']);
                    $is_vivencia = ($tipo_lc === 'vivência' OR $tipo_lc === 'vivencia');
                    
                    $badge_class = $is_vivencia ? 'badge-vivencia' : 'badge-espiritualidade';
                    
                    $data_formatada = !empty($item['data_momento']) ? date('d/m/Y', strtotime($item['data_momento'])) : 'Sem data';
                    $hora_formatada = !empty($item['horario_momento']) ? date('H:i', strtotime($item['horario_momento'])) : 'Sem horário';

                    $json_item = htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8');
                    ?>

                    <div class="col">
                        <div class="card-espiritualidade shadow-sm">
                            <div>
                                <!-- Cabeçalho com Data em Destaque -->
                                <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="badge-tipo <?= $badge_class ?>"><?= htmlspecialchars($item['tipo']) ?></span>
                                        
                                        <!-- Destaque na Data e Horário -->
                                        <span class="badge bg-primary text-white fw-bold px-2 py-1 rounded-3" style="font-size: 0.825rem;">
                                            <i class="bi bi-calendar-event me-1"></i><?= $data_formatada ?>
                                        </span>
                                        <span class="badge bg-light text-dark border fw-semibold px-2 py-1 rounded-3" style="font-size: 0.825rem;">
                                            <i class="bi bi-clock me-1 text-primary"></i><?= $hora_formatada ?>
                                        </span>
                                    </div>

                                    <div class="d-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-sm btn-light rounded-circle p-1 px-2" title="Editar" onclick='abrirModalForm(<?= $json_item ?>)'>
                                            <i class="bi bi-pencil text-dark"></i>
                                        </button>

                                        <form method="POST" action="espiritualidade.php?edicao_id=<?= $edicao_id_param ?>&modalidade_id=<?= $modalidade_id_param ?>" onsubmit="return confirm('Tem certeza que deseja excluir este momento?');" class="d-inline">
                                            <input type="hidden" name="acao" value="excluir_espiritualidade">
                                            <input type="hidden" name="espiritualidade_id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-light rounded-circle text-danger p-1 px-2" title="Excluir">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <!-- Itens Cronológicos Compactos -->
                                <div class="d-flex flex-column gap-2">

                                    <?php if (!empty($item['oracao_inicial_responsavel'])): ?>
                                        <div class="item-ordem d-flex justify-content-between align-items-center">
                                            <span><i class="bi bi-hand-thumbs-up text-success me-1"></i><strong>Oração Inicial:</strong></span>
                                            <span class="text-dark fw-medium"><?= htmlspecialchars($item['oracao_inicial_responsavel']) ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($item['animacao_responsavel'])): ?>
                                        <div class="item-ordem d-flex justify-content-between align-items-center">
                                            <span><i class="bi bi-music-note-beaming text-primary me-1"></i><strong>Animação:</strong></span>
                                            <span class="text-dark fw-medium"><?= htmlspecialchars($item['animacao_responsavel']) ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!$is_vivencia): ?>
                                        <!-- Espiritualidade -->
                                        <?php if (!empty($item['pregacao_tema']) OR !empty($item['pregacao_pregador'])): ?>
                                            <div class="item-ordem">
                                                <div class="fw-bold text-secondary mb-1"><i class="bi bi-mic text-warning me-1"></i>Pregação:</div>
                                                <div class="ps-2">
                                                    <?php if (!empty($item['pregacao_tema'])): ?>
                                                        <div><strong>Tema:</strong> <?= htmlspecialchars($item['pregacao_tema']) ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['pregacao_pregador'])): ?>
                                                        <div><strong>Pregador:</strong> <?= htmlspecialchars($item['pregacao_pregador']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <!-- Vivência -->
                                        <?php if (!empty($item['pregacao_tema'])): ?>
                                            <div class="item-ordem">
                                                <div class="fw-bold text-secondary mb-1"><i class="bi bi-1-circle text-info me-1"></i>Momento 1:</div>
                                                <div class="ps-2"><?= htmlspecialchars($item['pregacao_tema']) ?></div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($item['pregacao_pregador'])): ?>
                                            <div class="item-ordem">
                                                <div class="fw-bold text-secondary mb-1"><i class="bi bi-2-circle text-info me-1"></i>Momento 2:</div>
                                                <div class="ps-2"><?= htmlspecialchars($item['pregacao_pregador']) ?></div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                </div>
                            </div>

                            <!-- Observações -->
                            <?php if (!empty($item['detalhes'])): ?>
                                <div class="mt-2 pt-2 border-top">
                                    <small class="text-muted d-block mb-1" style="font-size: 0.75rem;"><strong><i class="bi bi-info-circle me-1"></i>Detalhes:</strong></small>
                                    <p class="small text-dark m-0" style="white-space: pre-line; font-size: 0.8rem;"><?= htmlspecialchars($item['detalhes']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- Modal Form -->
    <div class="modal fade" id="modalForm" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="modalTitulo">Novo Momento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <form action="espiritualidade.php?edicao_id=<?= $edicao_id_param ?>&modalidade_id=<?= $modalidade_id_param ?>" method="POST">
                    <input type="hidden" name="acao" value="salvar_espiritualidade">
                    <input type="hidden" name="espiritualidade_id" id="form_id" value="0">

                    <div class="modal-body p-4">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tipo de Momento</label>
                            <select name="tipo" id="form_tipo" class="form-select" onchange="atualizarRotulosForm()" required>
                                <option value="Espiritualidade">Espiritualidade</option>
                                <option value="Vivência">Vivência</option>
                            </select>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Data</label>
                                <input type="date" name="data_momento" id="form_data" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Horário</label>
                                <input type="time" name="horario_momento" id="form_horario" class="form-control" required>
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Oração Inicial <small class="text-muted fw-normal">(Quem conduz)</small></label>
                            <input type="text" name="oracao_inicial_responsavel" id="form_oracao" class="form-control" placeholder="Nome do responsável">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Animação <small class="text-muted fw-normal">(Quem conduz)</small></label>
                            <input type="text" name="animacao_responsavel" id="form_animacao" class="form-control" placeholder="Nome da pessoa ou equipe">
                        </div>

                        <hr class="my-3">

                        <div class="mb-3">
                            <label class="form-label fw-semibold" id="label_campo_1">Pregação - Tema</label>
                            <input type="text" name="pregacao_tema" id="form_tema" class="form-control" placeholder="Informe o tema ou atração">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" id="label_campo_2">Pregação - Pregador(a)</label>
                            <input type="text" name="pregacao_pregador" id="form_pregador" class="form-control" placeholder="Informe o responsável">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Detalhes / Observações</label>
                            <textarea name="detalhes" id="form_detalhes" class="form-control" rows="3" placeholder="Informações adicionais, materiais necessários..."></textarea>
                        </div>

                    </div>

                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Salvar Momento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function atualizarRotulosForm() {
            const tipo = document.getElementById('form_tipo').value;
            const label1 = document.getElementById('label_campo_1');
            const label2 = document.getElementById('label_campo_2');

            if (tipo === 'Vivência' || tipo === 'vivencia') {
                label1.innerHTML = 'Momento 1 <small class="text-muted fw-normal">(Atividade e Responsável)</small>';
                label2.innerHTML = 'Momento 2 <small class="text-muted fw-normal">(Atividade e Responsável)</small>';
            } else {
                label1.innerHTML = 'Pregação - Tema';
                label2.innerHTML = 'Pregação - Pregador(a)';
            }
        }

        function abrirModalForm(dados = null) {
            const modalEl = new bootstrap.Modal(document.getElementById('modalForm'));

            if (dados) {
                document.getElementById('modalTitulo').innerText = 'Editar Momento';
                document.getElementById('form_id').value = dados.id;
                document.getElementById('form_tipo').value = dados.tipo || 'Espiritualidade';
                document.getElementById('form_data').value = dados.data_momento || '';
                document.getElementById('form_horario').value = dados.horario_momento || '';
                document.getElementById('form_animacao').value = dados.animacao_responsavel || '';
                document.getElementById('form_oracao').value = dados.oracao_inicial_responsavel || '';
                document.getElementById('form_tema').value = dados.pregacao_tema || '';
                document.getElementById('form_pregador').value = dados.pregacao_pregador || '';
                document.getElementById('form_detalhes').value = dados.detalhes || '';
            } else {
                document.getElementById('modalTitulo').innerText = 'Novo Momento';
                document.getElementById('form_id').value = '0';
                document.getElementById('form_tipo').value = 'Espiritualidade';
                document.getElementById('form_data').value = '';
                document.getElementById('form_horario').value = '';
                document.getElementById('form_animacao').value = '';
                document.getElementById('form_oracao').value = '';
                document.getElementById('form_tema').value = '';
                document.getElementById('form_pregador').value = '';
                document.getElementById('form_detalhes').value = '';
            }

            atualizarRotulosForm();
            modalEl.show();
        }
    </script>
</body>
</html>

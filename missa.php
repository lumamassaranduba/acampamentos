<?php
// missa.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$mensagem_sucesso = '';
$mensagem_erro = '';
require_once 'config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$edicao_id = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$modalidade_id = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;

// Lista de opções do ENUM permitidas
$dias_enum_permitidos = ['1º Dia', '2º Dia', '3º Dia', '4º Dia', '5º Dia', '6º Dia', '7º Dia'];

// --- 1. SALVAR / EDITAR MISSA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_salvar_missa'])) {
    $missa_id  = intval($_POST['missa_id'] ?? 0);
    $titulo    = trim($_POST['titulo'] ?? '');
    $dia_missa = trim($_POST['dia_missa'] ?? '');
    $horario   = trim($_POST['horario'] ?? '');
    $leituras  = trim($_POST['leituras'] ?? '');
    $preces    = trim($_POST['preces'] ?? '');

    // Validação para garantir que o valor enviado pertence ao ENUM
    if (!in_array($dia_missa, $dias_enum_permitidos)) {
        $dia_missa = $dias_enum_permitidos[0]; // fallback para o 1º Dia
    }

    if (!empty($titulo) && $edicao_id > 0) {
        if ($missa_id > 0) {
            // Atualizar
            $stmt = $conn->prepare("UPDATE missas SET titulo = ?, dia_missa = ?, horario = ?, leituras = ?, preces = ? WHERE id = ? AND edicao_id = ?");
            $stmt->bind_param("sssssii", $titulo, $dia_missa, $horario, $leituras, $preces, $missa_id, $edicao_id);
        } else {
            // Inserir Nova
            $stmt = $conn->prepare("INSERT INTO missas (edicao_id, titulo, dia_missa, horario, leituras, preces) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $edicao_id, $titulo, $dia_missa, $horario, $leituras, $preces);
        }

        if ($stmt->execute()) {
            $mensagem_sucesso = "Missa salva com sucesso!";
        } else {
            $mensagem_erro = "Erro ao salvar as informações da missa.";
        }
    } else {
        $mensagem_erro = "Preencha o título da missa.";
    }
}

// --- 2. EXCLUIR MISSA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_excluir_missa'])) {
    $missa_id_del = intval($_POST['missa_id'] ?? 0);
    $stmt_del = $conn->prepare("DELETE FROM missas WHERE id = ? AND edicao_id = ?");
    $stmt_del->bind_param("ii", $missa_id_del, $edicao_id);
    if ($stmt_del->execute()) {
        $mensagem_sucesso = "Missa removida com sucesso!";
    } else {
        $mensagem_erro = "Erro ao excluir a missa.";
    }
}

// BUSCA AS MISSAS DA EDIÇÃO
$stmt_missas = $conn->prepare("SELECT * FROM missas WHERE edicao_id = ? ORDER BY dia_missa ASC, horario ASC");
$stmt_missas->bind_param("i", $edicao_id);
$stmt_missas->execute();
$res_missas = $stmt_missas->get_result();

$missas = [];
while ($row = $res_missas->fetch_assoc()) {
    $missas[] = $row;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Missas, Leituras e Preces</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8fafc; color: #0f172a; }
        .card-missa { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.5rem; transition: all 0.2s ease; }
        .card-missa:hover { border-color: #cbd5e1; box-shadow: 0 10px 20px -5px rgba(0,0,0,0.05); }
        .texto-leitura { white-space: pre-wrap; font-size: 1.05rem; line-height: 1.6; background: #f8fafc; padding: 1rem; border-radius: 12px; border: 1px solid #e2e8f0; }
        .badge-dia { background-color: #e2e8f0; color: #334155; font-weight: 600; font-size: 0.8rem; padding: 4px 10px; border-radius: 8px; }
    </style>
</head>
<body>

<main class="container py-4" style="max-width: 1200px;">

    <?php if ($mensagem_sucesso): ?>
        <div class="alert alert-success rounded-4 border-0 shadow-sm alert-dismissible fade show"><?= $mensagem_sucesso ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php if ($mensagem_erro): ?>
        <div class="alert alert-danger rounded-4 border-0 shadow-sm alert-dismissible fade show"><?= $mensagem_erro ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="edicao-detalhes.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="btn btn-sm btn-outline-secondary rounded-3 mb-2">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
            <h3 class="fw-bold m-0">Missas, Leituras e Preces</h3>
        </div>
        <button class="btn btn-dark rounded-3 px-3 py-2 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalMissa" onclick="limparModalMissa()">
            <i class="bi bi-plus-lg"></i> Nova Missa
        </button>
    </div>
  
    
    <!-- LISTA DE MISSAS -->
    <div class="row g-4">
        <?php foreach ($missas as $m): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card-missa h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="badge-dia mb-2 d-inline-block"><?= htmlspecialchars($m['dia_missa']) ?></span>
                                <h5 class="fw-bold text-dark m-0"><?= htmlspecialchars($m['titulo']) ?></h5>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-link text-muted p-0 border-0" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm">
                                    <li>
                                        <button class="dropdown-item small" onclick='editarMissa(<?= json_encode($m) ?>)'>
                                            <i class="bi bi-pencil me-2"></i>Editar
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="" method="POST" onsubmit="return confirm('Deseja excluir esta missa?')">
                                            <input type="hidden" name="acao_excluir_missa" value="1">
                                            <input type="hidden" name="missa_id" value="<?= $m['id'] ?>">
                                            <button type="submit" class="dropdown-item small text-danger"><i class="bi bi-trash me-2"></i>Excluir</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="text-muted small mb-3">
                            <?php if(!empty($m['horario'])): ?>
                                <i class="bi bi-clock me-1"></i> Horário: <?= date('H:i', strtotime($m['horario'])) ?>
                            <?php else: ?>
                                <i class="bi bi-clock me-1"></i> Horário não definido
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- BOTÕES DE LEITURA, PRECES E GERAR PDF -->
                    <div class="d-flex flex-column gap-2 mt-3 pt-3 border-top">
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-dark btn-sm rounded-3 w-100 d-flex align-items-center justify-content-center gap-1" 
                                    onclick='abrirModalLeitura("Leituras - <?= htmlspecialchars($m['titulo']) ?>", <?= json_encode($m['leituras'] ?? "Nenhuma leitura cadastrada.") ?>)'>
                                <i class="bi bi-book"></i> Leituras
                            </button>
                            
                            <button class="btn btn-outline-primary btn-sm rounded-3 w-100 d-flex align-items-center justify-content-center gap-1" 
                                    onclick='abrirModalLeitura("Preces - <?= htmlspecialchars($m['titulo']) ?>", <?= json_encode($m['preces'] ?? "Nenhuma prece cadastrada.") ?>)'>
                                <i class="bi bi-chat-quote"></i> Preces
                            </button>
                        </div>

                        <!-- BOTÃO GERAR PDF -->
                        <a href="gerar-pdf-missa.php?id=<?= $m['id'] ?>" target="_blank" class="btn btn-danger btn-sm rounded-3 w-100 d-flex align-items-center justify-content-center gap-1">
                            <i class="bi bi-file-earmark-pdf"></i> Gerar PDF (Leituras + Preces)
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($missas)): ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="bi bi-journal-x fs-1 d-block mb-2 opacity-50"></i>
                Nenhuma missa cadastrada para esta edição.
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- MODAL PARA CADASTRAR / EDITAR MISSA -->
<div class="modal fade" id="modalMissa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalMissaTitulo">Cadastrar Missa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="acao_salvar_missa" value="1">
                <input type="hidden" name="missa_id" id="missa_id" value="0">

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Título / Celebração</label>
                        <input type="text" name="titulo" id="missa_titulo" class="form-control rounded-3" placeholder="Ex: Missa de Abertura" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Dia do Acampamento (ENUM)</label>
                            <select name="dia_missa" id="missa_dia" class="form-select rounded-3" required>
                                <?php foreach ($dias_enum_permitidos as $opcao_dia): ?>
                                    <option value="<?= $opcao_dia ?>"><?= $opcao_dia ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Horário</label>
                            <input type="time" name="horario" id="missa_horario" class="form-control rounded-3">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium"><i class="bi bi-book me-1"></i> Leituras (Primeira Leitura, Salmo, Evangelho, etc.)</label>
                        <textarea name="leituras" id="missa_leituras" class="form-control rounded-3" rows="5" placeholder="Digite ou cole aqui as leituras da missa..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium"><i class="bi bi-chat-quote me-1"></i> Preces dos Fiéis</label>
                        <textarea name="preces" id="missa_preces" class="form-control rounded-3" rows="4" placeholder="Digite ou cole aqui as preces..."></textarea>
                    </div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark rounded-3 px-4">Salvar Missa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EXIBIÇÃO DE LEITURA / PRECES -->
<div class="modal fade" id="modalExibirTexto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="exibirTitulo">Leitura</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-0">
                <div class="texto-leitura" id="exibirConteudo"></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function limparModalMissa() {
    document.getElementById('modalMissaTitulo').textContent = 'Cadastrar Missa';
    document.getElementById('missa_id').value = '0';
    document.getElementById('missa_titulo').value = '';
    document.getElementById('missa_dia').value = '1º Dia';
    document.getElementById('missa_horario').value = '';
    document.getElementById('missa_leituras').value = '';
    document.getElementById('missa_preces').value = '';
}

function editarMissa(missa) {
    document.getElementById('modalMissaTitulo').textContent = 'Editar Missa';
    document.getElementById('missa_id').value = missa.id;
    document.getElementById('missa_titulo').value = missa.titulo || '';
    document.getElementById('missa_dia').value = missa.dia_missa || '1º Dia';
    document.getElementById('missa_horario').value = missa.horario || '';
    document.getElementById('missa_leituras').value = missa.leituras || '';
    document.getElementById('missa_preces').value = missa.preces || '';
    
    var modal = new bootstrap.Modal(document.getElementById('modalMissa'));
    modal.show();
}

function abrirModalLeitura(titulo, conteudo) {
    document.getElementById('exibirTitulo').textContent = titulo;
    document.getElementById('exibirConteudo').textContent = conteudo || 'Nenhum texto informado.';
    var modal = new bootstrap.Modal(document.getElementById('modalExibirTexto'));
    modal.show();
}
</script>
</body>
</html>
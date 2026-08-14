<?php
// alterar-status-edicao.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$id            = isset($_GET['id']) ? intval($_GET['id']) : 0;
$modalidade_id = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;

if ($id <= 0 || $modalidade_id <= 0) {
    header("Location: dashboard-geral.php?erro=dados_invalidos");
    exit;
}

// Valida permissões
$perfil     = $_SESSION['usuario_perfil'] ?? '';
$permitidos = $_SESSION['modalidades_permitidas'] ?? [];

if ($perfil !== 'COORDENADORA_GERAL' && !in_array($modalidade_id, $permitidos)) {
    header("Location: dashboard-geral.php?erro=sem_permissao");
    exit;
}

// Busca status atual
$stmt_busca = $conn->prepare("SELECT status FROM edicoes WHERE id = ? AND modalidade_id = ?");
$stmt_busca->bind_param("ii", $id, $modalidade_id);
$stmt_busca->execute();
$res = $stmt_busca->get_result()->fetch_assoc();

if ($res) {
    $novo_status = (strtolower($res['status']) === 'ativa') ? 'inativa' : 'ativa';

    $stmt_update = $conn->prepare("UPDATE edicoes SET status = ? WHERE id = ? AND modalidade_id = ?");
    $stmt_update->bind_param("sii", $novo_status, $id, $modalidade_id);
    if ($stmt_update->execute()) {
        header("Location: dashboard-acampamento.php?modalidade_id={$modalidade_id}&sucesso=1");
        exit;
    }
}

header("Location: dashboard-acampamento.php?modalidade_id={$modalidade_id}&erro=falha_status");
exit;
<?php
// equipe-salvar.php
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $modalidade_id    = intval($_POST['modalidade_id']);
    $nome             = trim($_POST['nome']);
    $telefone         = trim($_POST['telefone']);
    $tamanho_camiseta = trim($_POST['tamanho_camiseta']);
    $setor_id         = !empty($_POST['setor_id']) ? intval($_POST['setor_id']) : NULL;
    $funcao           = trim($_POST['funcao']);

    if (!empty($nome) && $modalidade_id > 0) {
        $stmt = $conn->prepare("INSERT INTO voluntarios (modalidade_id, setor_id, nome, telefone, tamanho_camiseta, funcao) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $modalidade_id, $setor_id, $nome, $telefone, $tamanho_camiseta, $funcao);
        
        if ($stmt->execute()) {
            header("Location: equipes.php?modalidade_id=" . $modalidade_id . "&sucesso=1");
            exit;
        }
    }
}

// Em caso de falha ou acesso direto
header("Location: dashboard-geral.php");
exit;
<?php
// logout.php

// 1. Inicia ou recupera a sessão existente
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Limpa todas as variáveis armazenadas na sessão
$_SESSION = array();

// 3. Se for utilizado cookie de sessão, remove-o invalidando a expiração no navegador
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 4. Destrói o arquivo da sessão no servidor
session_destroy();

// 5. Redireciona o usuário para a página de login
header("Location: login.php");
exit;
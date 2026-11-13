<?php

session_start();

// Limpa os dados da sessão
$_SESSION = [];

// Destrói a sessão
session_destroy();

// Retorna para a tela de login
header("Location: index.php");
exit;
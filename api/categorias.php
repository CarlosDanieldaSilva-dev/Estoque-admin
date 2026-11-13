
<?php

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["Controle de Estoque"])) {
    http_response_code(401);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Acesso não autorizado."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

require_once __DIR__ . '/../config.php';

try {

    // Consultar a procedure de resumo por categoria
    $consulta = $pdo->query("CALL sp_resumo_categorias()");

    $categorias = $consulta->fetchAll(PDO::FETCH_ASSOC);

    $consulta->closeCursor();

    // Converter os valores numéricos
    foreach ($categorias as &$categoria) {
        $categoria["total_produtos"] = (int) $categoria["total_produtos"];
        $categoria["quantidade_total_estoque"] = (int) $categoria["quantidade_total_estoque"];
        $categoria["produtos_estoque_baixo"] = (int) $categoria["produtos_estoque_baixo"];
        $categoria["produtos_zerados"] = (int) $categoria["produtos_zerados"];
    }

    unset($categoria);

    echo json_encode([
        "sucesso" => true,
        "dados" => $categorias
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $erro) {

    http_response_code(500);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Erro ao consultar o resumo das categorias."
    ], JSON_UNESCAPED_UNICODE);
}

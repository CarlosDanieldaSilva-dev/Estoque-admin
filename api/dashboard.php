
<?php

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está autenticado
if (!isset($_SESSION["Controle de Estoque"])) {
    http_response_code(401);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Acesso não autorizado."
    ]);

    exit;
}

require_once __DIR__ . '/../config.php';

try {

    // Executar a procedure do dashboard
    $consulta = $pdo->query("CALL sp_indicadores_dashboard()");

    $indicadores = $consulta->fetch(PDO::FETCH_ASSOC);

    // Liberar o resultado da procedure
    $consulta->closeCursor();

    if (!$indicadores) {
        throw new Exception("Não foi possível obter os indicadores.");
    }

    echo json_encode([
        "sucesso" => true,
        "dados" => [
            "total_produtos" => (int) $indicadores["total_produtos"],
            "quantidade_total_estoque" => (int) $indicadores["quantidade_total_estoque"],
            "produtos_estoque_baixo" => (int) $indicadores["produtos_estoque_baixo"],
            "produtos_sem_estoque" => (int) $indicadores["produtos_sem_estoque"]
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $erro) {

    http_response_code(500);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Erro ao consultar os indicadores."
    ], JSON_UNESCAPED_UNICODE);

}

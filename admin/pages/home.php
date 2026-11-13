
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pdo)) {
    require_once __DIR__ . '/../../config.php';
}

// Função para proteger a exibição de dados
function escaparHome( mixed $valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

// ========================================
// 1. TOTAL DE PRODUTOS
// ========================================

$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM produtos
");

$totalProdutos = (int) $stmt->fetchColumn();


// ========================================
// 2. TOTAL DE CATEGORIAS
// ========================================

$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM categorias
");

$totalCategorias = (int) $stmt->fetchColumn();


// ========================================
// 3. PRODUTOS COM ESTOQUE BAIXO
// ========================================

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM produtos
    WHERE quantidade_estoque > 0
    AND quantidade_estoque <= estoque_minimo
");

$estoqueBaixo = (int) $stmt->fetchColumn();


// ========================================
// 4. PRODUTOS SEM ESTOQUE
// ========================================

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM produtos
    WHERE quantidade_estoque <= 0
");

$semEstoque = (int) $stmt->fetchColumn();


// ========================================
// 5. LISTA DE PRODUTOS COM ESTOQUE BAIXO
// ========================================

$stmt = $pdo->query("
    SELECT
        p.nome,
        p.quantidade_estoque,
        p.estoque_minimo,
        c.nome_categoria
    FROM produtos p
    INNER JOIN categorias c
        ON p.id_categoria = c.id_categoria
    WHERE p.quantidade_estoque <= p.estoque_minimo
    ORDER BY p.quantidade_estoque ASC
    LIMIT 5
");

$produtosBaixos = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ========================================
// 6. ÚLTIMAS MOVIMENTAÇÕES
// ========================================

$stmt = $pdo->query("
    SELECT
        m.tipo,
        m.quantidade,
        m.data_movimentacao,
        p.nome AS nome_produto,
        u.nome AS nome_usuario
    FROM movimentacoes_estoque m
    INNER JOIN produtos p
        ON m.id_produto = p.id_produto
    INNER JOIN usuario u
        ON m.id_usuario = u.id_usuario
    ORDER BY m.data_movimentacao DESC
    LIMIT 5
");

$movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container-fluid">

    <!-- TÍTULO -->
    <div class="mb-4">
        <h3 class="fw-bold">Painel inicial</h3>
        <p class="text-muted mb-0">
            Visão geral do controle de estoque.
        </p>
    </div>


    <!-- INDICADORES -->
    <div class="row g-3 mb-4">

        <!-- TOTAL DE PRODUTOS -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted mb-1">
                                Total de produtos
                            </p>

                            <h3 class="fw-bold mb-0" id="indicador-total-produtos">
                                <?= $totalProdutos ?>
                            </h3>
                        </div>

                        <div class="fs-2 text-primary">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- TOTAL DE CATEGORIAS -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted mb-1">
                                Categorias
                            </p>

                            <h3 class="fw-bold mb-0">
                                <?= $totalCategorias ?>
                            </h3>
                        </div>

                        <div class="fs-2 text-info">
                            <i class="bi bi-tags"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- UNIDADES EM ESTOQUE -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted mb-1">
                                Unidades em estoque
                            </p>

                            <h3 class="fw-bold mb-0" id="indicador-total-unidades">
                                0
                            </h3>
                        </div>

                        <div class="fs-2 text-success">
                            <i class="bi bi-boxes"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- ESTOQUE BAIXO -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted mb-1">
                                Estoque baixo
                            </p>

                            <h3 class="fw-bold mb-0 text-warning" id="indicador-estoque-baixo">
                                <?= $estoqueBaixo ?>
                            </h3>
                        </div>

                        <div class="fs-2 text-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- SEM ESTOQUE -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted mb-1">
                                Sem estoque
                            </p>

                            <h3 class="fw-bold mb-0 text-danger" id="indicador-sem-estoque">
                                <?= $semEstoque ?>
                            </h3>
                        </div>

                        <div class="fs-2 text-danger">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- GRÁFICO DE ESTOQUE POR CATEGORIA -->
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Estoque por categoria</h5>
        </div>

        <div style="position: relative; height: 300px; width: 100%;">
            <canvas id="grafico-categorias"></canvas>
        </div>
    </div>

    <!-- TABELAS -->
    <div class="row g-3">

        <!-- PRODUTOS COM ESTOQUE BAIXO -->
        <div class="col-12 col-xl-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-exclamation-circle text-warning me-2"></i>
                        Produtos que precisam de reposição
                    </h5>
                </div>

                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table table-hover align-middle mb-0">

                            <thead class="table-light">
                                <tr>
                                    <th>Produto</th>
                                    <th>Categoria</th>
                                    <th>Quantidade</th>
                                    <th>Mínimo</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if (count($produtosBaixos) > 0): ?>

                                    <?php foreach ($produtosBaixos as $produto): ?>

                                        <tr>
                                            <td>
                                                <?= escaparHome($produto['nome']) ?>
                                            </td>

                                            <td>
                                                <?= escaparHome($produto['nome_categoria']) ?>
                                            </td>

                                            <td>
                                                <span class="badge bg-warning text-dark">
                                                    <?= (int) $produto['quantidade_estoque'] ?>
                                                </span>
                                            </td>

                                            <td>
                                                <?= (int) $produto['estoque_minimo'] ?>
                                            </td>
                                        </tr>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
                                            Nenhum produto precisa de reposição.
                                        </td>
                                    </tr>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>


        <!-- ÚLTIMAS MOVIMENTAÇÕES -->
        <div class="col-12 col-xl-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-arrow-left-right text-primary me-2"></i>
                        Últimas movimentações
                    </h5>
                </div>

                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table table-hover align-middle mb-0">

                            <thead class="table-light">
                                <tr>
                                    <th>Produto</th>
                                    <th>Tipo</th>
                                    <th>Quantidade</th>
                                    <th>Usuário</th>
                                    <th>Data</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if (count($movimentacoes) > 0): ?>

                                    <?php foreach ($movimentacoes as $mov): ?>

                                        <tr>

                                            <td>
                                                <?= escaparHome($mov['nome_produto']) ?>
                                            </td>

                                            <td>

                                                <?php if (strtolower($mov['tipo']) === 'entrada'): ?>

                                                    <span class="badge bg-success">
                                                        Entrada
                                                    </span>

                                                <?php else: ?>

                                                    <span class="badge bg-danger">
                                                        Saída
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                            <td>
                                                <?= (int) $mov['quantidade'] ?>
                                            </td>

                                            <td>
                                                <?= escaparHome($mov['nome_usuario']) ?>
                                            </td>

                                            <td>
                                                <?= date(
                                                    'd/m/Y H:i',
                                                    strtotime($mov['data_movimentacao'])
                                                ) ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            Nenhuma movimentação registrada.
                                        </td>
                                    </tr>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
<script src="../js/dashboard.js"></script>

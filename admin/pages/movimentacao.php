


<?php

if (!isset($pdo)) {
    require_once __DIR__ . '/../../config.php';
}

$mensagem = '';
$tipoMensagem = '';

// ==========================================
// PROCESSAR NOVA MOVIMENTAÇÃO
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $idProduto = filter_input(INPUT_POST, 'id_produto', FILTER_VALIDATE_INT);
    $tipo = $_POST['tipo'] ?? '';
    $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_INT);
    $observacao = trim($_POST['observacao'] ?? '');

    $idUsuario = $_SESSION["Controle de Estoque"]["id_usuario"] ?? null;

    if (!$idProduto || !$quantidade || $quantidade <= 0) {

        $mensagem = "Selecione um produto e informe uma quantidade válida.";
        $tipoMensagem = "danger";

    } elseif (!in_array($tipo, ['Entrada', 'Saída'], true)) {

        $mensagem = "Selecione um tipo de movimentação válido.";
        $tipoMensagem = "danger";

    } elseif (!$idUsuario) {

        $mensagem = "Não foi possível identificar o usuário responsável.";
        $tipoMensagem = "danger";

    } else {

        try {

            // Iniciar transação
            $pdo->beginTransaction();

            // Buscar produto e bloquear a linha
            $sqlProduto = "SELECT id_produto, quantidade_estoque
                           FROM produtos
                           WHERE id_produto = :id_produto
                           FOR UPDATE";

            $consultaProduto = $pdo->prepare($sqlProduto);

            $consultaProduto->execute([
                ':id_produto' => $idProduto
            ]);

            $produtoSelecionado = $consultaProduto->fetch(PDO::FETCH_ASSOC);

            if (!$produtoSelecionado) {
                throw new Exception("Produto não encontrado.");
            }

            $estoqueAtual = (int) $produtoSelecionado['quantidade_estoque'];

            // Calcular novo estoque
            if ($tipo === 'Entrada') {

                $novoEstoque = $estoqueAtual + $quantidade;

            } else {

                if ($quantidade > $estoqueAtual) {
                    throw new Exception(
                        "Estoque insuficiente. Quantidade disponível: "
                        . $estoqueAtual
                    );
                }

                $novoEstoque = $estoqueAtual - $quantidade;
            }

            // Atualizar estoque
            $sqlAtualizar = "UPDATE produtos
                             SET quantidade_estoque = :quantidade
                             WHERE id_produto = :id_produto";

            $atualizar = $pdo->prepare($sqlAtualizar);

            $atualizar->execute([
                ':quantidade' => $novoEstoque,
                ':id_produto' => $idProduto
            ]);

            // Registrar movimentação
            $sqlMovimentacao = "INSERT INTO movimentacoes_estoque
                                (id_produto, id_usuario, tipo, quantidade,
                                 data_movimentacao, observacao)
                                VALUES
                                (:id_produto, :id_usuario, :tipo, :quantidade,
                                 NOW(), :observacao)";

            $registrar = $pdo->prepare($sqlMovimentacao);

            $registrar->execute([
                ':id_produto' => $idProduto,
                ':id_usuario' => $idUsuario,
                ':tipo' => $tipo,
                ':quantidade' => $quantidade,
                ':observacao' => $observacao
            ]);

            // Confirmar operação
            $pdo->commit();

            $mensagem = "Movimentação registrada com sucesso! "
                      . "Novo estoque: " . $novoEstoque . " unidades.";

            $tipoMensagem = "success";

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $mensagem = "Ocorreu um erro no banco de dados ao registrar a movimentação.";
            $tipoMensagem = "danger";

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $mensagem = $e->getMessage();
            $tipoMensagem = "danger";
        }
    }
}

// ==========================================
// BUSCAR PRODUTOS
// ==========================================

$sql = "SELECT id_produto, nome, quantidade_estoque
        FROM produtos
        ORDER BY nome ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute();

$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// BUSCAR HISTÓRICO DE MOVIMENTAÇÕES
// ==========================================

$sqlHistorico = "SELECT
                    m.id_movimentacao,
                    p.nome AS produto,
                    u.nome AS usuario,
                    m.tipo,
                    m.quantidade,
                    m.data_movimentacao,
                    m.observacao
                 FROM movimentacoes_estoque m
                 INNER JOIN produtos p
                    ON m.id_produto = p.id_produto
                 INNER JOIN usuario u
                    ON m.id_usuario = u.id_usuario
                 ORDER BY m.data_movimentacao DESC";

$stmtHistorico = $pdo->prepare($sqlHistorico);
$stmtHistorico->execute();

$historico = $stmtHistorico->fetchAll(PDO::FETCH_ASSOC);

?>

<!-- ====================================== -->
<!-- FORMULÁRIO DE MOVIMENTAÇÃO -->
<!-- ====================================== -->

<div class="container-fluid">

    <div class="mb-4">
        <h2>Movimentação de Estoque</h2>

        <p class="text-muted">
            Registre entradas e saídas dos produtos.
        </p>
    </div>

    <div class="card shadow-sm border-0">

        <div class="card-header bg-white py-3">
            <h5 class="mb-0">
                <i class="bi bi-arrow-left-right me-2"></i>
                Nova movimentação
            </h5>
        </div>

        <div class="card-body">

            <?php if ($mensagem !== ''): ?>

                <div class="alert alert-<?= $tipoMensagem ?>">
                    <?= htmlspecialchars($mensagem) ?>
                </div>

            <?php endif; ?>

            <?php if (count($produtos) > 0): ?>

                <form method="POST" action="index.php?pagina=movimentacao">

                    <div class="row">

                        <!-- Produto -->
                        <div class="col-md-6 mb-3">

                            <label for="id_produto" class="form-label">
                                Produto
                            </label>

                            <select name="id_produto"
                                    id="id_produto"
                                    class="form-select"
                                    required>

                                <option value="">
                                    Selecione um produto
                                </option>

                                <?php foreach ($produtos as $produto): ?>

                                    <option value="<?= $produto['id_produto'] ?>">
                                        <?= htmlspecialchars($produto['nome']) ?>
                                        (Estoque: <?= $produto['quantidade_estoque'] ?>)
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <!-- Tipo de movimentação -->
                        <div class="col-md-6 mb-3">

                            <label for="tipo" class="form-label">
                                Tipo de movimentação
                            </label>

                            <select name="tipo"
                                    id="tipo"
                                    class="form-select"
                                    required>

                                <option value="">
                                    Selecione o tipo
                                </option>

                                <option value="Entrada">
                                    Entrada
                                </option>

                                <option value="Saída">
                                    Saída
                                </option>

                            </select>

                        </div>

                        <!-- Quantidade -->
                        <div class="col-md-6 mb-3">

                            <label for="quantidade" class="form-label">
                                Quantidade
                            </label>

                            <input type="number"
                                   name="quantidade"
                                   id="quantidade"
                                   class="form-control"
                                   min="1"
                                   step="1"
                                   placeholder="Digite a quantidade"
                                   required>

                        </div>

                        <!-- Observação -->
                        <div class="col-md-12 mb-3">

                            <label for="observacao" class="form-label">
                                Observação
                            </label>

                            <textarea name="observacao"
                                      id="observacao"
                                      class="form-control"
                                      rows="3"
                                      placeholder="Motivo da movimentação (opcional)"></textarea>

                        </div>

                    </div>

                    <!-- Botões -->
                    <div class="d-flex justify-content-end gap-2">

                        <button type="reset"
                                class="btn btn-outline-secondary">

                            <i class="bi bi-eraser me-1"></i>
                            Limpar

                        </button>

                        <button type="submit"
                                class="btn btn-primary">

                            <i class="bi bi-check-circle me-1"></i>
                            Registrar movimentação

                        </button>

                    </div>

                </form>

            <?php else: ?>

                <div class="alert alert-warning mb-0">
                    Nenhum produto cadastrado. Cadastre um produto
                    antes de realizar uma movimentação.
                </div>

            <?php endif; ?>

        </div>
    </div>

    <!-- ====================================== -->
    <!-- HISTÓRICO DE MOVIMENTAÇÕES -->
    <!-- ====================================== -->

    <div class="card shadow-sm border-0 mt-4">

        <div class="card-header bg-white py-3">

            <h5 class="mb-0">
                <i class="bi bi-clock-history me-2"></i>
                Histórico de movimentações
            </h5>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-light">

                        <tr>
                            <th>Produto</th>
                            <th>Tipo</th>
                            <th>Quantidade</th>
                            <th>Data</th>
                            <th>Responsável</th>
                            <th>Observação</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (count($historico) > 0): ?>

                            <?php foreach ($historico as $movimento): ?>

                                <tr>

                                    <!-- Produto -->
                                    <td>
                                        <?= htmlspecialchars($movimento['produto']) ?>
                                    </td>

                                    <!-- Tipo -->
                                    <td>

                                        <?php if ($movimento['tipo'] === 'Entrada'): ?>

                                            <span class="badge bg-success">
                                                <i class="bi bi-box-arrow-in-down me-1"></i>
                                                Entrada
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-danger">
                                                <i class="bi bi-box-arrow-up me-1"></i>
                                                Saída
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <!-- Quantidade -->
                                    <td>
                                        <?= (int) $movimento['quantidade'] ?>
                                    </td>

                                    <!-- Data -->
                                    <td>
                                        <?= date(
                                            'd/m/Y H:i',
                                            strtotime($movimento['data_movimentacao'])
                                        ) ?>
                                    </td>

                                    <!-- Responsável -->
                                    <td>
                                        <?= htmlspecialchars($movimento['usuario']) ?>
                                    </td>

                                    <!-- Observação -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $movimento['observacao'] ?: '-'
                                        ) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="6"
                                    class="text-center text-muted py-4">

                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>

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


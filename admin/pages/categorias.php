
<?php

// Verificar sessão
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Conexão com o banco
if (!isset($pdo)) {
    require_once __DIR__ . '/../../config.php';
}

// Mensagens
$mensagem = '';
$tipoMensagem = '';

// Proteção CSRF
if (empty($_SESSION['csrf_categorias'])) {
    $_SESSION['csrf_categorias'] = bin2hex(random_bytes(32));
}

$tokenCSRF = $_SESSION['csrf_categorias'];

// Função para proteger a exibição de textos
function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tokenRecebido = $_POST['csrf_token'] ?? '';

    if (!hash_equals($tokenCSRF, $tokenRecebido)) {

        $mensagem = 'Solicitação inválida. Atualize a página e tente novamente.';
        $tipoMensagem = 'danger';

    } else {

        $acao = $_POST['acao'] ?? '';

        try {

            // CADASTRAR CATEGORIA
            if ($acao === 'cadastrar') {

                $nome = trim($_POST['nome_categoria'] ?? '');

                if ($nome === '') {

                    throw new Exception('Informe o nome da categoria.');

                }

                if (mb_strlen($nome) > 100) {

                    throw new Exception('O nome deve ter no máximo 100 caracteres.');

                }

                $sql = "INSERT INTO categorias (nome_categoria)
                        VALUES (:nome)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':nome' => $nome
                ]);

                $mensagem = 'Categoria cadastrada com sucesso!';
                $tipoMensagem = 'success';
            }

            // EDITAR CATEGORIA
            elseif ($acao === 'editar') {

                $id = filter_var(
                    $_POST['id_categoria'] ?? '',
                    FILTER_VALIDATE_INT
                );

                $nome = trim($_POST['nome_categoria'] ?? '');

                if (!$id || $id <= 0) {
                    throw new Exception('Categoria inválida.');
                }

                if ($nome === '') {
                    throw new Exception('Informe o nome da categoria.');
                }

                if (mb_strlen($nome) > 100) {
                    throw new Exception('O nome deve ter no máximo 100 caracteres.');
                }

                $sql = "UPDATE categorias
                        SET nome_categoria = :nome
                        WHERE id_categoria = :id";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    ':nome' => $nome,
                    ':id' => $id
                ]);

                $mensagem = 'Categoria atualizada com sucesso!';
                $tipoMensagem = 'success';
            }

            // EXCLUIR CATEGORIA
            elseif ($acao === 'excluir') {

                $id = filter_var(
                    $_POST['id_categoria'] ?? '',
                    FILTER_VALIDATE_INT
                );

                if (!$id || $id <= 0) {
                    throw new Exception('Categoria inválida.');
                }

                $pdo->beginTransaction();

                // Verificar se a categoria existe
                $stmt = $pdo->prepare(
                    "SELECT id_categoria
                     FROM categorias
                     WHERE id_categoria = :id
                     FOR UPDATE"
                );

                $stmt->execute([':id' => $id]);

                $categoria = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$categoria) {
                    throw new Exception('Categoria não encontrada.');
                }

                // Verificar produtos vinculados
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM produtos
                     WHERE id_categoria = :id"
                );

                $stmt->execute([':id' => $id]);

                $totalProdutos = (int) $stmt->fetchColumn();

                if ($totalProdutos > 0) {

                    throw new Exception(
                        "Não é possível excluir esta categoria. 
                        Existem {$totalProdutos} produto(s) vinculado(s) a ela."
                    );

                }

                // Excluir categoria
                $stmt = $pdo->prepare(
                    "DELETE FROM categorias
                     WHERE id_categoria = :id"
                );

                $stmt->execute([':id' => $id]);

                $pdo->commit();

                $mensagem = 'Categoria excluída com sucesso!';
                $tipoMensagem = 'success';
            }

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $mensagem = 'Erro ao processar a operação. Verifique os dados e tente novamente.';
            $tipoMensagem = 'danger';

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $mensagem = $e->getMessage();
            $tipoMensagem = 'danger';
        }
    }
}

// BUSCAR CATEGORIAS
$busca = trim($_GET['busca'] ?? '');

try {

    if ($busca !== '') {

        $sql = "SELECT id_categoria, nome_categoria
                FROM categorias
                WHERE nome_categoria LIKE :busca
                ORDER BY nome_categoria ASC";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':busca' => '%' . $busca . '%'
        ]);

    } else {

        $sql = "SELECT id_categoria, nome_categoria
                FROM categorias
                ORDER BY nome_categoria ASC";

        $stmt = $pdo->query($sql);
    }

    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $categorias = [];
    $mensagem = 'Não foi possível carregar as categorias.';
    $tipoMensagem = 'danger';
}

?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h3 class="fw-bold">Categorias</h3>
            <p class="text-muted">
                Gerencie as categorias dos produtos do estoque.
            </p>
        </div>

    </div>

    <!-- MENSAGEM -->
    <?php if ($mensagem !== ''): ?>

        <div class="alert alert-<?php echo escapar($tipoMensagem); ?> alert-dismissible fade show"
             role="alert">

            <?php echo escapar($mensagem); ?>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
            </button>

        </div>

    <?php endif; ?>


    <!-- CADASTRAR CATEGORIA -->
    <div class="card shadow-sm border-0 mb-4">

        <div class="card-header bg-white py-3">

            <h5 class="mb-0 fw-bold">
                Cadastrar categoria
            </h5>

        </div>

        <div class="card-body">

            <form method="POST">

                <input type="hidden"
                       name="csrf_token"
                       value="<?php echo escapar($tokenCSRF); ?>">

                <input type="hidden"
                       name="acao"
                       value="cadastrar">

                <div class="row align-items-end">

                    <div class="col-md-9 mb-3">

                        <label class="form-label">
                            Nome da categoria
                        </label>

                        <input
                            type="text"
                            name="nome_categoria"
                            class="form-control"
                            placeholder="Digite o nome da categoria"
                            maxlength="100"
                            required
                        >

                    </div>

                    <div class="col-md-3 mb-3">

                        <button type="submit"
                                class="btn btn-primary w-100">

                            <i class="bi bi-plus-circle me-1"></i>
                            Cadastrar

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>


    <!-- LISTAGEM E BUSCA -->
    <div class="card shadow-sm border-0">

        <div class="card-header bg-white py-3">

            <div class="row align-items-center">

                <div class="col-md-6">

                    <h5 class="mb-0 fw-bold">
                        Categorias cadastradas
                    </h5>

                </div>

                <div class="col-md-6">

                    <form method="GET" action="index.php" class="d-flex">

                        <input
                            type="hidden"
                            name="pagina"
                            value="categorias"
                        >

                        <input
                            type="text"
                            name="busca"
                            class="form-control me-2"
                            placeholder="Pesquisar categoria..."
                            value="<?php echo escapar($busca); ?>"
                        >

                        <button type="submit" class="btn btn-outline-primary">
                            <i class="bi bi-search"></i>
                        </button>

                    </form>

                </div>

            </div>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-light">

                        <tr>

                            <th>ID</th>
                            <th>Nome da categoria</th>
                            <th class="text-center">Ações</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (count($categorias) > 0): ?>

                            <?php foreach ($categorias as $categoria): ?>

                                <tr>

                                    <td>
                                        <?php echo escapar($categoria['id_categoria']); ?>
                                    </td>

                                    <td>

                                        <form method="POST"
                                              class="d-flex gap-2">

                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?php echo escapar($tokenCSRF); ?>">

                                            <input type="hidden"
                                                   name="acao"
                                                   value="editar">

                                            <input type="hidden"
                                                   name="id_categoria"
                                                   value="<?php echo escapar($categoria['id_categoria']); ?>">

                                            <input
                                                type="text"
                                                name="nome_categoria"
                                                class="form-control form-control-sm"
                                                value="<?php echo escapar($categoria['nome_categoria']); ?>"
                                                maxlength="100"
                                                required
                                            >

                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="Salvar alteração">

                                                <i class="bi bi-check-lg"></i>

                                            </button>

                                        </form>

                                    </td>

                                    <td class="text-center">

                                        <form method="POST"
                                              class="d-inline"
                                              onsubmit="return confirm('Deseja realmente excluir esta categoria?');">

                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?php echo escapar($tokenCSRF); ?>">

                                            <input type="hidden"
                                                   name="acao"
                                                   value="excluir">

                                            <input type="hidden"
                                                   name="id_categoria"
                                                   value="<?php echo escapar($categoria['id_categoria']); ?>">

                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-danger"
                                                    title="Excluir categoria">

                                                <i class="bi bi-trash"></i>

                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="3"
                                    class="text-center text-muted py-4">

                                    Nenhuma categoria encontrada.

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <div class="text-muted small">

                Total de categorias encontradas:
                <strong><?php echo count($categorias); ?></strong>

            </div>

        </div>

    </div>

</div>

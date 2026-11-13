
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pdo)) {
    require_once __DIR__ . '/../../config.php';
}

// Caminho físico para salvar as imagens
$pastaUploads = dirname(__DIR__, 2) . '/arquivos/produtos';

// Caminho utilizado pelo navegador
$caminhoImagens = '../arquivos/';

// Criar pasta caso não exista
if (!is_dir($pastaUploads)) {
    mkdir($pastaUploads, 0755, true);
}

// Função para proteger a exibição de textos
function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

// Mensagens
$mensagem = $_SESSION['mensagem_produtos'] ?? '';
$tipoMensagem = $_SESSION['tipo_mensagem_produtos'] ?? 'success';

unset(
    $_SESSION['mensagem_produtos'],
    $_SESSION['tipo_mensagem_produtos']
);

// Redirecionamento após operações
function redirecionarProdutos()
{
    header('Location: index.php?pagina=produtos');
    exit;
}

// Excluir imagem do servidor
function excluirImagemProduto($imagem, $pastaUploads)
{
    if (empty($imagem)) {
        return;
    }

    // Apenas imagens dentro da pasta de produtos
    $nomeArquivo = basename($imagem);
    $caminho = $pastaUploads . '/' . $nomeArquivo;

    if (is_file($caminho)) {
        unlink($caminho);
    }
}

// Processamento dos formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acao = $_POST['acao'] ?? '';

    try {

        // CADASTRAR OU EDITAR PRODUTO
        if ($acao === 'salvar') {

            $idProduto = filter_input(
                INPUT_POST,
                'id_produto',
                FILTER_VALIDATE_INT
            );

            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');

            $quantidade = filter_input(
                INPUT_POST,
                'quantidade_estoque',
                FILTER_VALIDATE_INT
            );

            $estoqueMinimo = filter_input(
                INPUT_POST,
                'estoque_minimo',
                FILTER_VALIDATE_INT
            );

            $idCategoria = filter_input(
                INPUT_POST,
                'id_categoria',
                FILTER_VALIDATE_INT
            );

            if (
                $nome === '' ||
                $quantidade === false ||
                $quantidade === null ||
                $quantidade < 0 ||
                $estoqueMinimo === false ||
                $estoqueMinimo === null ||
                $estoqueMinimo < 0 ||
                !$idCategoria
            ) {
                throw new Exception(
                    'Preencha corretamente todos os campos obrigatórios.'
                );
            }

            if (mb_strlen($nome) > 150) {
                throw new Exception(
                    'O nome do produto deve ter no máximo 150 caracteres.'
                );
            }

            // Verificar categoria
            $stmtCategoria = $pdo->prepare(
                "SELECT id_categoria
                 FROM categorias
                 WHERE id_categoria = :id"
            );

            $stmtCategoria->execute([
                ':id' => $idCategoria
            ]);

            if (!$stmtCategoria->fetch()) {
                throw new Exception('Categoria inválida.');
            }

            // Buscar imagem atual na edição
            $imagemAtual = null;

            if ($idProduto) {

                $stmtAtual = $pdo->prepare(
                    "SELECT imagem
                     FROM produtos
                     WHERE id_produto = :id"
                );

                $stmtAtual->execute([
                    ':id' => $idProduto
                ]);

                $produtoAtual = $stmtAtual->fetch(PDO::FETCH_ASSOC);

                if (!$produtoAtual) {
                    throw new Exception('Produto não encontrado.');
                }

                $imagemAtual = $produtoAtual['imagem'];
            }

            $imagem = $imagemAtual;

            // PROCESSAR UPLOAD DA IMAGEM
            if (
                isset($_FILES['imagem']) &&
                $_FILES['imagem']['error'] !== UPLOAD_ERR_NO_FILE
            ) {

                if ($_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Erro ao enviar a imagem.');
                }

                if ($_FILES['imagem']['size'] > 5 * 1024 * 1024) {
                    throw new Exception(
                        'A imagem deve ter no máximo 5 MB.'
                    );
                }

                $tiposPermitidos = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                $finfo = new finfo(FILEINFO_MIME_TYPE);

                $tipoArquivo = $finfo->file(
                    $_FILES['imagem']['tmp_name']
                );

                if (!isset($tiposPermitidos[$tipoArquivo])) {
                    throw new Exception(
                        'Formato inválido. Utilize JPG, PNG ou WEBP.'
                    );
                }

                if (
                    getimagesize($_FILES['imagem']['tmp_name']) === false
                ) {
                    throw new Exception('O arquivo enviado não é uma imagem válida.');
                }

                $extensao = $tiposPermitidos[$tipoArquivo];

                $nomeArquivo = bin2hex(random_bytes(16))
                    . '.' . $extensao;

                $destino = $pastaUploads . '/' . $nomeArquivo;

                if (!move_uploaded_file(
                    $_FILES['imagem']['tmp_name'],
                    $destino
                )) {
                    throw new Exception(
                        'Não foi possível salvar a imagem.'
                    );
                }

                // Remover imagem anterior após salvar a nova
                excluirImagemProduto($imagemAtual, $pastaUploads);

                $imagem = 'produtos/' . $nomeArquivo;
            }

            // EDITAR PRODUTO
            if ($idProduto) {

                $sql = "UPDATE produtos SET
                            nome = :nome,
                            descricao = :descricao,
                            quantidade_estoque = :quantidade,
                            estoque_minimo = :estoque_minimo,
                            id_categoria = :categoria,
                            imagem = :imagem
                        WHERE id_produto = :id";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    ':nome' => $nome,
                    ':descricao' => $descricao,
                    ':quantidade' => $quantidade,
                    ':estoque_minimo' => $estoqueMinimo,
                    ':categoria' => $idCategoria,
                    ':imagem' => $imagem,
                    ':id' => $idProduto
                ]);

                $_SESSION['mensagem_produtos'] =
                    'Produto atualizado com sucesso!';

            } else {

                // CADASTRAR PRODUTO
                $sql = "INSERT INTO produtos (
                            nome,
                            descricao,
                            quantidade_estoque,
                            estoque_minimo,
                            id_categoria,
                            imagem
                        ) VALUES (
                            :nome,
                            :descricao,
                            :quantidade,
                            :estoque_minimo,
                            :categoria,
                            :imagem
                        )";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    ':nome' => $nome,
                    ':descricao' => $descricao,
                    ':quantidade' => $quantidade,
                    ':estoque_minimo' => $estoqueMinimo,
                    ':categoria' => $idCategoria,
                    ':imagem' => $imagem
                ]);

                $_SESSION['mensagem_produtos'] =
                    'Produto cadastrado com sucesso!';
            }

            $_SESSION['tipo_mensagem_produtos'] = 'success';

            redirecionarProdutos();
        }

        // EXCLUIR PRODUTO
        if ($acao === 'excluir') {

            $idProduto = filter_input(
                INPUT_POST,
                'id_produto',
                FILTER_VALIDATE_INT
            );

            if (!$idProduto) {
                throw new Exception('Produto inválido.');
            }

            $stmt = $pdo->prepare(
                "SELECT imagem
                 FROM produtos
                 WHERE id_produto = :id"
            );

            $stmt->execute([
                ':id' => $idProduto
            ]);

            $produto = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$produto) {
                throw new Exception('Produto não encontrado.');
            }

            $stmt = $pdo->prepare(
                "DELETE FROM produtos
                 WHERE id_produto = :id"
            );

            $stmt->execute([
                ':id' => $idProduto
            ]);

            excluirImagemProduto(
                $produto['imagem'],
                $pastaUploads
            );

            $_SESSION['mensagem_produtos'] =
                'Produto excluído com sucesso!';

            $_SESSION['tipo_mensagem_produtos'] = 'success';

            redirecionarProdutos();
        }

    } catch (PDOException $e) {

        $_SESSION['mensagem_produtos'] =
            'Não foi possível realizar a operação. Verifique se o produto possui movimentações vinculadas.';

        $_SESSION['tipo_mensagem_produtos'] = 'danger';

        redirecionarProdutos();

    } catch (Exception $e) {

        $_SESSION['mensagem_produtos'] = $e->getMessage();
        $_SESSION['tipo_mensagem_produtos'] = 'danger';

        redirecionarProdutos();
    }
}

// BUSCAR CATEGORIAS
$stmtCategorias = $pdo->query(
    "SELECT id_categoria, nome_categoria
     FROM categorias
     ORDER BY nome_categoria ASC"
);

$categorias = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC);

// PESQUISA E FILTRO
$pesquisa = trim($_GET['pesquisa'] ?? '');

$filtroCategoria = filter_input(
    INPUT_GET,
    'categoria',
    FILTER_VALIDATE_INT
);

$sql = "SELECT
            p.id_produto,
            p.nome,
            p.descricao,
            p.quantidade_estoque,
            p.estoque_minimo,
            p.data_cadastro,
            p.imagem,
            p.id_categoria,
            c.nome_categoria
        FROM produtos p
        INNER JOIN categorias c
            ON p.id_categoria = c.id_categoria
        WHERE 1 = 1";

$parametros = [];

if ($pesquisa !== '') {
    $sql .= " AND p.nome LIKE :pesquisa";
    $parametros[':pesquisa'] = '%' . $pesquisa . '%';
}

if ($filtroCategoria) {
    $sql .= " AND p.id_categoria = :categoria";
    $parametros[':categoria'] = $filtroCategoria;
}

$sql .= " ORDER BY p.nome ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);

$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// BUSCAR PRODUTO PARA EDIÇÃO
$produtoEditar = null;

if (isset($_GET['editar'])) {

    $idEditar = filter_input(
        INPUT_GET,
        'editar',
        FILTER_VALIDATE_INT
    );

    if ($idEditar) {

        $stmt = $pdo->prepare(
            "SELECT *
             FROM produtos
             WHERE id_produto = :id"
        );

        $stmt->execute([
            ':id' => $idEditar
        ]);

        $produtoEditar = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">

        <div>
            <h3 class="fw-bold mb-1">Gerenciamento de Produtos</h3>
            <p class="text-muted mb-0">
                Cadastre, consulte e gerencie os produtos do estoque.
            </p>
        </div>

        <a href="index.php?pagina=produtos&novo=1"
           class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>
            Novo produto
        </a>

    </div>

    <?php if ($mensagem !== ''): ?>

        <div class="alert alert-<?= escapar($tipoMensagem) ?> alert-dismissible fade show"
             role="alert">

            <?= escapar($mensagem) ?>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
            </button>

        </div>

    <?php endif; ?>


    <!-- FORMULÁRIO DE CADASTRO E EDIÇÃO -->

    <?php if (isset($_GET['novo']) || $produtoEditar): ?>

        <div class="card border-0 shadow-sm mb-4">

            <div class="card-header bg-white py-3">

                <h5 class="mb-0 fw-bold">

                    <?= $produtoEditar
                        ? 'Editar produto'
                        : 'Cadastrar novo produto' ?>

                </h5>

            </div>

            <div class="card-body">

                <form method="POST"
                      action="index.php?pagina=produtos"
                      enctype="multipart/form-data">

                    <input type="hidden" name="acao" value="salvar">

                    <?php if ($produtoEditar): ?>

                        <input type="hidden"
                               name="id_produto"
                               value="<?= escapar($produtoEditar['id_produto']) ?>">

                    <?php endif; ?>

                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Nome do produto *
                            </label>

                            <input type="text"
                                   name="nome"
                                   class="form-control"
                                   maxlength="150"
                                   required
                                   value="<?= escapar($produtoEditar['nome'] ?? '') ?>"
                                   placeholder="Digite o nome do produto">

                        </div>

                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Categoria *
                            </label>

                            <select name="id_categoria"
                                    class="form-select"
                                    required>

                                <option value="">
                                    Selecione uma categoria
                                </option>

                                <?php foreach ($categorias as $categoria): ?>

                                    <option
                                        value="<?= escapar($categoria['id_categoria']) ?>"
                                        <?= (
                                            isset($produtoEditar['id_categoria']) &&
                                            $produtoEditar['id_categoria'] == $categoria['id_categoria']
                                        ) ? 'selected' : '' ?>>

                                        <?= escapar($categoria['nome_categoria']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="col-12">

                            <label class="form-label fw-semibold">
                                Descrição
                            </label>

                            <textarea name="descricao"
                                      class="form-control"
                                      rows="3"
                                      placeholder="Descreva o produto..."><?= escapar($produtoEditar['descricao'] ?? '') ?></textarea>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Quantidade em estoque *
                            </label>

                            <input type="number"
                                   name="quantidade_estoque"
                                   class="form-control"
                                   min="0"
                                   required
                                   value="<?= escapar($produtoEditar['quantidade_estoque'] ?? '0') ?>">

                        </div>

                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Estoque mínimo *
                            </label>

                            <input type="number"
                                   name="estoque_minimo"
                                   class="form-control"
                                   min="0"
                                   required
                                   value="<?= escapar($produtoEditar['estoque_minimo'] ?? '0') ?>">

                        </div>

                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Imagem do produto
                            </label>

                            <input type="file"
                                   name="imagem"
                                   class="form-control"
                                   accept=".jpg,.jpeg,.png,.webp">

                            <small class="text-muted">
                                Formatos JPG, PNG ou WEBP. Máximo de 5 MB.
                            </small>

                        </div>

                        <?php if (!empty($produtoEditar['imagem'])): ?>

                            <div class="col-md-6">

                                <label class="form-label fw-semibold">
                                    Imagem atual
                                </label>

                                <div>
                                    <img
                                        src="<?= escapar($caminhoImagens . $produtoEditar['imagem']) ?>"
                                        alt="Imagem do produto"
                                        style="width: 100px; height: 100px; object-fit: cover;"
                                        class="rounded border">
                                </div>

                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="d-flex gap-2 mt-4">

                        <button type="submit" class="btn btn-primary">

                            <i class="bi bi-check-lg me-1"></i>

                            <?= $produtoEditar
                                ? 'Salvar alterações'
                                : 'Cadastrar produto' ?>

                        </button>

                        <a href="index.php?pagina=produtos"
                           class="btn btn-outline-secondary">

                            Cancelar

                        </a>

                    </div>

                </form>

            </div>

        </div>

    <?php endif; ?>


    <!-- PESQUISA E FILTRO -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <form method="GET"
                  action="index.php"
                  class="row g-3">

                <input type="hidden"
                       name="pagina"
                       value="produtos">

                <div class="col-md-6">

                    <label class="form-label fw-semibold">
                        Pesquisar produto
                    </label>

                    <input type="text"
                           name="pesquisa"
                           class="form-control"
                           placeholder="Digite o nome do produto..."
                           value="<?= escapar($pesquisa) ?>">

                </div>

                <div class="col-md-4">

                    <label class="form-label fw-semibold">
                        Filtrar por categoria
                    </label>

                    <select name="categoria" class="form-select">

                        <option value="">
                            Todas as categorias
                        </option>

                        <?php foreach ($categorias as $categoria): ?>

                            <option
                                value="<?= escapar($categoria['id_categoria']) ?>"
                                <?= $filtroCategoria == $categoria['id_categoria']
                                    ? 'selected'
                                    : '' ?>>

                                <?= escapar($categoria['nome_categoria']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-md-2 d-flex align-items-end">

                    <button type="submit"
                            class="btn btn-dark w-100">

                        <i class="bi bi-search me-1"></i>
                        Buscar

                    </button>

                </div>

            </form>

        </div>

    </div>


    <!-- LISTAGEM DE PRODUTOS -->

    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white py-3">

            <div class="d-flex justify-content-between align-items-center">

                <h5 class="fw-bold mb-0">
                    Produtos cadastrados
                </h5>

                <span class="badge bg-primary">
                    <?= count($produtos) ?> produto(s)
                </span>

            </div>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>
                            <th>Imagem</th>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th>Quantidade</th>
                            <th>Estoque mínimo</th>
                            <th>Situação</th>
                            <th class="text-center">Ações</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (count($produtos) > 0): ?>

                            <?php foreach ($produtos as $produto): ?>

                                <?php

                                if ($produto['quantidade_estoque'] == 0) {

                                    $situacao = 'Sem estoque';
                                    $classe = 'danger';

                                } elseif (
                                    $produto['quantidade_estoque'] <=
                                    $produto['estoque_minimo']
                                ) {

                                    $situacao = 'Estoque baixo';
                                    $classe = 'warning';

                                } else {

                                    $situacao = 'Disponível';
                                    $classe = 'success';

                                }

                                ?>

                                <tr>

                                    <td>

                                        <?php if (!empty($produto['imagem'])): ?>

                                            <img
                                                src="<?= escapar($caminhoImagens . $produto['imagem']) ?>"
                                                alt="<?= escapar($produto['nome']) ?>"
                                                style="width: 55px; height: 55px; object-fit: cover;"
                                                class="rounded border">

                                        <?php else: ?>

                                            <div class="bg-light border rounded d-flex align-items-center justify-content-center"
                                                 style="width: 55px; height: 55px;">

                                                <i class="bi bi-image text-muted fs-4"></i>

                                            </div>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <strong>
                                            <?= escapar($produto['nome']) ?>
                                        </strong>

                                        <div class="small text-muted">
                                            Código: #<?= escapar($produto['id_produto']) ?>
                                        </div>

                                    </td>

                                    <td>
                                        <?= escapar($produto['nome_categoria']) ?>
                                    </td>

                                    <td>
                                        <?= escapar($produto['quantidade_estoque']) ?>
                                    </td>

                                    <td>
                                        <?= escapar($produto['estoque_minimo']) ?>
                                    </td>

                                    <td>

                                        <span class="badge text-bg-<?= $classe ?>">
                                            <?= escapar($situacao) ?>
                                        </span>

                                    </td>

                                    <td class="text-center">

                                        <div class="d-flex justify-content-center gap-2">

                                            <a href="index.php?pagina=produtos&editar=<?= escapar($produto['id_produto']) ?>"
                                               class="btn btn-sm btn-outline-primary"
                                               title="Editar produto">

                                                <i class="bi bi-pencil"></i>

                                            </a>

                                            <form method="POST"
                                                  action="index.php?pagina=produtos"
                                                  onsubmit="return confirm('Tem certeza que deseja excluir este produto?');">

                                                <input type="hidden"
                                                       name="acao"
                                                       value="excluir">

                                                <input type="hidden"
                                                       name="id_produto"
                                                       value="<?= escapar($produto['id_produto']) ?>">

                                                <button type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="Excluir produto">

                                                    <i class="bi bi-trash"></i>

                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="7"
                                    class="text-center py-5">

                                    <i class="bi bi-box-seam fs-1 text-muted"></i>

                                    <p class="mt-2 mb-0 text-muted">
                                        Nenhum produto encontrado.
                                    </p>

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

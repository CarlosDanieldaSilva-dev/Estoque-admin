
<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Verificar nível de acesso
$usuarioLogado = $_SESSION["Controle de Estoque"] ?? null;

if (
    !$usuarioLogado ||
    ($usuarioLogado["nivel_acesso"] ?? "") !== "Administrador"
) {
    echo '<div class="alert alert-danger">
            Você não tem permissão para acessar esta página.
          </div>';
    return;
}

// Conexão com o banco
if (!isset($pdo)) {
    require_once __DIR__ . '/../../config.php';
}

// Mensagens
$mensagem = '';
$tipoMensagem = '';

// Token CSRF
if (empty($_SESSION['csrf_usuarios'])) {
    $_SESSION['csrf_usuarios'] = bin2hex(random_bytes(32));
}

$tokenCSRF = $_SESSION['csrf_usuarios'];

// Função para validar datas
function dataValidaUsuario($data)
{
    if ($data === '') {
        return true;
    }

    $dataObjeto = DateTime::createFromFormat('!Y-m-d', $data);

    return $dataObjeto && $dataObjeto->format('Y-m-d') === $data;
}

// Identificar usuário em edição
$usuarioEdicao = null;

if (isset($_GET['editar']) && ctype_digit($_GET['editar'])) {

    $idEdicao = (int) $_GET['editar'];

    try {

        $stmtEdicao = $pdo->prepare("
            SELECT
                id_usuario,
                nome,
                data_nascimento,
                funcao,
                email,
                telefone,
                data_de_contrato,
                status,
                nivel_acesso
            FROM usuario
            WHERE id_usuario = :id
            LIMIT 1
        ");

        $stmtEdicao->execute([
            ':id' => $idEdicao
        ]);

        $usuarioEdicao = $stmtEdicao->fetch(PDO::FETCH_ASSOC);

        if (!$usuarioEdicao) {
            $mensagem = 'Usuário não encontrado.';
            $tipoMensagem = 'danger';
        }

    } catch (PDOException $e) {

        $mensagem = 'Não foi possível carregar os dados do usuário.';
        $tipoMensagem = 'danger';

    }
}

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acao = $_POST['acao'] ?? '';
    $tokenEnviado = $_POST['csrf_token'] ?? '';

    if (!hash_equals($tokenCSRF, $tokenEnviado)) {

        $mensagem = 'Solicitação inválida. Atualize a página e tente novamente.';
        $tipoMensagem = 'danger';

    } elseif (in_array($acao, ['desativar', 'ativar'], true)) {

        // PROCESSAR ATIVAÇÃO OU DESATIVAÇÃO

        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);

        if ($idUsuario <= 0) {

            $mensagem = 'Usuário inválido.';
            $tipoMensagem = 'danger';

        } elseif (
            $idUsuario === (int) ($usuarioLogado['id_usuario'] ?? 0)
        ) {

            $mensagem = 'Você não pode alterar o status da sua própria conta.';
            $tipoMensagem = 'danger';

        } else {

            try {

                // Verificar se o usuário existe
                $stmtExiste = $pdo->prepare("
                    SELECT id_usuario
                    FROM usuario
                    WHERE id_usuario = :id
                    LIMIT 1
                ");

                $stmtExiste->execute([
                    ':id' => $idUsuario
                ]);

                if (!$stmtExiste->fetch()) {

                    $mensagem = 'Usuário não encontrado.';
                    $tipoMensagem = 'danger';

                } else {

                    $novoStatus = $acao === 'desativar'
                        ? 'Inativo'
                        : 'Ativo';

                    $stmtStatus = $pdo->prepare("
                        UPDATE usuario
                        SET status = :status
                        WHERE id_usuario = :id
                    ");

                    $stmtStatus->execute([
                        ':status' => $novoStatus,
                        ':id' => $idUsuario
                    ]);

                    $mensagem = $novoStatus === 'Inativo'
                        ? 'Usuário desativado com sucesso!'
                        : 'Usuário ativado com sucesso!';

                    $tipoMensagem = 'success';

                }

            } catch (PDOException $e) {

                $mensagem = 'Não foi possível alterar o status do usuário.';
                $tipoMensagem = 'danger';

            }

        }

    } elseif (in_array($acao, ['cadastrar', 'editar'], true)) {

        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $dataNascimento = $_POST['data_nascimento'] ?? '';
        $telefone = trim($_POST['telefone'] ?? '');
        $dataContrato = $_POST['data_de_contrato'] ?? '';
        $funcao = trim($_POST['funcao'] ?? '');
        $nivelAcesso = $_POST['nivel_acesso'] ?? '';
        $status = $_POST['status'] ?? '';
        $senha = $_POST['senha'] ?? '';

        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);

        // Validar campos
        $camposValidos = (
            $nome !== '' &&
            strlen($nome) <= 150 &&
            filter_var($email, FILTER_VALIDATE_EMAIL) &&
            strlen($email) <= 150 &&
            strlen($funcao) <= 100 &&
            strlen($telefone) <= 20 &&
            dataValidaUsuario($dataNascimento) &&
            dataValidaUsuario($dataContrato) &&
            in_array($nivelAcesso, ['Administrador', 'Funcionario'], true) &&
            in_array($status, ['Ativo', 'Inativo'], true)
        );

        if (
            !$camposValidos ||
            ($acao === 'cadastrar' && strlen($senha) < 8) ||
            ($acao === 'editar' && $idUsuario <= 0) ||
            ($senha !== '' && strlen($senha) < 8)
        ) {

            $mensagem = 'Verifique os dados informados. A senha deve ter pelo menos 8 caracteres.';
            $tipoMensagem = 'danger';

        } else {

            try {

                if ($acao === 'cadastrar') {

                    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

                    $sql = "INSERT INTO usuario (
                                nome,
                                data_nascimento,
                                funcao,
                                email,
                                telefone,
                                data_de_contrato,
                                status,
                                senha,
                                nivel_acesso
                            ) VALUES (
                                :nome,
                                :data_nascimento,
                                :funcao,
                                :email,
                                :telefone,
                                :data_de_contrato,
                                :status,
                                :senha,
                                :nivel_acesso
                            )";

                    $stmt = $pdo->prepare($sql);

                    $stmt->execute([
                        ':nome' => $nome,
                        ':data_nascimento' => $dataNascimento !== '' ? $dataNascimento : null,
                        ':funcao' => $funcao !== '' ? $funcao : null,
                        ':email' => $email,
                        ':telefone' => $telefone !== '' ? $telefone : null,
                        ':data_de_contrato' => $dataContrato !== '' ? $dataContrato : null,
                        ':status' => $status,
                        ':senha' => $senhaHash,
                        ':nivel_acesso' => $nivelAcesso
                    ]);

                    $mensagem = 'Usuário cadastrado com sucesso!';
                    $tipoMensagem = 'success';

                    $usuarioEdicao = null;

                } elseif ($acao === 'editar') {

                    // Verificar se o usuário existe
                    $stmtExiste = $pdo->prepare("
                        SELECT id_usuario
                        FROM usuario
                        WHERE id_usuario = :id
                        LIMIT 1
                    ");

                    $stmtExiste->execute([
                        ':id' => $idUsuario
                    ]);

                    if (!$stmtExiste->fetch()) {

                        throw new RuntimeException('Usuário não encontrado.');

                    }

                    // Impedir alteração do próprio acesso
                    if (
                        $idUsuario === (int) ($usuarioLogado['id_usuario'] ?? 0) &&
                        (
                            $status !== 'Ativo' ||
                            $nivelAcesso !== 'Administrador'
                        )
                    ) {

                        throw new RuntimeException(
                            'Você não pode desativar ou retirar seu próprio acesso de administrador.'
                        );

                    }

                    // Atualizar sem alterar a senha
                    if ($senha === '') {

                        $sql = "UPDATE usuario SET
                                    nome = :nome,
                                    data_nascimento = :data_nascimento,
                                    funcao = :funcao,
                                    email = :email,
                                    telefone = :telefone,
                                    data_de_contrato = :data_de_contrato,
                                    status = :status,
                                    nivel_acesso = :nivel_acesso
                                WHERE id_usuario = :id";

                        $stmt = $pdo->prepare($sql);

                        $stmt->execute([
                            ':nome' => $nome,
                            ':data_nascimento' => $dataNascimento !== '' ? $dataNascimento : null,
                            ':funcao' => $funcao !== '' ? $funcao : null,
                            ':email' => $email,
                            ':telefone' => $telefone !== '' ? $telefone : null,
                            ':data_de_contrato' => $dataContrato !== '' ? $dataContrato : null,
                            ':status' => $status,
                            ':nivel_acesso' => $nivelAcesso,
                            ':id' => $idUsuario
                        ]);

                    } else {

                        // Atualizar incluindo uma nova senha
                        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

                        $sql = "UPDATE usuario SET
                                    nome = :nome,
                                    data_nascimento = :data_nascimento,
                                    funcao = :funcao,
                                    email = :email,
                                    telefone = :telefone,
                                    data_de_contrato = :data_de_contrato,
                                    status = :status,
                                    senha = :senha,
                                    nivel_acesso = :nivel_acesso
                                WHERE id_usuario = :id";

                        $stmt = $pdo->prepare($sql);

                        $stmt->execute([
                            ':nome' => $nome,
                            ':data_nascimento' => $dataNascimento !== '' ? $dataNascimento : null,
                            ':funcao' => $funcao !== '' ? $funcao : null,
                            ':email' => $email,
                            ':telefone' => $telefone !== '' ? $telefone : null,
                            ':data_de_contrato' => $dataContrato !== '' ? $dataContrato : null,
                            ':status' => $status,
                            ':senha' => $senhaHash,
                            ':nivel_acesso' => $nivelAcesso,
                            ':id' => $idUsuario
                        ]);

                    }

                    $mensagem = 'Usuário atualizado com sucesso!';
                    $tipoMensagem = 'success';

                    $usuarioEdicao = null;

                }

            } catch (PDOException $e) {

                if ($e->getCode() === '23000') {
                    $mensagem = 'Este e-mail já está cadastrado para outro usuário.';
                } else {
                    $mensagem = 'Não foi possível salvar os dados do usuário.';
                }

                $tipoMensagem = 'danger';

            } catch (RuntimeException $e) {

                $mensagem = $e->getMessage();
                $tipoMensagem = 'danger';

            }

            // Manter os dados digitados caso ocorra algum erro
            if ($tipoMensagem === 'danger' && $acao === 'editar') {

                $usuarioEdicao = [
                    'id_usuario' => $idUsuario,
                    'nome' => $nome,
                    'data_nascimento' => $dataNascimento,
                    'funcao' => $funcao,
                    'email' => $email,
                    'telefone' => $telefone,
                    'data_de_contrato' => $dataContrato,
                    'status' => $status,
                    'nivel_acesso' => $nivelAcesso
                ];

            }

        }

    }

}

// Buscar usuários
try {

    $sql = "SELECT
                id_usuario,
                nome,
                data_nascimento,
                funcao,
                email,
                telefone,
                data_de_contrato,
                data_cadastro,
                status,
                nivel_acesso
            FROM usuario
            ORDER BY nome ASC";

    $stmt = $pdo->query($sql);

    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $usuarios = [];
    $mensagem = 'Não foi possível carregar os usuários.';
    $tipoMensagem = 'danger';

}

?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold">Usuários</h3>
            <p class="text-muted">
                Gerencie os usuários e seus níveis de acesso.
            </p>
        </div>
    </div>

    <!-- MENSAGENS -->
    <?php if ($mensagem !== ''): ?>

        <div class="alert alert-<?php echo htmlspecialchars($tipoMensagem, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show">

            <?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
            </button>

        </div>

    <?php endif; ?>

    <!-- FORMULÁRIO DE USUÁRIO -->
    <div class="card shadow-sm border-0 mb-4">

        <div class="card-header bg-white py-3">
            <h4>
                <?php echo $usuarioEdicao ? 'Editar usuário' : 'Cadastrar usuário'; ?>
            </h4>
        </div>

        <div class="card-body">

            <form method="POST">

                <input type="hidden"
                       name="csrf_token"
                       value="<?php echo htmlspecialchars($tokenCSRF, ENT_QUOTES, 'UTF-8'); ?>">

                <input type="hidden"
                       name="acao"
                       value="<?php echo $usuarioEdicao ? 'editar' : 'cadastrar'; ?>">

                <?php if ($usuarioEdicao): ?>
                    <input type="hidden"
                           name="id_usuario"
                           value="<?php echo (int) $usuarioEdicao['id_usuario']; ?>">
                <?php endif; ?>

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nome completo</label>
                        <input type="text"
                               name="nome"
                               class="form-control"
                               maxlength="150"
                               value="<?php echo htmlspecialchars($usuarioEdicao['nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                               required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email"
                               name="email"
                               class="form-control"
                               maxlength="150"
                               value="<?php echo htmlspecialchars($usuarioEdicao['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                               required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Data de nascimento</label>
                        <input type="date"
                               name="data_nascimento"
                               class="form-control"
                               value="<?php echo htmlspecialchars(($usuarioEdicao['data_nascimento'] ?? '') === '0001-01-01' ? '' : ($usuarioEdicao['data_nascimento'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Telefone</label>
                        <input type="text"
                               name="telefone"
                               class="form-control"
                               maxlength="20"
                               value="<?php echo htmlspecialchars($usuarioEdicao['telefone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Data de contrato</label>
                        <input type="date"
                               name="data_de_contrato"
                               class="form-control"
                               value="<?php echo htmlspecialchars($usuarioEdicao['data_de_contrato'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Função</label>
                        <input type="text"
                               name="funcao"
                               class="form-control"
                               maxlength="100"
                               value="<?php echo htmlspecialchars($usuarioEdicao['funcao'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Nível de acesso</label>
                        <select name="nivel_acesso" class="form-select" required>

                            <option value="Administrador"
                                <?php echo (($usuarioEdicao['nivel_acesso'] ?? 'Funcionario') === 'Administrador') ? 'selected' : ''; ?>>
                                Administrador
                            </option>

                            <option value="Funcionario"
                                <?php echo (($usuarioEdicao['nivel_acesso'] ?? 'Funcionario') === 'Funcionario') ? 'selected' : ''; ?>>
                                Funcionário
                            </option>

                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>

                            <option value="Ativo"
                                <?php echo (($usuarioEdicao['status'] ?? 'Ativo') === 'Ativo') ? 'selected' : ''; ?>>
                                Ativo
                            </option>

                            <option value="Inativo"
                                <?php echo (($usuarioEdicao['status'] ?? '') === 'Inativo') ? 'selected' : ''; ?>>
                                Inativo
                            </option>

                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">
                            Senha
                            <?php echo $usuarioEdicao ? '(deixe em branco para manter a atual)' : ''; ?>
                        </label>

                        <input type="password"
                               name="senha"
                               class="form-control"
                               minlength="8"
                               <?php echo $usuarioEdicao ? '' : 'required'; ?>>
                    </div>

                </div>

                <button type="submit" class="btn btn-primary">
                    <?php echo $usuarioEdicao ? 'Salvar alterações' : 'Cadastrar usuário'; ?>
                </button>

                <?php if ($usuarioEdicao): ?>
                    <a href="index.php?pagina=usuarios"
                       class="btn btn-secondary">
                        Cancelar edição
                    </a>
                <?php endif; ?>

            </form>

        </div>
    </div>

    <!-- LISTAGEM -->
    <div class="card shadow-sm border-0">

        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold">Usuários cadastrados</h5>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Função</th>
                            <th>Nível de acesso</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (count($usuarios) > 0): ?>

                            <?php foreach ($usuarios as $usuario): ?>

                                <tr>

                                    <td>
                                        <?php echo (int) $usuario['id_usuario']; ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($usuario['email'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($usuario['funcao'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($usuario['nivel_acesso'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>

                                    <td>
                                        <?php if ($usuario['status'] === 'Ativo'): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inativo</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="d-flex gap-2 flex-wrap">

                                        <a href="index.php?pagina=usuarios&editar=<?php echo (int) $usuario['id_usuario']; ?>"
                                           class="btn btn-sm btn-warning">
                                            <i class="bi bi-pencil-square"></i>
                                            Editar
                                        </a>

                                        <?php if ((int) $usuario['id_usuario'] !== (int) ($usuarioLogado['id_usuario'] ?? 0)): ?>

                                            <form method="POST"
                                                  onsubmit="return confirm('Tem certeza que deseja alterar o status deste usuário?');">

                                                <input type="hidden"
                                                       name="csrf_token"
                                                       value="<?php echo htmlspecialchars($tokenCSRF, ENT_QUOTES, 'UTF-8'); ?>">

                                                <input type="hidden"
                                                       name="id_usuario"
                                                       value="<?php echo (int) $usuario['id_usuario']; ?>">

                                                <?php if ($usuario['status'] === 'Ativo'): ?>

                                                    <input type="hidden"
                                                           name="acao"
                                                           value="desativar">

                                                    <button type="submit"
                                                            class="btn btn-sm btn-danger">
                                                        <i class="bi bi-person-x"></i>
                                                        Desativar
                                                    </button>

                                                <?php else: ?>

                                                    <input type="hidden"
                                                           name="acao"
                                                           value="ativar">

                                                    <button type="submit"
                                                            class="btn btn-sm btn-success">
                                                        <i class="bi bi-person-check"></i>
                                                        Ativar
                                                    </button>

                                                <?php endif; ?>

                                            </form>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    Nenhum usuário encontrado.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <div class="text-muted small">
                Total de usuários:
                <strong><?php echo count($usuarios); ?></strong>
            </div>

        </div>

    </div>

</div>


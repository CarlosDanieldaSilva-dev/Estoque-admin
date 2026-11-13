

<?php

// Iniciar buffer de saída
ob_start();

// Iniciar sessão
session_start();

// Conexão com banco de dados
require "../config.php";

$erroLogin = null;


// ==========================================
// PROCESSAR LOGIN
// ==========================================

if (
    !isset($_SESSION["Controle de Estoque"]) &&
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $email = trim($_POST["email"] ?? "");
    $senha = trim($_POST["senha"] ?? "");

    // Validar campos
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $erroLogin = "E-mail inválido.";

    } elseif (empty($senha)) {

        $erroLogin = "Senha inválida.";

    } else {

        // Buscar usuário no banco
        $sqlLogin = "SELECT
                        id_usuario,
                        nome,
                        email,
                        senha,
                        nivel_acesso
                     FROM usuario
                     WHERE status = 'Ativo'
                     AND email = :email
                     LIMIT 1";

        $consultaLogin = $pdo->prepare($sqlLogin);

        $consultaLogin->bindParam(":email", $email);

        $consultaLogin->execute();

        $dadosLogin = $consultaLogin->fetch(PDO::FETCH_OBJ);


        // Validar usuário e senha
        if (
            !$dadosLogin ||
            !password_verify($senha, $dadosLogin->senha)
        ) {

            $erroLogin = "E-mail ou senha incorretos.";

        } else {

            // Criar sessão
            session_regenerate_id(true);

            $_SESSION["Controle de Estoque"] = [
                "id_usuario" => $dadosLogin->id_usuario,
                "nome" => $dadosLogin->nome,
                "nivel_acesso" => $dadosLogin->nivel_acesso
            ];

            // Redirecionar para o painel
            header("Location: index.php");
            exit;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sistema Administrativo - Controle de Estoque</title>


    <!-- Google Fonts -->

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
          rel="stylesheet">


    <!-- Bootstrap CSS -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <!-- Bootstrap Icons -->

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


    <!-- SweetAlert2 -->

    <link rel="stylesheet"
          href="css/sweetalert2.min.css">


    <!-- Summernote CSS -->

    <link href="css/summernote-bs5.min.css"
          rel="stylesheet">


    <!-- CSS personalizado -->

    <link rel="stylesheet"
          href="css/style.css?v=1">


    <!-- Favicon -->

    <link rel="icon"
          href="../imgs/logotiposite.jpg">


    <!-- Função de mensagens -->

    <script>

        function mensagem(texto, tipo) {

            Swal.fire({

                icon: tipo,

                title: tipo === "error"
                    ? "Atenção"
                    : "Sucesso",

                text: texto,

                confirmButtonText: "OK"

            });

        }

    </script>

</head>


<body>

<?php

// ==========================================
// VERIFICAR LOGIN E CARREGAR SISTEMA
// ==========================================

if (!isset($_SESSION["Controle de Estoque"])) {

    // Mostrar tela de login
    require "pages/login.php";

} else {

    // Carregar Navbar
    require "includes/navbar.php";


    // Identificar página solicitada
    $pagina = $_GET['pagina'] ?? 'home';


    // Páginas permitidas
    $paginasPermitidas = [

        'home',
        'produtos',
        'categorias',
        'movimentacao',
        'usuarios',
        'perfil'

    ];


    // Verificar se a página está autorizada
    if (in_array($pagina, $paginasPermitidas, true)) {

        require "pages/" . $pagina . ".php";

    } else {

        echo "<div class='container mt-4'>";

        echo "<div class='alert alert-danger'>
                Página não encontrada.
              </div>";

        echo "</div>";

    }

}

?>


<!-- ========================================== -->
<!-- ARQUIVOS JAVASCRIPT -->
<!-- ========================================== -->


<!-- Bootstrap JS -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>


<!-- jQuery -->

<script src="js/jquery-3.5.1.min.js"></script>


<!-- Parsley -->

<script src="js/parsley.min.js"></script>


<!-- SweetAlert2 -->

<script src="js/sweetalert2.js"></script>


<!-- Inputmask -->

<script src="js/jquery.inputmask.min.js"></script>

<script src="js/bindings/inputmask.binding.js"></script>


<!-- jQuery Mask -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>


<!-- Summernote -->

<script src="js/summernote-bs5.min.js"></script>


<!-- ========================================== -->
<!-- MENSAGEM DE ERRO DO LOGIN -->
<!-- ========================================== -->

<?php if ($erroLogin !== null): ?>

    <script>

        mensagem(
            <?= json_encode($erroLogin) ?>,
            "error"
        );

    </script>

<?php endif; ?>


</body>

</html>


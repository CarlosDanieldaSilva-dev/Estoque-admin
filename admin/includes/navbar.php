<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container-fluid">

        <!-- Logo e nome do sistema -->
        <a class="navbar-brand fw-bold" href="index.php?pagina=home">
            <i class="bi bi-box-seam me-2"></i>
            Controle de Estoque
        </a>

        <!-- Botão para telas menores -->
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#menuPrincipal"
                aria-controls="menuPrincipal"
                aria-expanded="false"
                aria-label="Abrir menu">

            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Itens da Navbar -->
        <div class="collapse navbar-collapse" id="menuPrincipal">

            <!-- Menu esquerdo -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <li class="nav-item">
                    <a class="nav-link" href="index.php?pagina=home">
                        <i class="bi bi-house-door me-1"></i>
                        Página inicial
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="index.php?pagina=produtos">
                        <i class="bi bi-box-seam me-1"></i>
                        Produtos
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="index.php?pagina=categorias">
                        <i class="bi bi-tags me-1"></i>
                        Categorias
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="index.php?pagina=movimentacao">
                        <i class="bi bi-arrow-left-right me-1"></i>
                        Movimento de Estoque
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="index.php?pagina=usuarios">
                        <i class="bi bi-people me-1"></i>
                        Usuários
                    </a>
                </li>

            </ul>

            <!-- Menu direito: administrador -->
            <ul class="navbar-nav">

                <li class="nav-item dropdown">

                    <a class="nav-link dropdown-toggle"
                       href="#"
                       role="button"
                       data-bs-toggle="dropdown"
                       aria-expanded="false">

                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($_SESSION["Controle de Estoque"]["nome"]) ?>

                    </a>

                    <ul class="dropdown-menu dropdown-menu-end">

                        <li>
                            <a class="dropdown-item" href="index.php?pagina=perfil">
                                <i class="bi bi-person me-2"></i>
                                Meu perfil
                            </a>
                        </li>

                        <li><hr class="dropdown-divider"></li>

                        <li>
                            <a class="dropdown-item text-danger" href="sair.php">
                                <i class="bi bi-box-arrow-right me-2"></i>
                                Sair
                            </a>
                        </li>

                    </ul>

                </li>

            </ul>

        </div>
    </div>
</nav>
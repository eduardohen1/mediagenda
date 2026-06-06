<?php
session_start();
require_once("conexao.php");

if(!isset($_SESSION['cod_usuario'])){
    header("Location: login.php");
    exit;
}
$cod_usuario = $_SESSION['cod_usuario'];
$nomeUsuario = "";
$emailUsuario = "";
$perfilUsuario = "";
$pageError = '';

/* ============================================================
   1. SELECT INICIAL
============================================================ */
$sql = "SELECT * FROM usuario WHERE cod_usuario = ?";
$stmt = mysqli_prepare($conexao_bd, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $cod_usuario);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && $consulta = mysqli_fetch_assoc($result)) {
        $nomeUsuario = $consulta["nome"];
        $emailUsuario = $consulta["email"];
        $perfilUsuario = $consulta["perfil"];
    } elseif ($result === false) {
        $pageError = mysqli_error($conexao_bd);
    }
    mysqli_stmt_close($stmt);
}

if ($perfilUsuario != "admin") {
    header("Location: principal.php");
    exit;
}

/* ============================================================
   2. INSERT (Adicionar Usuário)
============================================================ */
if (isset($_POST["adicionar"])) {
    $nome     = $_POST["novo_nome"];
    $email    = $_POST["novo_email"];
    $username = $_POST["novo_username"];
    $perfil   = $_POST["novo_perfil"];
    
    // O Hash da senha já é seguro por natureza
    $pass = password_hash($_POST["novo_pass"], PASSWORD_DEFAULT);

    $sqlInsert = "INSERT INTO usuario (nome, email, username, pass, perfil) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conexao_bd, $sqlInsert);
    
    if ($stmt) {
        // "sssss" indica 5 strings
        mysqli_stmt_bind_param($stmt, "sssss", $nome, $email, $username, $pass, $perfil);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    header("Location: admin_usuarios.php?sucesso=adicionar");
    exit;
}

/* ============================================================
   3. INSERT (Gerar Convite)
============================================================ */
if (isset($_POST["gerar_convite"])) {
    $perfilConvite = $_POST["perfil_convite"];
    $codigoConvite = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));

    $sqlConvite = "INSERT INTO convite_usuario (codigo, perfil, usado) VALUES (?, ?, 0)";
    $stmt = mysqli_prepare($conexao_bd, $sqlConvite);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $codigoConvite, $perfilConvite);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("Location: admin_usuarios.php?convite=$codigoConvite");
            exit;
        }
        mysqli_stmt_close($stmt);
    }
}

/* ============================================================
   4. UPDATE (Editar Usuário)
============================================================ */
if (isset($_POST["editar"])) {
    $cod_usuario_edit = intval($_POST["cod_usuario"]);
    $nome     = $_POST["nome"];
    $email    = $_POST["email"];
    $username = $_POST["username"];
    $pass     = trim($_POST["pass"] ?? '');

    if ($pass != "") {
        $passHash = password_hash($pass, PASSWORD_DEFAULT);
        $sqlUpdate = "UPDATE usuario SET nome = ?, email = ?, username = ?, pass = ? WHERE cod_usuario = ?";
        $stmt = mysqli_prepare($conexao_bd, $sqlUpdate);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssssi", $nome, $email, $username, $passHash, $cod_usuario_edit);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } else {
        $sqlUpdate = "UPDATE usuario SET nome = ?, email = ?, username = ? WHERE cod_usuario = ?";
        $stmt = mysqli_prepare($conexao_bd, $sqlUpdate);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sssi", $nome, $email, $username, $cod_usuario_edit);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    header("Location: admin_usuarios.php?sucesso=editar");
    exit;
}

/* ============================================================
   5. DELETE BLINDADO (Excluir Usuário)
============================================================ */
if (isset($_GET["excluir"])) {
    $usernameExcluir = $_GET["excluir"];

    $sqlDelete = "DELETE FROM usuario WHERE username = ?";
    $stmt = mysqli_prepare($conexao_bd, $sqlDelete);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $usernameExcluir);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    header("Location: admin_usuarios.php?sucesso=excluir");
    exit;
}

/* ============================================================
   6. SELECT DINÂMICO BLINDADO (Filtros e Paginação)
============================================================ */
$filtroNome = trim($_GET["nome"] ?? '');
$filtroUsername = trim($_GET["username"] ?? '');
$filtroEmail = trim($_GET["email"] ?? '');

$where = array();
$params = array();
$types = "";

if ($filtroNome != '') {
    $where[] = "nome LIKE ?";
    $params[] = "%" . $filtroNome . "%";
    $types .= "s";
}

if ($filtroUsername != '') {
    $where[] = "username LIKE ?";
    $params[] = "%" . $filtroUsername . "%";
    $types .= "s";
}

if ($filtroEmail != '') {
    $where[] = "email LIKE ?";
    $params[] = "%" . $filtroEmail . "%";
    $types .= "s";
}

$whereClause = "";
if (!empty($where)) {
    $whereClause = " WHERE " . implode(" AND ", $where);
}

// 6.1 Contagem Total
$sqlTotal = "SELECT COUNT(*) as total FROM usuario" . $whereClause;
$stmtTotal = mysqli_prepare($conexao_bd, $sqlTotal);
if ($stmtTotal) {
    if (!empty($params)) {
        // Operador de espalhamento (spread operator) injeta os itens do array como parâmetros
        mysqli_stmt_bind_param($stmtTotal, $types, ...$params);
    }
    mysqli_stmt_execute($stmtTotal);
    $resultTotal = mysqli_stmt_get_result($stmtTotal);
    $totalUsuarios = mysqli_fetch_assoc($resultTotal)["total"];
    mysqli_stmt_close($stmtTotal);
} else {
    $totalUsuarios = 0;
}

$usuariosPorPagina = 10;
$totalPaginas = ceil($totalUsuarios / $usuariosPorPagina);
$paginaAtual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
if ($paginaAtual < 1) { $paginaAtual = 1; }
$inicio = ($paginaAtual - 1) * $usuariosPorPagina;

// 6.2 Busca Principal com Limite
$sqlBusca = "SELECT cod_usuario, nome, email, username, pass, perfil FROM usuario" . $whereClause . " ORDER BY nome ASC LIMIT ?, ?";
$stmtBusca = mysqli_prepare($conexao_bd, $sqlBusca);

if ($stmtBusca) {
    // Adiciona os limites de paginação ao array de parâmetros
    $paramsBusca = $params;
    $paramsBusca[] = $inicio;
    $paramsBusca[] = $usuariosPorPagina;
    $typesBusca = $types . "ii";

    mysqli_stmt_bind_param($stmtBusca, $typesBusca, ...$paramsBusca);
    mysqli_stmt_execute($stmtBusca);
    $resultado = mysqli_stmt_get_result($stmtBusca);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Usuários cadastrados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>

    <nav class="navbar-topo d-flex align-items-center justify-content-between px-3">
        <div class="d-flex align-items-center gap-2">
            <button class="btn-sanduiche" id="btnSanduiche" title="Menu">
                <i class="fa-solid fa-bars"></i>
            </button>

            <a class="navbar-brand mb-0 d-flex align-items-center" href="principal.php">
                <i class="fa-solid fa-stethoscope"></i>
                <span>MediAgenda</span>
            </a>
        </div>

        <div class="dropdown">
            <button
                class="operador-toggle"
                type="button"
                id="dropdownOperador"
                data-bs-toggle="dropdown"
                aria-expanded="false">

                <i class="fa-solid fa-circle-user"></i>

                <span class="d-none d-md-inline">
                    <?php echo htmlspecialchars($nomeUsuario); ?>
                </span>

                <i class="fa-solid fa-chevron-down" style="font-size: 0.75rem;"></i>
            </button>

            <ul
                class="dropdown-menu dropdown-menu-end dropdown-menu-operador"
                aria-labelledby="dropdownOperador">

                <li>
                    <a class="dropdown-item" href="#">
                        <i class="fa-solid fa-user"></i>
                        <?php echo htmlspecialchars($nomeUsuario); ?>
                    </a>
                </li>

                <li>
                    <a class="dropdown-item" href="#">
                        <i class="fa-solid fa-envelope"></i>
                        <?php echo htmlspecialchars($emailUsuario); ?>
                    </a>
                </li>

                <li><hr class="dropdown-divider"></li>

                <li>
                    <a class="dropdown-item" href="config_usuarios.php">
                        <i class="fa-solid fa-gear"></i>
                        Configurações
                    </a>
                </li>

                <li>
                    <a class="dropdown-item" href="logout.php">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        Sair
                    </a>
                </li>

            </ul>
        </div>
    </nav>

    <aside class="sidebar" id="sidebar">
        <ul class="nav flex-column">

        <li class="nav-item">
            <a class="nav-link" href="principal.php">
                <i class="fa-solid fa-calendar-days"></i>
                Calendário
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="cadastro_agendas.php">
                <i class="fa-solid fa-calendar-plus"></i>
                Agendamentos
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="cadastro_medicos.php">
                <i class="fa-solid fa-user-doctor"></i>
                Cadastro de Médicos
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="cadastro_especialidades.php">
                <i class="fa-solid fa-list-check"></i>
                Cadastro de Especialidades
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link ativo" href="admin_usuarios.php">
                <i class="fa-solid fa-users"></i>
                Administração de Usuários
            </a>
        </li>

    </ul>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="conteudo-principal" id="conteudoPrincipal">

    <div class="page-header">
        <h2>
            <i class="fa-solid fa-users"></i>
            Administração de Usuários
        </h2>

        <div class="d-flex gap-2">

            <button
                type="button"
                class="btn btn-outline-primary"
                data-bs-toggle="modal"
                data-bs-target="#conviteModal">
                <i class="fa-solid fa-key me-2"></i>
                Gerar Convite
            </button>

            <button
                type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#adicionarModal">
                <i class="fa-solid fa-user-plus me-2"></i>
                Adicionar Usuário
            </button>

        </div>
    </div>

    <div class="card-pagina">
        <div class="card-titulo">
            <i class="fa-solid fa-magnifying-glass"></i>
            Filtros
        </div>

        <form method="GET" action="admin_usuarios.php">
            <div class="row g-3">

                <div class="col-md-4">
                    <input
                        type="text"
                        name="nome"
                        class="form-control form-control-sm"
                        placeholder="Nome do usuário"
                        value="<?= htmlspecialchars($_GET['nome'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <input
                        type="text"
                        name="username"
                        class="form-control form-control-sm"
                        placeholder="Login do usuário"
                        value="<?= htmlspecialchars($_GET['username'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <input
                        type="text"
                        name="email"
                        class="form-control form-control-sm"
                        placeholder="E-mail"
                        value="<?= htmlspecialchars($_GET['email'] ?? '') ?>">
                </div>

            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-magnifying-glass me-1"></i>
                    Filtrar
                </button>

                <a href="admin_usuarios.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-xmark me-1"></i>
                    Limpar
                </a>
            </div>
        </form>
    </div>

    <div class="card-pagina">

        <div class="card-titulo d-flex justify-content-between align-items-center">
            <span>
                <i class="fa-solid fa-table-list"></i>
                Usuários cadastrados
            </span>

            <span class="text-muted" style="font-size:0.82rem; font-weight:400;">
                <?php echo $totalUsuarios; ?> registro(s) encontrado(s)
            </span>
        </div>

        <div class="table-responsive">
            <table class="tabela-agendamentos">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Usuário</th>
                        <th>Perfil</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (isset($resultado) && mysqli_num_rows($resultado) > 0) { 
                        while ($usuario = mysqli_fetch_assoc($resultado)) { ?>
                        <tr>
                            <td class="text-muted"><?= $usuario["cod_usuario"] ?></td>
                            <td><?= htmlspecialchars($usuario["nome"]) ?></td>
                            <td><?= htmlspecialchars($usuario["email"]) ?></td>
                            <td><?= htmlspecialchars($usuario["username"]) ?></td>
                            <td>
                                <?php if ($usuario["perfil"] == "admin") { ?>
                                    <span class="badge bg-primary">Administrador</span>
                                <?php } else { ?>
                                    <span class="badge bg-secondary">Usuário</span>
                                <?php } ?>
                            </td>

                            <td class="text-center" style="white-space: nowrap;">
                                <button
                                    type="button"
                                    class="btn btn-outline-primary btn-sm btn-icon-sm me-2"
                                    title="Editar usuário"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editarModal"
                                    data-cod="<?= $usuario["cod_usuario"] ?>"
                                    data-nome="<?= htmlspecialchars($usuario["nome"]) ?>"
                                    data-email="<?= htmlspecialchars($usuario["email"]) ?>"
                                    data-username="<?= htmlspecialchars($usuario["username"]) ?>">
                                    <i class="fa-solid fa-pen"></i>
                                </button>

                                <?php if ($usuario["cod_usuario"] != $_SESSION["cod_usuario"]) { ?>
                                    <a
                                        href="?excluir=<?= urlencode($usuario["username"]) ?>"
                                        class="btn btn-outline-danger btn-sm btn-icon-sm btn-excluir"
                                        title="Excluir usuário">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } } else { ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Nenhum usuário encontrado com os filtros selecionados.</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPaginas > 1) { ?>
            <div class="d-flex justify-content-end mt-3">
                <nav>
                    <ul class="pagination pagination-sm mb-0">

                        <li class="page-item <?= ($paginaAtual <= 1) ? 'disabled' : '' ?>">
                            <a
                                class="page-link"
                                href="?pagina=<?= $paginaAtual - 1 ?>&nome=<?= urlencode($filtroNome) ?>&username=<?= urlencode($filtroUsername) ?>&email=<?= urlencode($filtroEmail) ?>">
                                &laquo;
                            </a>
                        </li>

                        <?php for ($i = 1; $i <= $totalPaginas; $i++) { ?>
                            <li class="page-item <?= ($paginaAtual == $i) ? 'active' : '' ?>">
                                <a
                                    class="page-link"
                                    href="?pagina=<?= $i ?>&nome=<?= urlencode($filtroNome) ?>&username=<?= urlencode($filtroUsername) ?>&email=<?= urlencode($filtroEmail) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php } ?>

                        <li class="page-item <?= ($paginaAtual >= $totalPaginas) ? 'disabled' : '' ?>">
                            <a
                                class="page-link"
                                href="?pagina=<?= $paginaAtual + 1 ?>&nome=<?= urlencode($filtroNome) ?>&username=<?= urlencode($filtroUsername) ?>&email=<?= urlencode($filtroEmail) ?>">
                                &raquo;
                            </a>
                        </li>

                    </ul>
                </nav>
            </div>
        <?php } ?>
    </div>

    <div class="modal fade modal-form" id="editarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">

                <form method="POST">

                    <div class="modal-header">
                        <h5 class="modal-title">Editar Usuário</h5>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal">
                        </button>
                    </div>

                    <div class="modal-body">

                        <input
                            type="hidden"
                            name="cod_usuario"
                            id="edit_cod_usuario">

                        <div class="mb-3">
                            <label class="form-label">Nome Completo <span class="text-danger">*</span></label>

                            <input
                                type="text"
                                name="nome"
                                id="edit_nome"
                                class="form-control"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">E-mail <span class="text-danger">*</span></label>

                            <input
                                type="email"
                                name="email"
                                id="edit_email"
                                class="form-control"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Usuário <span class="text-danger">*</span></label>

                            <input
                                type="text"
                                name="username"
                                id="edit_username"
                                class="form-control"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nova senha</label>

                            <input
                                type="text"
                                name="pass"
                                id="edit_pass"
                                class="form-control"
                                placeholder="Digite a nova senha ou deixe em branco para manter a atual">
                        </div>

                    </div>

                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">
                            Cancelar
                        </button>

                        <button
                            type="button"
                            id="btnSalvarEdicao"
                            class="btn btn-primary">
                            Salvar
                        </button>

                    </div>

                </form>

            </div>
        </div>
    </div>

    <div class="modal fade modal-form" id="adicionarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">

                <form method="POST">

                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fa-solid fa-user-plus me-2"></i>
                            Adicionar Usuário
                        </h5>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal">
                        </button>
                    </div>

                    <div class="modal-body">

                        <div class="mb-3">
                            <label class="form-label">Nome Completo <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                name="novo_nome"
                                class="form-control"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">E-mail <span class="text-danger">*</span></label>
                            <input
                                type="email"
                                name="novo_email"
                                class="form-control"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Usuário <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                name="novo_username"
                                class="form-control"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Senha <span class="text-danger">*</span></label>

                            <div class="position-relative">
                                <input
                                    type="password"
                                    name="novo_pass"
                                    id="novo_pass"
                                    class="form-control pe-5"
                                    required>

                                <button
                                    type="button"
                                    id="toggleNovoPass"
                                    style="
                                        position:absolute;
                                        right:12px;
                                        top:50%;
                                        transform:translateY(-50%);
                                        border:none;
                                        background:none;
                                        padding:0;
                                        color:#6c757d;
                                        cursor:pointer;
                                    ">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Perfil</label>

                            <select
                                name="novo_perfil"
                                class="form-select"
                                required>

                                <option value="user" selected>Usuário</option>
                                <option value="admin">Administrador</option>

                            </select>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            name="adicionar"
                            class="btn btn-primary">
                            Cadastrar
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>

    <div class="modal fade modal-form" id="conviteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">

                <form method="POST">

                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fa-solid fa-key me-2"></i>
                            Gerar Convite
                        </h5>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal">
                        </button>
                    </div>

                    <div class="modal-body">

                        <div class="mb-3">
                            <label class="form-label">Perfil</label>

                            <select
                                name="perfil_convite"
                                class="form-select"
                                required>

                                <option value="user">Usuário</option>
                                <option value="admin">Administrador</option>

                            </select>
                        </div>

                    </div>

                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            name="gerar_convite"
                            class="btn btn-primary">
                            Gerar Código
                        </button>

                    </div>

                </form>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const sucesso = urlParams.get('sucesso');

        if (sucesso) {
            let mensagem = '';

            if (sucesso === 'adicionar') {
                mensagem = 'Usuário cadastrado com sucesso!';
            }

            if (sucesso === 'editar') {
                mensagem = 'Usuário atualizado com sucesso!';
            }

            if (sucesso === 'excluir') {
                mensagem = 'Usuário excluído com sucesso!';
            }

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: mensagem,
                showConfirmButton: false,
                timer: 2200,
                timerProgressBar: true
            });
        }

        const toggleNovoPass = document.getElementById('toggleNovoPass');
        const novoPass = document.getElementById('novo_pass');

        if (toggleNovoPass && novoPass) {

            toggleNovoPass.addEventListener('click', function () {

                const tipoAtual = novoPass.getAttribute('type');

                if (tipoAtual === 'password') {
                    novoPass.setAttribute('type', 'text');

                    this.innerHTML =
                        '<i class="fa-solid fa-eye-slash"></i>';

                } else {
                    novoPass.setAttribute('type', 'password');

                    this.innerHTML =
                        '<i class="fa-solid fa-eye"></i>';
                }

            });

}

        const codigoConvite = new URLSearchParams(window.location.search).get('convite');

        if (codigoConvite) {
            Swal.fire({
                icon: 'success',
                title: 'Convite gerado com sucesso!',
                html: `
                    <p style="margin-bottom: 12px;">
                        Compartilhe este código com o usuário:
                    </p>

                    <div style="
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        gap:10px;
                        margin-top:16px;
                    ">

                        <div style="
                            flex:1;
                            background:#f8f9fa;
                            border:1px solid #dee2e6;
                            border-radius:12px;
                            padding:14px 18px;
                            font-size:1.5rem;
                            font-weight:700;
                            letter-spacing:3px;
                            color:#0d6efd;
                            text-align:center;
                        ">
                            ${codigoConvite}
                        </div>

                        <button
                            id="btnCopiarConvite"
                            type="button"
                            onclick="event.preventDefault(); event.stopPropagation(); return false;"
                            style="
                                width:52px;
                                height:52px;
                                border:none;
                                border-radius:12px;
                                background:#0d6efd;
                                color:white;
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                cursor:pointer;
                                flex-shrink:0;
                            "
                            title="Copiar código"
                        >
                            <i class="fa-solid fa-copy"></i>
                        </button>

                    </div>
                `,
                confirmButtonText: 'Fechar',
                confirmButtonColor: '#0d6efd',

                allowOutsideClick: false,
                allowEscapeKey: true,
                stopKeydownPropagation: false,

                didOpen: () => {
                    const btnCopiar = document.getElementById('btnCopiarConvite');

                    btnCopiar.onclick = async function (e) {
                        e.preventDefault();
                        e.stopPropagation();

                        await navigator.clipboard.writeText(codigoConvite);

                        const toast = document.createElement('div');

                        toast.innerHTML = `
                            <div style="
                                position: fixed;
                                top: 20px;
                                right: 20px;
                                background: #198754;
                                color: white;
                                padding: 12px 18px;
                                border-radius: 10px;
                                box-shadow: 0 6px 18px rgba(0,0,0,0.15);
                                z-index: 99999;
                                font-size: 14px;
                                font-weight: 500;
                            ">
                                Código copiado!
                            </div>
                        `;

                        document.body.appendChild(toast);

                        setTimeout(() => {
                            toast.remove();
                        }, 1800);

                        return false;
                    };
                }
            });
        }
    
        const editarModal = document.getElementById('editarModal');

        editarModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;

            const cod = button.getAttribute('data-cod');
            const nome = button.getAttribute('data-nome');
            const email = button.getAttribute('data-email');
            const username = button.getAttribute('data-username');

            document.getElementById('edit_cod_usuario').value = cod;
            document.getElementById('edit_nome').value = nome;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_pass').value = '';
        });

        const btnSalvarEdicao = document.getElementById('btnSalvarEdicao');

        if (btnSalvarEdicao) {
            btnSalvarEdicao.addEventListener('click', function () {

                Swal.fire({
                    title: 'Salvar alterações?',
                    text: 'Deseja atualizar os dados deste usuário?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Salvar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d'
                }).then((result) => {

                    if (result.isConfirmed) {
                        btnSalvarEdicao.closest('form').submit();
                    }

                });
            });
        }

        document.querySelectorAll('.btn-excluir').forEach(function(botao) {
            botao.addEventListener('click', function(e) {
                e.preventDefault();

                const url = this.getAttribute('href');

                Swal.fire({
                    title: 'Excluir usuário?',
                    text: 'Essa ação não poderá ser desfeita.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sim, excluir',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = url;
                    }
                });
            });
        });

    </script>

</body>
</html>
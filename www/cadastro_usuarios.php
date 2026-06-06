<?php
require_once("conexao.php");

    $mensagem = "";

    if ($_SERVER["REQUEST_METHOD"] == "POST") {

        $nome = trim($_POST["nome"] ?? '');
        $email = trim($_POST["email"] ?? '');
        $usuario = trim($_POST["usuario"] ?? '');
        $senha = trim($_POST["senha"] ?? '');
        $confirmar_senha = trim($_POST["confirmar_senha"] ?? '');
        $codigo_convite = trim($_POST["codigo_convite"] ?? '');

        if (
            $nome == "" ||
            $email == "" ||
            $usuario == "" ||
            $senha == "" ||
            $confirmar_senha == "" ||
            $codigo_convite == ""
        ) {

            $mensagem = "Preencha todos os campos.";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $mensagem = "Informe um e-mail válido.";

        } else {

            /* ============================================================
               1. Validação do Convite
            ============================================================ */
            $sqlConvite = "SELECT * FROM convite_usuario WHERE codigo = ? AND usado = 0 LIMIT 1";
            $stmtConvite = mysqli_prepare($conexao_bd, $sqlConvite);
            
            if ($stmtConvite) {
                mysqli_stmt_bind_param($stmtConvite, "s", $codigo_convite);
                mysqli_stmt_execute($stmtConvite);
                $resultadoConvite = mysqli_stmt_get_result($stmtConvite);

                if (mysqli_num_rows($resultadoConvite) == 0) {
                    $mensagem = "Código de convite inválido ou já utilizado.";
                } elseif ($senha !== $confirmar_senha) {
                    $mensagem = "As senhas não coincidem.";
                } elseif (
                    strlen($senha) < 6 ||
                    !preg_match('/[a-z]/', $senha) ||
                    !preg_match('/[A-Z]/', $senha) ||
                    !preg_match('/[0-9]/', $senha) ||
                    !preg_match('/[^A-Za-z0-9]/', $senha)
                ) {
                    $mensagem = "A senha deve ser forte: mínimo 6 caracteres, com maiúscula, minúscula, número e símbolo.";
                } else {

                    $convite = mysqli_fetch_assoc($resultadoConvite);
                    $perfilUsuario = $convite["perfil"];
                    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

                    /* ============================================================
                       2. Verificação de Duplicidade
                    ============================================================ */
                    $verifica = "SELECT * FROM usuario WHERE username = ? OR email = ?";
                    $stmtVerifica = mysqli_prepare($conexao_bd, $verifica);
                    
                    if ($stmtVerifica) {
                        mysqli_stmt_bind_param($stmtVerifica, "ss", $usuario, $email);
                        mysqli_stmt_execute($stmtVerifica);
                        $resultadoDuplicidade = mysqli_stmt_get_result($stmtVerifica);

                        if (mysqli_num_rows($resultadoDuplicidade) > 0) {
                            $mensagem = "Usuário ou e-mail já cadastrado.";
                        } else {

                            /* ============================================================
                               3. Inserção do Novo Usuário
                            ============================================================ */
                            $sqlInsert = "INSERT INTO usuario (nome, email, username, pass, perfil) VALUES (?, ?, ?, ?, ?)";
                            $stmtInsert = mysqli_prepare($conexao_bd, $sqlInsert);

                            if ($stmtInsert) {
                                mysqli_stmt_bind_param($stmtInsert, "sssss", $nome, $email, $usuario, $senhaHash, $perfilUsuario);
                                
                                if (mysqli_stmt_execute($stmtInsert)) {

                                    /* ============================================================
                                       4. Queima do Convite
                                    ============================================================ */
                                    $sqlUpdateConvite = "UPDATE convite_usuario SET usado = 1 WHERE codigo = ?";
                                    $stmtUpdateConvite = mysqli_prepare($conexao_bd, $sqlUpdateConvite);
                                    
                                    if ($stmtUpdateConvite) {
                                        mysqli_stmt_bind_param($stmtUpdateConvite, "s", $codigo_convite);
                                        mysqli_stmt_execute($stmtUpdateConvite);
                                        mysqli_stmt_close($stmtUpdateConvite);
                                    }

                                    header("Location: login.php?sucesso=cadastro");
                                    exit;

                                } else {
                                    $mensagem = "Erro ao cadastrar usuário.";
                                }
                                mysqli_stmt_close($stmtInsert);
                            }
                        }
                        mysqli_stmt_close($stmtVerifica);
                    }
                }
                mysqli_stmt_close($stmtConvite);
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>MediAgenda - Cadastro de Usuário</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>

<body>
    <div class="page-login">
        <div class="login-card">
            <div class="login-hero">
                <div class="mb-3">
                    <i class="fa-solid fa-stethoscope fa-2x"></i>
                </div>

                <h1>MediAgenda</h1>

                <p>Crie sua conta para acessar o sistema.</p>
            </div>

            <div class="login-body">

                <?php if($mensagem != "") { ?>
                    <div class="alert alert-danger">
                        <?php echo $mensagem; ?>
                    </div>
                <?php } ?>

                <form method="POST" novalidate>

                    <label class="form-label fw-semibold">Nome completo <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        name="nome"
                        class="form-control mb-3"
                        placeholder="Digite seu nome completo"
                        value="<?php echo htmlspecialchars($nome ?? ''); ?>"
                        required>

                    <label class="form-label fw-semibold">E-mail <span class="text-danger">*</span></label>
                    <input
                        type="email"
                        name="email"
                        class="form-control mb-3"
                        placeholder="Digite seu e-mail"
                        value="<?php echo htmlspecialchars($email ?? ''); ?>"
                        required>

                    <label class="form-label fw-semibold">Usuário <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        name="usuario"
                        class="form-control mb-3"
                        placeholder="Escolha um nome de usuário"
                        value="<?php echo htmlspecialchars($usuario ?? ''); ?>"
                        required>

                    <label class="form-label fw-semibold">Senha <span class="text-danger">*</span></label>
                    <div class="position-relative mb-2">
                        <input
                            type="password"
                            name="senha"
                            id="senha"
                            class="form-control pe-5"
                            placeholder="Digite sua senha"
                            required>

                        <button
                            type="button"
                            onclick="toggleSenha('senha', this)"
                            class="btn toggle-password-btn position-absolute top-50 end-0 translate-middle-y border-0 bg-transparent me-2">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>

                    <div class="mt-2 mb-3">
                        <div class="progress" style="height: 8px; border-radius: 999px;">
                            <div
                                id="barraSenha"
                                class="progress-bar"
                                role="progressbar"
                                style="width: 0%; border-radius: 999px;">
                            </div>
                        </div>
                        <small id="textoForcaSenha" class="text-muted"></small>
                    </div>

                    <label class="form-label fw-semibold">Confirmar senha <span class="text-danger">*</span></label>
                    <div class="position-relative mb-3">
                        <input
                            type="password"
                            name="confirmar_senha"
                            id="confirmar_senha"
                            class="form-control pe-5"
                            placeholder="Digite a senha novamente"
                            required>

                        <button
                            type="button"
                            onclick="toggleSenha('confirmar_senha', this)"
                            class="btn toggle-password-btn position-absolute top-50 end-0 translate-middle-y border-0 bg-transparent me-2">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Código de convite <span class="text-danger">*</span></label>

                        <input
                            type="text"
                            name="codigo_convite"
                            class="form-control"
                            placeholder="Informe o código recebido"
                            value="<?= htmlspecialchars($_POST['codigo_convite'] ?? '') ?>"
                            required>
                    </div>    

                    <button type="submit" class="btn btn-primary btn-auth w-100">
                        <i class="fa-solid fa-user-plus me-2"></i>
                        Cadastrar
                    </button>

                    <a href="login.php" class="btn btn-outline-primary btn-auth w-100 mt-2">
                        <i class="fa-solid fa-arrow-left me-2"></i>
                        Voltar ao Login
                    </a>

                </form>
            </div>
        </div>
    </div>
    <script>
        function toggleSenha(idCampo, botao) {
            const campo = document.getElementById(idCampo);
            const icone = botao.querySelector("i");

            if (campo.type === "password") {
                campo.type = "text";
                icone.classList.remove("fa-eye");
                icone.classList.add("fa-eye-slash");
            } else {
                campo.type = "password";
                icone.classList.remove("fa-eye-slash");
                icone.classList.add("fa-eye");
            }
        }

        const campoSenha = document.getElementById("senha");
        const barraSenha = document.getElementById("barraSenha");
        const textoForcaSenha = document.getElementById("textoForcaSenha");

        campoSenha.addEventListener("input", function () {
            const senha = campoSenha.value;
            let pontos = 0;

            if (senha.length >= 6) pontos++;
            if (/[a-z]/.test(senha)) pontos++;
            if (/[A-Z]/.test(senha)) pontos++;
            if (/[0-9]/.test(senha)) pontos++;
            if (/[^A-Za-z0-9]/.test(senha)) pontos++;

            if (senha.length === 0) {
                barraSenha.style.width = "0%";
                barraSenha.style.backgroundColor = "";
                textoForcaSenha.innerText = "";
                return;
            }

            if (pontos <= 2) {
                barraSenha.style.width = "33%";
                barraSenha.style.backgroundColor = "#dc3545";
                textoForcaSenha.innerText = "Senha fraca";
            }
            else if (pontos <= 4) {
                barraSenha.style.width = "66%";
                barraSenha.style.backgroundColor = "#ffc107";
                textoForcaSenha.innerText = "Senha média";
            }
            else {
                barraSenha.style.width = "100%";
                barraSenha.style.backgroundColor = "#198754";
                textoForcaSenha.innerText = "Senha forte";
            }
        });

    </script>
</body>
</html>
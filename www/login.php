<?php
session_start();
if (isset($_SESSION['cod_usuario']) && intval($_SESSION['cod_usuario']) > 0) {
    header('Location: principal.php');
    exit;
}
$loginError = isset($_GET['erro']) && $_GET['erro'] === 'login';
$cadastroSucesso = isset($_GET['sucesso']) && $_GET['sucesso'] === 'cadastro';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediAgenda - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>
    <main class="page-login">
        <section class="login-card">
            <div class="login-hero">
                <div class="mb-3">
                    <i class="fa-solid fa-stethoscope fa-2x"></i>
                </div>
                <h1>MediAgenda</h1>
                <p>Entre com seu usuário e senha.</p>
            </div>
            <div class="login-body">
                <form action="cadastrobanco.php" method="POST" onsubmit="return validateForm()">
                    <div class="mb-3">
                        <label for="usuario" class="form-label">Usuário</label>
                        <input type="text" id="usuario" name="usuario" class="form-control" placeholder="Digite seu usuário">
                    </div>
                    
                    <div class="mb-4">
                        <label for="senha" class="form-label">Senha</label>
                        <div class="password-wrapper">
                            <input type="password" id="senha" name="senha" class="form-control" placeholder="Digite sua senha">
                            <button type="button" class="toggle-password-btn" onclick="togglePassword()" title="Mostrar senha">
                                <i class="fa-solid fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 btn-auth">
                        <i class="fa-solid fa-right-to-bracket me-2"></i>Entrar no sistema
                    </button>
                    <a href="cadastro_usuarios.php" class="btn btn-outline-primary btn-auth w-100 mt-2">
                        <i class="fa-solid fa-user-plus me-2"></i>
                        Criar Conta
                    </a>
                </form>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        var loginError = <?php echo json_encode($loginError ? true : false); ?>;
        var cadastroSucesso = <?php echo json_encode($cadastroSucesso ? true : false); ?>;

        if (loginError) {
            Swal.fire({
                icon: 'error',
                title: 'Falha no login',
                text: 'Usuário ou senha incorretos. Tente novamente.',
                confirmButtonText: 'Entendi'
            });
        }
        if (cadastroSucesso) {
            Swal.fire({
                icon: 'success',
                title: 'Cadastro concluído',
                text: 'Faça login para continuar.',
                confirmButtonText: 'Entendi',
                confirmButtonColor: '#0d6efd',
                customClass: {
                    popup: 'swal-mediagenda-popup',
                    confirmButton: 'swal-mediagenda-button'
                }
            });
        }

        function validateForm() {
            var usuario = document.getElementById('usuario').value.trim();
            var senha = document.getElementById('senha').value.trim();

            if (usuario === '') {
                Swal.fire({
                    icon: 'error',
                    title: 'Preencha o usuário',
                    text: 'Informe o usuário antes de continuar.',
                    confirmButtonText: 'Ok'
                });
                return false;
            }
            if (senha === '') {
                Swal.fire({
                    icon: 'error',
                    title: 'Preencha a senha',
                    text: 'Informe a senha antes de continuar.',
                    confirmButtonText: 'Ok'
                });
                return false;
            }
            return true;
        }

        // Função para alternar a visibilidade da senha
        function togglePassword() {
            var senhaInput = document.getElementById('senha');
            var eyeIcon = document.getElementById('eyeIcon');
            
            if (senhaInput.type === 'password') {
                senhaInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                senhaInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
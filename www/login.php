<?php
session_start();
if (isset($_SESSION['cod_usuario']) && intval($_SESSION['cod_usuario']) > 0) {
    header('Location: principal.php');
    exit;
}
$loginError = isset($_GET['erro']) && $_GET['erro'] === 'login';
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
    <style>
        :root {
            --bg-primary: #0d6efd;
            --bg-secondary: #084298;
            --surface: #fff;
            --text: #1f2d3d;
            --text-muted: #6c757d;
        }
        * {
            box-sizing: border-box;
        }
        html,
        body {
            height: 100%;
            margin: 0;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #eef6ff 0%, #dbe9ff 100%);
            color: var(--text);
        }
        .page-login {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            max-height: 80vh;
            border: 0;
            border-radius: 24px;
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.12);
            overflow: hidden;
            background: var(--surface);
            display: flex;
            flex-direction: column;
        }
        .login-hero {
            padding: 1.4rem 1.4rem;
            background: linear-gradient(135deg, var(--bg-primary), var(--bg-secondary));
            color: #fff;
            text-align: center;
        }
        .login-hero h1 {
            margin-bottom: 0.4rem;
            font-size: 1.7rem;
            letter-spacing: 0.01em;
        }
        .login-hero p {
            margin: 0;
            opacity: 0.92;
            font-size: 0.94rem;
            line-height: 1.5;
        }
        .login-body {
            padding: 1.2rem 1.25rem 1.4rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .form-control {
            border-radius: 12px;
            padding: 0.85rem 1rem;
            border: 1px solid #d5dbe8;
            background: #f8fafd;
        }
        .form-control:focus {
            border-color: var(--bg-primary);
            box-shadow: 0 0 0 0.12rem rgba(13, 110, 253, 0.16);
        }
        .btn-auth {
            border-radius: 14px;
            padding: 0.85rem 1rem;
            font-weight: 600;
            font-size: 0.98rem;
        }
        .login-footer {
            margin-top: 1.25rem;
            padding-bottom: 1rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .login-footer a {
            color: var(--bg-primary);
            text-decoration: none;
        }
        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
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
                        <input type="password" id="senha" name="senha" class="form-control" placeholder="Digite sua senha">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-auth">
                        <i class="fa-solid fa-right-to-bracket me-2"></i>Entrar no sistema
                    </button>
                </form>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        var loginError = <?php echo json_encode($loginError ? true : false); ?>;
        if (loginError) {
            Swal.fire({
                icon: 'error',
                title: 'Falha no login',
                text: 'Usuário ou senha incorretos. Tente novamente.',
                confirmButtonText: 'Entendi'
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
    </script>
</body>
</html>

<?php
session_start();
require_once("conexao.php");

if (!isset($_SESSION["cod_usuario"])) {
    header("Location: login.php");
    exit;
}

$cod_usuario = $_SESSION["cod_usuario"];
$mensagem = "";

if (isset($_POST["alterar_senha"])) {

    $novaSenha = trim($_POST["nova_senha"] ?? '');
    $confirmarSenha = trim($_POST["confirmar_senha"] ?? '');

    if ($novaSenha == "" || $confirmarSenha == "") {

        $mensagem = "Preencha todos os campos.";

    } elseif ($novaSenha !== $confirmarSenha) {

        $mensagem = "As senhas não coincidem.";

    } elseif (
        strlen($novaSenha) < 6 ||
        !preg_match('/[a-z]/', $novaSenha) ||
        !preg_match('/[A-Z]/', $novaSenha) ||
        !preg_match('/[0-9]/', $novaSenha) ||
        !preg_match('/[^A-Za-z0-9]/', $novaSenha)
    ) {

        $mensagem = "A senha deve ser forte: mínimo 6 caracteres, com maiúscula, minúscula, número e símbolo.";

    } else {

        $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);

        /* ============================================================
           (Prepared Statement)
        ============================================================ */
        $sql = "UPDATE usuario SET pass = ? WHERE cod_usuario = ?";
        $stmt = mysqli_prepare($conexao_bd, $sql);

        if ($stmt) {
            // "si" indica que vamos passar uma String (o hash) e um Integer (o id do usuário)
            mysqli_stmt_bind_param($stmt, "si", $senhaHash, $cod_usuario);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header("Location: config_usuarios.php?sucesso=1");
                exit;
            } else {
                $mensagem = "Erro ao alterar senha.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $mensagem = "Erro interno de comunicação com o banco de dados.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Configurações - MediAgenda</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>

<body class="bg-light">

    <div
        class="modal fade show modal-form"
        id="modalSenha"
        tabindex="-1"
        style="display:block; background: rgba(0,0,0,.45);"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <form method="POST" novalidate>

                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fa-solid fa-key me-2"></i>
                            Alterar Senha
                        </h5>

                        <a
                            href="principal.php"
                            class="btn-close"
                        ></a>
                    </div>

                    <div class="modal-body">
                        <?php if ($mensagem != "") { ?>
                            <div class="alert alert-danger mb-3">
                                <?= $mensagem ?>
                            </div>
                        <?php } ?>

                        <div class="mb-3">
                            <label class="form-label">Nova senha <span class="text-danger">*</span></label>

                            <div class="password-wrapper">
                                <input
                                    type="password"
                                    name="nova_senha"
                                    id="nova_senha"
                                    class="form-control">

                                <button
                                    type="button"
                                    id="toggleNovaSenha">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>

                            <div class="barra-senha">
                                <div
                                    class="forca-senha"
                                    id="barraSenha">
                                </div>
                            </div>

                            <small id="textoForcaSenha" class="text-muted"></small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirmar nova senha <span class="text-danger">*</span></label>

                            <div class="password-wrapper">
                                <input
                                    type="password"
                                    name="confirmar_senha"
                                    id="confirmar_senha"
                                    class="form-control">

                                <button
                                    type="button"
                                    id="toggleConfirmarSenha">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <a
                            href="principal.php"
                            class="btn btn-secondary">
                            Cancelar
                        </a>

                        <button
                            type="submit"
                            name="alterar_senha"
                            class="btn btn-primary">
                            Salvar senha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>

        // Verifica se a URL tem ?sucesso=1 para mostrar o alerta
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('sucesso') === '1') {
            Swal.fire({
                icon: 'success',
                title: 'Senha atualizada!',
                text: 'Sua nova senha foi salva com sucesso.',
                confirmButtonColor: '#0d6efd',
                confirmButtonText: 'Entendi'
            }).then(() => {
                // Limpa a URL para não mostrar o alerta de novo ao recarregar a página
                window.history.replaceState(null, null, window.location.pathname);
            });
        }

        function alternarSenha(campoId, botaoId) {

            const campo = document.getElementById(campoId);
            const botao = document.getElementById(botaoId);

            if (campo.type === 'password') {
                campo.type = 'text';
                botao.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
            } else {
                campo.type = 'password';
                botao.innerHTML = '<i class="fa-solid fa-eye"></i>';
            }

        }

        document.getElementById('toggleNovaSenha').addEventListener('click', function() {
            alternarSenha('nova_senha', 'toggleNovaSenha');
        });

        document.getElementById('toggleConfirmarSenha').addEventListener('click', function() {
            alternarSenha('confirmar_senha', 'toggleConfirmarSenha');
        });

        const campoSenha = document.getElementById('nova_senha');
        const barraSenha = document.getElementById('barraSenha');
        const textoForcaSenha = document.getElementById('textoForcaSenha');
        
        campoSenha.addEventListener('input', function() {

            const senha = this.value;

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
<?php
    require_once("conexao.php");
    $usuario = isset($_POST["usuario"]) ? trim($_POST["usuario"]) : '';
    $senha = isset($_POST["senha"]) ? trim($_POST["senha"]) : '';

    // Zerar as sessões por segurança antes de tentar um novo login
    session_start();
    unset($_SESSION["cod_usuario"]); 

    if ($usuario !== '' && $senha !== '') {
        
        $sql = "SELECT * FROM usuario WHERE username = ?";
        $stmt = mysqli_prepare($conexao_bd, $sql);

        if ($stmt) {
            // "s" indica que vamos injetar uma String (texto)
            mysqli_stmt_bind_param($stmt, "s", $usuario);
            mysqli_stmt_execute($stmt);
            
            $result = mysqli_stmt_get_result($stmt);

            if ($result && $consulta = mysqli_fetch_assoc($result)) {
                $cod_usuario = $consulta['cod_usuario'];
                $password    = $consulta['pass'];

                // Verificação do Hash da senha
                if (password_verify($senha, $password)) {
                    $_SESSION["cod_usuario"] = $cod_usuario;
                    
                    mysqli_stmt_close($stmt);
                    mysqli_close($conexao_bd);
                    
                    header("Location: principal.php");
                    exit;
                }
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Se chegar até aqui (usuário em branco, senha incorreta, query falhou), bloqueia o acesso.
    if (isset($conexao_bd)) {
        mysqli_close($conexao_bd);
    }
    header("Location: login.php?erro=login");
    exit;
?>
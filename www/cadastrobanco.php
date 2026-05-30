<?php
    require_once("conexao.php");
    $usuario = isset($_POST["usuario"]) ? trim($_POST["usuario"]) : '';
    $senha = isset($_POST["senha"]) ? trim($_POST["senha"]) : '';

    //zerar as sessões:
    session_start();
    $_SESSION["cod_usuario"] = "";

    if ($usuario !== '' && $senha !== '') {
        $usuarioEsc = mysqli_real_escape_string($conexao_bd, $usuario);
        $sql = "SELECT * FROM usuario WHERE username = '" . $usuarioEsc . "'";

        $result = mysqli_query($conexao_bd, $sql);
        if ($result === false) {
            header("Location: login.php?erro=login");
            exit;
        }

        if ($consulta = mysqli_fetch_assoc($result)) {
            $cod_usuario = $consulta['cod_usuario'];
            $nome        = $consulta['nome'];
            $password    = $consulta['pass'];

            if (strtoupper(trim($senha)) === strtoupper(trim($password))) {
                $_SESSION["cod_usuario"] = $cod_usuario;
                header("Location: principal.php");
                exit;
            }
        }
    }

    header("Location: login.php?erro=login");
    exit;
?>
<?php
    if (gethostbyname('mediagenda_mysql') !== 'mediagenda_mysql') {
        $host_bd = "mediagenda_mysql";
        $login_bd = "root";        // Usuário do Docker
        $password_bd = "root123";  // Senha do root definida no docker-compose.yml
    } else {
        $host_bd = "localhost";
        $login_bd = "root";        // Usuário padrão do XAMPP
        $password_bd = "";         // Senha padrão do XAMPP
    }

    $nome_bd = "labdbprog2";
    $port = 3306;

    $conexao_bd = mysqli_connect($host_bd, $login_bd, $password_bd, $nome_bd, $port);

    if (!$conexao_bd) {
        die("Falha na conexão com o banco de dados: " . mysqli_connect_error());
    }
?>
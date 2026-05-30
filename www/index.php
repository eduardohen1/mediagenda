<?php
session_start();

if (isset($_SESSION['cod_usuario']) && intval($_SESSION['cod_usuario']) > 0) {
    header('Location: principal.php');
} else {
    header('Location: login.php');
}
exit;

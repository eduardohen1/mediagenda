<?php
/* ============================================================
   cancelar_agendamento.php
   Endpoint chamado via fetch() pelo principal.php para
   cancelar (status = 'Cancelado') um agendamento pelo id.
============================================================ */

session_start();

// Garante que a resposta será sempre JSON
header('Content-Type: application/json; charset=utf-8');

/* ============================================================
   VALIDAÇÃO DA REQUISIÇÃO E AUTENTICAÇÃO
============================================================ */
// Bloqueia se o usuário não estiver logado
if(!isset($_SESSION['cod_usuario'])){
    http_response_code(401);
    echo json_encode(array('sucesso' => false, 'mensagem' => 'Não autorizado. Faça login para continuar.'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('sucesso' => false, 'mensagem' => 'Método não permitido.'));
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(array('sucesso' => false, 'mensagem' => 'ID inválido.'));
    exit;
}

/* ============================================================
   CONEXÃO COM O BANCO DE DADOS
============================================================ */
require_once("conexao.php");

/* ============================================================
   CANCELAMENTO DO AGENDAMENTO (Exclusão Lógica + Prepared Statement)
============================================================ */

$sql  = "UPDATE agendamentos SET status = 'Cancelado' WHERE id = ?";
$stmt = mysqli_prepare($conexao_bd, $sql);

if ($stmt) {
    // "i" = integer (ID numérico)
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    // Verifica se alguma linha foi realmente alterada no banco
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo json_encode(array('sucesso' => true));
    } else {
        http_response_code(404);
        echo json_encode(array('sucesso' => false, 'mensagem' => 'Agendamento não encontrado ou já estava cancelado.'));
    }

    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(array('sucesso' => false, 'mensagem' => 'Erro interno ao processar o banco de dados.'));
}

mysqli_close($conexao_bd);
?>
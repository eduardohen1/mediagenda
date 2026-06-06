<?php
require_once("conexao.php");

// Define que o retorno deste arquivo será no formato JSON (padrão de APIs)
header('Content-Type: application/json; charset=utf-8');

$medico_id = isset($_GET['medico_id']) ? intval($_GET['medico_id']) : 0;
$especialidades = array();

if ($medico_id > 0) {
    /* ============================================================
       PREPARED STATEMENT
       Busca as especialidades vinculadas a este médico específico
    ============================================================ */
    $sql = "SELECT e.id, e.nome 
            FROM especialidades e 
            INNER JOIN medico_especialidades me ON me.especialidade_id = e.id 
            WHERE me.medico_id = ?
            ORDER BY e.nome ASC";
            
    $stmt = mysqli_prepare($conexao_bd, $sql);
    
    if ($stmt) {
        // "i" garante que o parâmetro seja tratado estritamente como um número inteiro
        mysqli_stmt_bind_param($stmt, "i", $medico_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $especialidades[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// Imprime o array do PHP convertido em um JSON legível para o JavaScript
echo json_encode($especialidades);
exit;
?>
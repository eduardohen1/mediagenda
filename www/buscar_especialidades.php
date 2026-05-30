<?php
require_once("conexao.php");

// Define que o retorno deste arquivo será no formato JSON (padrão de APIs)
header('Content-Type: application/json');

$medico_id = isset($_GET['medico_id']) ? intval($_GET['medico_id']) : 0;
$especialidades = array();

if ($medico_id > 0) {
    // Busca as especialidades vinculadas a este médico específico na tabela intermediária
    $sql = "SELECT e.id, e.nome 
            FROM especialidades e 
            INNER JOIN medico_especialidades me ON me.especialidade_id = e.id 
            WHERE me.medico_id = $medico_id
            ORDER BY e.nome ASC";
            
    $result = mysqli_query($conexao_bd, $sql);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $especialidades[] = $row;
        }
    }
}

// Imprime o array do PHP convertido em um JSON legível para o JavaScript
echo json_encode($especialidades);
exit;
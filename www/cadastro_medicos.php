<?php
require_once("conexao.php"); // importar o conexao.php para esta página

session_start();
if (!isset($_SESSION['cod_usuario'])) {
    header("Location: login.php");
    exit;
}

$cod_usuario = intval($_SESSION['cod_usuario']);
$nomeUsuario = "";
$emailUsuario = "";
$perfilUsuario = "";
$pageError = '';

/* ============================================================
   PREPARED STATEMENT: Sessão do Usuário
============================================================ */
$sql = "SELECT * FROM usuario WHERE cod_usuario = ?";
$stmt = mysqli_prepare($conexao_bd, $sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $cod_usuario);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && $consulta = mysqli_fetch_assoc($result)) {
        $nomeUsuario  = $consulta['nome'];
        $emailUsuario = $consulta['email'];
        $perfilUsuario = $consulta["perfil"];
    } elseif ($result === false) {
        $pageError = mysqli_error($conexao_bd);
    }
    mysqli_stmt_close($stmt);
}

/* ============================================================
   cadastro_medicos.php - Cadastro de Médicos
============================================================ */
$operadorNome  = $nomeUsuario;
$operadorEmail = $emailUsuario;

/* ============================================================
   PROCESSAMENTO DE AÇÕES (POST)
   Integração com o banco de dados para criar, editar e excluir médicos
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = isset($_POST['acao']) ? trim($_POST['acao']) : '';
    $acao = strtolower($acao);
    $redirect = 'cadastro_medicos.php';

    try {
        if ($acao === 'novo' || $acao === 'editar') {
            $nome             = trim($_POST['nome'] ?? '');
            $crm              = trim($_POST['crm'] ?? '');
            $telefone         = trim($_POST['telefone'] ?? '');
            $email            = trim($_POST['email'] ?? '');
            $status           = isset($_POST['status']) && $_POST['status'] === 'Inativo' ? 'Inativo' : 'Ativo';
            
            // --- CAPTURA DE ESPECIALIDADES (ARRAY) ---
            $especialidades   = isset($_POST['especialidades']) ? $_POST['especialidades'] : [];

            if ($nome === '') {
                throw new Exception('Nome do médico é obrigatório.');
            }
            if ($crm === '') {
                throw new Exception('CRM do médico é obrigatório.');
            }
            if (empty($especialidades)) {
                throw new Exception('Selecione pelo menos uma especialidade.');
            }

            if ($acao === 'novo') {
                /* ============================================================
                   INSERT: Novo Médico e Tabela Pivô
                ============================================================ */
                $sql = "INSERT INTO medicos (nome, crm, telefone, email, status) VALUES (?, ?, ?, ?, ?)";
                $stmtInsert = mysqli_prepare($conexao_bd, $sql);
                
                if (!$stmtInsert) {
                    throw new Exception('Erro ao preparar query de inserção.');
                }

                mysqli_stmt_bind_param($stmtInsert, "sssss", $nome, $crm, $telefone, $email, $status);
                if (!mysqli_stmt_execute($stmtInsert)) {
                    throw new Exception('Não foi possível cadastrar o médico. ' . mysqli_error($conexao_bd));
                }
                
                $novoMedicoId = mysqli_insert_id($conexao_bd);
                mysqli_stmt_close($stmtInsert);
                
                // --- INSERT NA TABELA PIVÔ ---
                $sqlPivo = "INSERT INTO medico_especialidades (medico_id, especialidade_id) VALUES (?, ?)";
                $stmtPivo = mysqli_prepare($conexao_bd, $sqlPivo);
                
                if ($stmtPivo) {
                    foreach ($especialidades as $espId) {
                        $espIdInt = intval($espId);
                        mysqli_stmt_bind_param($stmtPivo, "ii", $novoMedicoId, $espIdInt);
                        mysqli_stmt_execute($stmtPivo);
                    }
                    mysqli_stmt_close($stmtPivo);
                }
                
                $redirect .= '?alert=success&acao=novo';
                
            } elseif ($acao === 'editar') {
                $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
                if ($id <= 0) {
                    throw new Exception('Médico inválido para edição.');
                }
                if ($status === 'Inativo') {
                    /* ============================================================
                       SELECT: Checar agendamentos futuros
                    ============================================================ */
                    $sqlCount = "SELECT COUNT(*) AS total FROM agendamentos WHERE medico_id = ? AND data >= CURDATE()";
                    $stmtCount = mysqli_prepare($conexao_bd, $sqlCount);
                    if ($stmtCount) {
                        mysqli_stmt_bind_param($stmtCount, "i", $id);
                        mysqli_stmt_execute($stmtCount);
                        $resultCount = mysqli_stmt_get_result($stmtCount);
                        if ($resultCount) {
                            $rowCount = mysqli_fetch_assoc($resultCount);
                            if (intval($rowCount['total']) > 0) {
                                throw new Exception('Não é possível inativar este médico enquanto houver agendamentos futuros.');
                            }
                        }
                        mysqli_stmt_close($stmtCount);
                    } else {
                        throw new Exception('Não foi possível verificar agendamentos futuros.');
                    }
                }

                /* ============================================================
                VALIDAÇÃO: NÃO PERMITE REMOVER ESPECIALIDADE COM
                AGENDAMENTOS FUTUROS VINCULADOS
                ============================================================ */

                // Especialidades atuais do médico
                $especialidadesAtuais = [];

                $sqlEspAtuais = "
                    SELECT especialidade_id
                    FROM medico_especialidades
                    WHERE medico_id = ?
                ";

                $stmtEspAtuais = mysqli_prepare($conexao_bd, $sqlEspAtuais);

                if ($stmtEspAtuais) {

                    mysqli_stmt_bind_param($stmtEspAtuais, "i", $id);
                    mysqli_stmt_execute($stmtEspAtuais);

                    $resEspAtuais = mysqli_stmt_get_result($stmtEspAtuais);

                    while ($rowEsp = mysqli_fetch_assoc($resEspAtuais)) {
                        $especialidadesAtuais[] = intval($rowEsp['especialidade_id']);
                    }

                    mysqli_stmt_close($stmtEspAtuais);
                }

                // Descobre quais especialidades estão sendo removidas
                $especialidadesNovas = array_map('intval', $especialidades);

                $especialidadesRemovidas = array_diff(
                    $especialidadesAtuais,
                    $especialidadesNovas
                );

                // Para cada especialidade removida, verifica agendamentos futuros
                foreach ($especialidadesRemovidas as $especialidadeRemovida) {

                    $sqlAgendamento = "
                        SELECT
                            e.nome AS especialidade
                        FROM agendamentos a
                        INNER JOIN especialidades e
                            ON e.id = a.especialidade_id
                        WHERE a.medico_id = ?
                        AND a.especialidade_id = ?
                        AND a.data >= CURDATE()
                        AND a.status <> 'Cancelado'
                        LIMIT 1
                    ";

                    $stmtAgendamento = mysqli_prepare(
                        $conexao_bd,
                        $sqlAgendamento
                    );

                    if ($stmtAgendamento) {

                        mysqli_stmt_bind_param(
                            $stmtAgendamento,
                            "ii",
                            $id,
                            $especialidadeRemovida
                        );

                        mysqli_stmt_execute($stmtAgendamento);

                        $resAgendamento = mysqli_stmt_get_result(
                            $stmtAgendamento
                        );

                        if (
                            $resAgendamento &&
                            $rowAgendamento = mysqli_fetch_assoc($resAgendamento)
                        ) {

                            mysqli_stmt_close($stmtAgendamento);

                            throw new Exception(
                                'Não é possível remover a especialidade "' .
                                $rowAgendamento['especialidade'] .
                                '". Existem agendamentos futuros vinculados a ela.'
                            );
                        }

                        mysqli_stmt_close($stmtAgendamento);
                    }
                }
                
                /* ============================================================
                   UPDATE: Edição do Médico
                ============================================================ */
                $sql = "UPDATE medicos SET nome = ?, crm = ?, telefone = ?, email = ?, status = ? WHERE id = ?";
                $stmtUpdate = mysqli_prepare($conexao_bd, $sql);
                
                if (!$stmtUpdate) {
                    throw new Exception('Erro ao preparar query de atualização.');
                }

                mysqli_stmt_bind_param($stmtUpdate, "sssssi", $nome, $crm, $telefone, $email, $status, $id);
                if (!mysqli_stmt_execute($stmtUpdate)) {
                    throw new Exception('Não foi possível atualizar o médico. ' . mysqli_error($conexao_bd));
                }
                mysqli_stmt_close($stmtUpdate);

                // --- ATUALIZAÇÃO DA TABELA PIVÔ ---
                // 1. Deleta os antigos
                $sqlDelPivo = "DELETE FROM medico_especialidades WHERE medico_id = ?";
                $stmtDel = mysqli_prepare($conexao_bd, $sqlDelPivo);
                if ($stmtDel) {
                    mysqli_stmt_bind_param($stmtDel, "i", $id);
                    mysqli_stmt_execute($stmtDel);
                    mysqli_stmt_close($stmtDel);
                }

                // 2. Insere os novos
                $sqlInsPivo = "INSERT INTO medico_especialidades (medico_id, especialidade_id) VALUES (?, ?)";
                $stmtIns = mysqli_prepare($conexao_bd, $sqlInsPivo);
                if ($stmtIns) {
                    foreach ($especialidades as $espId) {
                        $espIdInt = intval($espId);
                        mysqli_stmt_bind_param($stmtIns, "ii", $id, $espIdInt);
                        mysqli_stmt_execute($stmtIns);
                    }
                    mysqli_stmt_close($stmtIns);
                }

                $redirect .= '?alert=success&acao=editar';
            }
        } elseif ($acao === 'excluir') {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            if ($id <= 0) {
                throw new Exception('Médico inválido para exclusão.');
            }

            /* ============================================================
               SELECT & DELETE: Exclusão do Médico
            ============================================================ */
            $sqlCheck = "SELECT COUNT(*) AS total FROM agendamentos WHERE medico_id = ?";
            $stmtCheck = mysqli_prepare($conexao_bd, $sqlCheck);
            if ($stmtCheck) {
                mysqli_stmt_bind_param($stmtCheck, "i", $id);
                mysqli_stmt_execute($stmtCheck);
                $resultCheck = mysqli_stmt_get_result($stmtCheck);
                if ($resultCheck) {
                    $refRow = mysqli_fetch_assoc($resultCheck);
                    if (intval($refRow['total']) > 0) {
                        throw new Exception('Não é possível excluir este médico porque ele possui agendamentos vinculados. Use editar para inativar.');
                    }
                }
                mysqli_stmt_close($stmtCheck);
            }

            $sqlDel = "DELETE FROM medicos WHERE id = ?";
            $stmtDel = mysqli_prepare($conexao_bd, $sqlDel);
            if ($stmtDel) {
                mysqli_stmt_bind_param($stmtDel, "i", $id);
                if (!mysqli_stmt_execute($stmtDel)) {
                    throw new Exception('Não foi possível excluir o médico. ' . mysqli_error($conexao_bd));
                }
                mysqli_stmt_close($stmtDel);
            }
            $redirect .= '?alert=success&acao=excluir';
        }
    } catch (Exception $e) {
        $redirect .= '?alert=error&message=' . rawurlencode($e->getMessage());
    }

    header("Location: " . $redirect);
    exit;
}

/* ============================================================
   FILTROS DE BUSCA (Dinâmicos)
============================================================ */
$filtroNome            = trim(isset($_GET['nome'])            ? $_GET['nome']            : '');
$filtroEspecialidadeId = trim(isset($_GET['especialidade_id']) ? $_GET['especialidade_id'] : '');
$filtroStatus          = trim(isset($_GET['status'])          ? $_GET['status']          : '');

/* ============================================================
   [SEGURANÇA] CONSULTA DE MÉDICOS (Construção Dinâmica da Query)
============================================================ */
$medicos = array();
$where = array();
$params = array();
$types = "";

if ($filtroNome !== '') {
    $where[] = "m.nome LIKE ?";
    $params[] = "%" . $filtroNome . "%";
    $types .= "s";
}

if ($filtroEspecialidadeId !== '' && intval($filtroEspecialidadeId) > 0) {
    $where[] = "m.id IN (SELECT medico_id FROM medico_especialidades WHERE especialidade_id = ?)";
    $params[] = intval($filtroEspecialidadeId);
    $types .= "i";
}

if ($filtroStatus !== '') {
    $where[] = "m.status = ?";
    $params[] = $filtroStatus;
    $types .= "s";
}

$sqlConsulta = "SELECT m.id, m.nome, m.crm, m.telefone, m.email, m.status, "
             . "GROUP_CONCAT(DISTINCT e.id SEPARATOR ',') AS especialidades_ids, "
             . "GROUP_CONCAT(DISTINCT e.nome SEPARATOR ', ') AS especialidades_nomes, "
             . "COUNT(DISTINCT a.id) AS agendamento_count, "
             . "SUM(CASE WHEN a.data >= CURDATE() THEN 1 ELSE 0 END) AS future_agendamento_count "
             . "FROM medicos m "
             . "LEFT JOIN medico_especialidades me ON me.medico_id = m.id "
             . "LEFT JOIN especialidades e ON e.id = me.especialidade_id "
             . "LEFT JOIN agendamentos a ON a.medico_id = m.id";

if (count($where) > 0) {
    $sqlConsulta .= " WHERE " . implode(' AND ', $where);
}
$sqlConsulta .= " GROUP BY m.id, m.nome, m.crm, m.telefone, m.email, m.status ORDER BY m.nome ASC";

$stmtMedicos = mysqli_prepare($conexao_bd, $sqlConsulta);
if ($stmtMedicos) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmtMedicos, $types, ...$params);
    }
    mysqli_stmt_execute($stmtMedicos);
    $resultMedicos = mysqli_stmt_get_result($stmtMedicos);
    
    if ($resultMedicos) {
        while ($row = mysqli_fetch_assoc($resultMedicos)) {
            $medicos[] = $row;
        }
    }
    mysqli_stmt_close($stmtMedicos);
} else {
    if ($pageError === '') {
        $pageError = mysqli_error($conexao_bd);
    }
}

/* ============================================================
   ESPECIALIDADES DISPONÍVEIS
============================================================ */
$especialidades = array();
$sqlEsp = "SELECT id, nome, cbo FROM especialidades ORDER BY nome";
$resultEsp = mysqli_query($conexao_bd, $sqlEsp);
if ($resultEsp) {
    while ($row = mysqli_fetch_assoc($resultEsp)) {
        $especialidades[] = $row;
    }
} else {
    if ($pageError === '') {
        $pageError = mysqli_error($conexao_bd);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediAgenda - Cadastro de Médicos</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="img/favicon.ico">

    <!-- ================ CDNs ================ -->
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- ==================================================
         NAVBAR SUPERIOR
    ================================================== -->
    <nav class="navbar-topo d-flex align-items-center justify-content-between px-3">
        <div class="d-flex align-items-center gap-2">
            <button class="btn-sanduiche" id="btnSanduiche" title="Menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <a class="navbar-brand mb-0 d-flex align-items-center" href="principal.php">
                <i class="fa-solid fa-stethoscope"></i>
                <span>MediAgenda</span>
            </a>
        </div>

        <div class="dropdown">
            <button class="operador-toggle" type="button" id="dropdownOperador" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fa-solid fa-circle-user"></i>
                <span class="d-none d-md-inline"><?php echo htmlspecialchars($operadorNome) ?></span>
                <i class="fa-solid fa-chevron-down" style="font-size: 0.75rem;"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-operador" aria-labelledby="dropdownOperador">
                <li><a class="dropdown-item" href="#"><i class="fa-solid fa-user"></i><?php echo htmlspecialchars($operadorNome) ?></a></li>
                <li><a class="dropdown-item" href="#"><i class="fa-solid fa-envelope"></i><?php echo htmlspecialchars($operadorEmail) ?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="config_usuarios.php"><i class="fa-solid fa-gear"></i>Configurações</a></li>
                <li><a class="dropdown-item" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i>Sair</a></li>
            </ul>
        </div>
    </nav>

    <!-- ==================================================
         SIDEBAR LATERAL
    ================================================== -->
    <aside class="sidebar" id="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="principal.php"><i class="fa-solid fa-calendar-days"></i> Calendário</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="cadastro_agendas.php"><i class="fa-solid fa-calendar-plus"></i> Agendamentos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link ativo" href="cadastro_medicos.php"><i class="fa-solid fa-user-doctor"></i> Cadastro de Médicos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="cadastro_especialidades.php"><i class="fa-solid fa-list-check"></i> Cadastro de Especialidades</a>
            </li>
            <?php if ($perfilUsuario == "admin") { ?>
                <li class="nav-item">
                    <a class="nav-link" href="admin_usuarios.php">
                        <i class="fa-solid fa-users"></i>
                        Administração de Usuários
                    </a>
                </li>
            <?php } ?>
        </ul>
    </aside>

    <!-- Overlay para mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ==================================================
         CONTEÚDO PRINCIPAL
    ================================================== -->
    <main class="conteudo-principal" id="conteudoPrincipal">

        <!-- Cabeçalho da página -->
        <div class="page-header">
            <h2><i class="fa-solid fa-user-doctor"></i> Cadastro de Médicos</h2>
            <button id="btnNovoMedico" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalFormMedico">
                <i class="fa-solid fa-plus me-1"></i> Novo Médico
            </button>
        </div>

        <div class="card-pagina">
            <div class="card-titulo"><i class="fa-solid fa-magnifying-glass"></i> Filtros</div>
            <form method="GET" action="cadastro_medicos.php">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm" id="filtroNome"
                               name="nome" placeholder="Nome do médico"
                               value="<?php echo htmlspecialchars($filtroNome) ?>">
                    </div>
                    <div class="col-md-4">
                        <select class="form-select form-select-lg" id="filtroEspecialidade" name="especialidade_id">
                            <option value="">Todas Especialidades</option>
                            <?php foreach ($especialidades as $esp): ?>
                                <option value="<?php echo intval($esp['id']) ?>"
                                    <?php echo ($filtroEspecialidadeId === strval($esp['id'])) ? 'selected' : '' ?>>
                                    <?php echo htmlspecialchars($esp['nome'] . ' (' . $esp['cbo'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-lg" id="filtroStatus" name="status">
                            <option value="">Todos Status</option>
                            <option value="Ativo"   <?php echo ($filtroStatus === 'Ativo')   ? 'selected' : '' ?>>Ativo</option>
                            <option value="Inativo" <?php echo ($filtroStatus === 'Inativo') ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Filtrar
                    </button>
                    <a href="cadastro_medicos.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-xmark me-1"></i> Limpar
                    </a>
                </div>
            </form>
        </div>

        <!-- ============================================================
             TABELA DE MÉDICOS
             Os dados são carregados do banco de dados e exibidos conforme filtros.
        ============================================================ -->
        <div class="card-pagina">
            <div class="card-titulo d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-table-list"></i> Médicos</span>
                <span id="contadorRegistros" class="text-muted" style="font-size:0.82rem; font-weight:400;">
                    <?php echo count($medicos) ?> registro(s) encontrado(s)
                </span>
            </div>

            <div class="table-responsive">
                <table class="tabela-medicos">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nome</th>
                            <th>CRM</th>
                            <th>Especialidades</th>
                            <th>Telefone</th>
                            <th>E-mail</th>
                            <th>Status</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($medicos)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="fa-solid fa-user-xmark me-2"></i>Nenhum médico encontrado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($medicos as $med):
                                // Iniciais para o avatar
                                $partes   = explode(' ', $med['nome']);
                                $iniciais = '';
                                foreach ($partes as $p) {
                                    $letra = ltrim($p, 'Dr. Dra. ');
                                    if ($letra !== '') {
                                        $iniciais .= mb_strtoupper(mb_substr($letra, 0, 1));
                                        if (mb_strlen($iniciais) === 2) break;
                                    }
                                }

                                // Define classe do badge conforme status
                                if ($med['status'] === 'Ativo') {
                                    $classeBadge = 'badge-ativo';
                                } else {
                                    $classeBadge = 'badge-inativo';
                                }
                            ?>
                            <tr>
                                <td class="text-muted"><?php echo $med['id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="avatar-medico"><?php echo htmlspecialchars($iniciais) ?></span>
                                        <?php echo htmlspecialchars($med['nome']) ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($med['crm']) ?></td>
                                <td><?php echo htmlspecialchars($med['especialidades_nomes'] ?? '') ?></td>
                                <td><?php echo htmlspecialchars($med['telefone']) ?></td>
                                <td><?php echo htmlspecialchars($med['email']) ?></td>
                                <td><span class="badge-status <?php echo $classeBadge ?>"><?php echo htmlspecialchars($med['status']) ?></span></td>
                                <td class="text-center" style="white-space:nowrap;">
                                    <button class="btn btn-sm btn-icon-sm btn-outline-primary btn-editar"
                                            title="Editar"
                                            data-id="<?php echo $med['id'] ?>"
                                            data-nome="<?php echo htmlspecialchars($med['nome']) ?>"
                                            data-crm="<?php echo htmlspecialchars($med['crm']) ?>"
                                            data-especialidades-nomes="<?php echo htmlspecialchars($med['especialidades_nomes'] ?? '') ?>"
                                            data-especialidades-ids="<?php echo htmlspecialchars($med['especialidades_ids'] ?? '') ?>"
                                            data-telefone="<?php echo htmlspecialchars($med['telefone']) ?>"
                                            data-email="<?php echo htmlspecialchars($med['email']) ?>"
                                            data-status="<?php echo htmlspecialchars($med['status']) ?>"
                                            data-future-agendamentos="<?php echo intval($med['future_agendamento_count']) ?>">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <?php if (intval($med['agendamento_count']) === 0): ?>
                                        <button class="btn btn-sm btn-icon-sm btn-outline-danger btn-excluir"
                                                title="Excluir médico"
                                                data-id="<?php echo $med['id'] ?>"
                                                data-nome="<?php echo htmlspecialchars($med['nome']) ?>">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-icon-sm btn-outline-secondary" type="button" disabled
                                                title="Este médico possui agendamentos vinculados e não pode ser excluído">
                                            <i class="fa-solid fa-link"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ============================================================
                 PAGINAÇÃO
                 Nesta versão exibe todos os registros; paginação pode ser adicionada futuramente.
            ============================================================ -->
            <div class="d-flex justify-content-end mt-3">
                <nav aria-label="Paginação">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item disabled"><a class="page-link" href="#">&laquo;</a></li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item disabled"><a class="page-link" href="#">&raquo;</a></li>
                    </ul>
                </nav>
            </div>
        </div>

    </main>

    <!-- ==================================================
         MODAL — NOVO / EDITAR MÉDICO
         O formulário envia os dados via POST para cadastro_medicos.php.
    ================================================== -->
    <div class="modal fade modal-form" id="modalFormMedico" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFormTitulo">
                        <i class="fa-solid fa-user-plus me-2"></i>Novo Médico
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <form id="formMedico" action="cadastro_medicos.php" method="POST">
                    <input type="hidden" name="acao" id="formAcao" value="novo">
                    <input type="hidden" name="id"   id="formId"   value="">
                    <input type="hidden" id="formHasFutureAppointments" value="0">

                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="formNome">Nome completo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="formNome" name="nome"
                                       placeholder="Ex: Dr. Carlos Lima" required>
                            </div>
                            <div class="col-md-4">
                                <label for="formCrm">CRM <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="formCrm" name="crm"
                                       placeholder="Ex: CRM/SP 12345" required>
                            </div>
                            
                            <div class="col-12">
                                <label for="formEspecialidadeInput">Especialidades <span class="text-danger">*</span></label>
                                <div class="position-relative">
                                    <input type="text" class="form-control" id="formEspecialidadeInput" placeholder="Digite para buscar especialidades" autocomplete="off">
                                    <div class="especialidades-suggestions d-none" id="especialidadesSuggestions"></div>
                                </div>
                                <div id="formEspecialidadesTags" class="especialidades-multi mt-2"></div>
                                <div id="formEspecialidadesHidden"></div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="formTelefone">Telefone</label>
                                <input type="text" class="form-control" id="formTelefone" name="telefone"
                                       placeholder="(00) 00000-0000">
                            </div>
                            <div class="col-md-8">
                                <label for="formEmail">E-mail</label>
                                <input type="email" class="form-control" id="formEmail" name="email"
                                       placeholder="medico@clinica.com">
                            </div>
                            <div class="col-md-4">
                                <label for="formStatus">Status</label>
                                <select class="form-select" id="formStatus" name="status">
                                    <option value="Ativo">Ativo</option>
                                    <option value="Inativo">Inativo</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>

                        <button type="button" class="btn btn-primary" onclick="salvarMedico()">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <form id="formExcluir" action="cadastro_medicos.php" method="POST" style="display:none;">
        <input type="hidden" name="acao" value="excluir">
        <input type="hidden" name="id" id="formExcluirId" value="">
    </form>

    <!-- ================ SCRIPTS ================ -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // ==================================================
        // TOGGLE DA SIDEBAR (responsivo)
        // ==================================================
        var btnSanduiche      = document.getElementById('btnSanduiche');
        var sidebar           = document.getElementById('sidebar');
        var conteudoPrincipal = document.getElementById('conteudoPrincipal');
        var sidebarOverlay    = document.getElementById('sidebarOverlay');

        btnSanduiche.addEventListener('click', function() {
            if (window.innerWidth <= 991.98) {
                sidebar.classList.toggle('aberta');
                sidebarOverlay.classList.toggle('ativo');
            } else {
                sidebar.classList.toggle('oculta');
                conteudoPrincipal.classList.toggle('expandido');
            }
        });
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('aberta');
            sidebarOverlay.classList.remove('ativo');
        });
        window.addEventListener('resize', function() {
            if (window.innerWidth > 991.98) {
                sidebar.classList.remove('aberta');
                sidebarOverlay.classList.remove('ativo');
            }
        });

        // ==================================================
        // INSTÂNCIA ÚNICA DO MODAL E FLAG DE MODO
        // ==================================================
        var modalFormMedicoEl = document.getElementById('modalFormMedico');
        var modalFormMedico   = new bootstrap.Modal(modalFormMedicoEl);
        var modoEdicao        = false;
        var btnNovoMedico     = document.getElementById('btnNovoMedico');
        var formEspecialidadeInput = document.getElementById('formEspecialidadeInput');
        var especialidadesTags     = document.getElementById('formEspecialidadesTags');
        var especialidadesHidden   = document.getElementById('formEspecialidadesHidden');
        var especialidadesSuggestions = document.getElementById('especialidadesSuggestions');
        var urlParams         = new URLSearchParams(window.location.search);
        var pageAlert         = urlParams.get('alert');
        var pageAction        = urlParams.get('acao');
        var serverErrorMessage = <?php echo json_encode($pageError); ?>;
        var formHasFutureAppointments = document.getElementById('formHasFutureAppointments');
        var especialidadesData = <?php echo json_encode($especialidades); ?>;
        var selectedEspecialidades = [];

        function renderEspecialidades() {
            especialidadesTags.innerHTML = '';
            especialidadesHidden.innerHTML = '';

            if (selectedEspecialidades.length === 0) {
                var placeholder = document.createElement('div');
                placeholder.className = 'text-muted';
                placeholder.style.fontSize = '0.88rem';
                placeholder.textContent = 'Nenhuma especialidade selecionada.';
                especialidadesTags.appendChild(placeholder);
            }

            selectedEspecialidades.forEach(function(item) {
                var tag = document.createElement('span');
                tag.className = 'especialidade-tag';
                tag.innerHTML = '<span>' + item.nome + ' (' + item.cbo + ')</span>' +
                    '<button type="button" aria-label="Remover ' + item.nome + '">&times;</button>';
                tag.querySelector('button').addEventListener('click', function() {
                    removeEspecialidade(item.id);
                });
                especialidadesTags.appendChild(tag);

                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'especialidades[]';
                hidden.value = item.id;
                especialidadesHidden.appendChild(hidden);
            });
        }

        function updateSuggestions(filter) {
            var query = filter.trim().toLowerCase();
            var available = especialidadesData.filter(function(item) {
                return !selectedEspecialidades.some(function(sel) { return sel.id === item.id; });
            });
            if (query !== '') {
                available = available.filter(function(item) {
                    return item.nome.toLowerCase().indexOf(query) !== -1 || item.cbo.toLowerCase().indexOf(query) !== -1;
                }).slice(0, 8);
            }
            especialidadesSuggestions.innerHTML = '';

            if (available.length === 0) {
                var noResult = document.createElement('div');
                noResult.className = 'sem-resultado';
                noResult.textContent = 'Nenhuma especialidade encontrada.';
                especialidadesSuggestions.appendChild(noResult);
                especialidadesSuggestions.classList.remove('d-none');
                return;
            }

            available.forEach(function(item) {
                var option = document.createElement('button');
                option.type = 'button';
                option.textContent = item.nome + ' (' + item.cbo + ')';
                option.addEventListener('click', function() {
                    addEspecialidade(item.id);
                });
                especialidadesSuggestions.appendChild(option);
            });
            especialidadesSuggestions.classList.remove('d-none');
        }

        function addEspecialidade(id) {
            if (selectedEspecialidades.some(function(item) { return item.id === id; })) {
                formEspecialidadeInput.value = '';
                updateSuggestions('');
                return;
            }
            var item = especialidadesData.find(function(item) { return item.id === id; });
            if (!item) return;
            selectedEspecialidades.push(item);
            formEspecialidadeInput.value = '';
            renderEspecialidades();
            updateSuggestions('');
        }

        function removeEspecialidade(id) {
            selectedEspecialidades = selectedEspecialidades.filter(function(item) { return item.id !== id; });
            renderEspecialidades();
            updateSuggestions(formEspecialidadeInput.value);
        }

        function setSelectedEspecialidades(ids) {
            selectedEspecialidades = especialidadesData.filter(function(item) {
                return ids.indexOf(String(item.id)) !== -1;
            });
            renderEspecialidades();
        }

        function resetEspecialidades() {
            selectedEspecialidades = [];
            if (formEspecialidadeInput) {
                formEspecialidadeInput.value = '';
            }
            renderEspecialidades();
            especialidadesSuggestions.classList.add('d-none');
        }

        if (formEspecialidadeInput) {
            formEspecialidadeInput.addEventListener('input', function() {
                updateSuggestions(this.value);
            });
            formEspecialidadeInput.addEventListener('focus', function() {
                updateSuggestions(this.value);
            });
            formEspecialidadeInput.addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    var available = especialidadesData.filter(function(item) {
                        return !selectedEspecialidades.some(function(sel) { return sel.id === item.id; });
                    }).filter(function(item) {
                        var query = formEspecialidadeInput.value.trim().toLowerCase();
                        return query !== '' && (item.nome.toLowerCase().indexOf(query) !== -1 || item.cbo.toLowerCase().indexOf(query) !== -1);
                    });
                    if (available.length > 0) {
                        addEspecialidade(available[0].id);
                    }
                } else if (event.key === 'Backspace' && formEspecialidadeInput.value === '') {
                    if (selectedEspecialidades.length > 0) {
                        removeEspecialidade(selectedEspecialidades[selectedEspecialidades.length - 1].id);
                    }
                }
            });
            document.addEventListener('click', function(event) {
                if (!formEspecialidadeInput.contains(event.target) && !especialidadesSuggestions.contains(event.target)) {
                    especialidadesSuggestions.classList.add('d-none');
                }
            });
        }

        if (serverErrorMessage) {
            Swal.fire({
                icon: 'error',
                title: 'Ops, algo deu errado!',
                text: serverErrorMessage,
                confirmButtonText: 'Entendi'
            });
        } else if (pageAlert === 'success') {
            var message = '';
            if (pageAction === 'novo') {
                message = 'Médico cadastrado com sucesso!';
            } else if (pageAction === 'editar') {
                message = 'Médico atualizado com sucesso!';
            } else if (pageAction === 'excluir') {
                message = 'Médico excluído com sucesso!';
            }
            if (message) {
                Swal.fire({
                    icon: 'success',
                    title: 'Tudo certo!',
                    text: message,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2200,
                    timerProgressBar: true
                });
            }
        } else if (pageAlert === 'error') {
            var errorMessage = urlParams.get('message') || 'Ocorreu um erro inesperado.';
            errorMessage = decodeURIComponent(errorMessage);
            Swal.fire({
                icon: 'error',
                title: 'Ops, algo deu errado!',
                text: errorMessage,
                confirmButtonText: 'Entendi'
            });
        }

        if (btnNovoMedico) {
            btnNovoMedico.addEventListener('click', function() {
                modoEdicao = false;
                document.getElementById('modalFormTitulo').innerHTML =
                    '<i class="fa-solid fa-user-plus me-2"></i>Novo Médico';
                document.getElementById('formAcao').value = 'novo';
                document.getElementById('formId').value   = '';
                document.getElementById('formMedico').reset();
                resetEspecialidades();
            });
        }

        // Reseta o formulário apenas quando aberto no modo "Novo"
        modalFormMedicoEl.addEventListener('show.bs.modal', function() {
            if (!modoEdicao) {
                document.getElementById('modalFormTitulo').innerHTML =
                    '<i class="fa-solid fa-user-plus me-2"></i>Novo Médico';
                document.getElementById('formAcao').value = 'novo';
                document.getElementById('formId').value   = '';
                document.getElementById('formMedico').reset();
                resetEspecialidades();
            }
            modoEdicao = false;
        });

        // ==================================================
        // EVENT DELEGATION — Editar e Excluir
        // ==================================================
        document.querySelector('.tabela-medicos').addEventListener('click', function(e) {
            var btnEditar  = e.target.closest('.btn-editar');
            var btnExcluir = e.target.closest('.btn-excluir');

            if (btnEditar) {
                modoEdicao = true;
                document.getElementById('modalFormTitulo').innerHTML =
                    '<i class="fa-solid fa-pen me-2"></i>Editar Médico';
                document.getElementById('formAcao').value           = 'editar';
                document.getElementById('formId').value             = btnEditar.dataset.id;
                document.getElementById('formNome').value           = btnEditar.dataset.nome;
                document.getElementById('formCrm').value            = btnEditar.dataset.crm;
                
                // --- SELEÇÃO DE MÚLTIPLAS ESPECIALIDADES ---
                var espIds = btnEditar.dataset.especialidadesIds ? btnEditar.dataset.especialidadesIds.split(',') : [];
                setSelectedEspecialidades(espIds);

                document.getElementById('formTelefone').value       = btnEditar.dataset.telefone;
                document.getElementById('formEmail').value          = btnEditar.dataset.email;
                document.getElementById('formStatus').value         = btnEditar.dataset.status;
                document.getElementById('formHasFutureAppointments').value = btnEditar.dataset.futureAgendamentos || '0';
                modalFormMedico.show();
            }

            if (btnExcluir) {
                Swal.fire({
                    title: 'Excluir médico?',
                    html: 'Deseja excluir o cadastro de <strong>' + btnExcluir.dataset.nome + '</strong>?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor:  '#6c757d',
                    confirmButtonText:  'Sim, excluir',
                    cancelButtonText:   'Voltar'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        document.getElementById('formExcluirId').value = btnExcluir.dataset.id;
                        document.getElementById('formExcluir').submit();
                    }
                });
            }
        });

        // ==================================================
        // FUNÇÃO PRINCIPAL: salvar médico
        // Realiza envio direto ao servidor via POST
        // ==================================================
        function salvarMedico() {
            var form = document.getElementById('formMedico');
            if (selectedEspecialidades.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Especialidades obrigatórias',
                    text: 'Selecione pelo menos uma especialidade para este médico.',
                    confirmButtonColor: '#0d6efd'
                });
                formEspecialidadeInput.focus();
                return;
            }
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            if (document.getElementById('formAcao').value === 'editar') {
                var status = document.getElementById('formStatus').value;
                var futureCount = parseInt(formHasFutureAppointments.value, 10) || 0;
                if (status === 'Inativo' && futureCount > 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Atenção',
                        text: 'Não é possível inativar este médico enquanto houver agendamentos futuros.',
                        confirmButtonColor: '#0d6efd'
                    });
                    return;
                }
            }
            form.submit();
        }

    </script>
</body>
</html>
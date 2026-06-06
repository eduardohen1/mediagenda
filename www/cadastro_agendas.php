<?php
session_start();
require_once("conexao.php"); // importar o conexao.php para esta página

if(!isset($_SESSION['cod_usuario'])){
    header("Location: login.php");
    exit;
}
$cod_usuario = $_SESSION['cod_usuario'];
$nomeUsuario = "";
$perfilUsuario = "";
$pageError = '';

/* ============================================================
   PREPARED STATEMENT: Dados do Usuário Logado
============================================================ */
$sql = "SELECT * FROM usuario WHERE cod_usuario = ?";
$stmtUser = mysqli_prepare($conexao_bd, $sql);

if ($stmtUser) {
    mysqli_stmt_bind_param($stmtUser, "i", $cod_usuario);
    mysqli_stmt_execute($stmtUser);
    $result = mysqli_stmt_get_result($stmtUser);

    if ($result && $consulta = mysqli_fetch_assoc($result)) {
        $nomeUsuario  = $consulta['nome'];
        $emailUsuario = $consulta['email'];
        $perfilUsuario = $consulta["perfil"];
    } elseif ($result === false) {
        $pageError = mysqli_error($conexao_bd);
    }
    mysqli_stmt_close($stmtUser);
}

$operadorNome  = $nomeUsuario;
$operadorEmail = $emailUsuario;

/* ============================================================
   PROCESSAMENTO DE AÇÕES (POST)
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = isset($_POST['acao']) ? $_POST['acao'] : '';
    $redirect = 'cadastro_agendas.php';

    try {
        if ($acao === 'novo') {
            $paciente         = trim($_POST['paciente'] ?? '');
            $medico_id        = intval($_POST['medico_id'] ?? 0);
            $especialidade_id = intval($_POST['especialidade_id'] ?? 0);
            $data             = trim($_POST['data'] ?? '');
            $diaSemana        = date('w', strtotime($data));

            // ALTERAÇÃO WALKIRIA (Validação de Fim de Semana)
            if ($diaSemana == 0 || $diaSemana == 6) {
                throw new Exception('Não é permitido agendar aos finais de semana.');
            }

            $horario = trim($_POST['horario'] ?? '');
            $status  = trim($_POST['status'] ?? 'Pendente');

            // Validação de horário: apenas entre 07:00 e 17:00 (inclusive)
            $horarioParts = explode(':', $horario);
            if (count($horarioParts) === 2) {
                $horaAgendamento = intval($horarioParts[0]);
                $minutoAgendamento = intval($horarioParts[1]);
                $totalMinutosAgendamento = $horaAgendamento * 60 + $minutoAgendamento;

                $horarioAbertura = 7 * 60;      // 07:00
                $horarioFechamento = 17 * 60;   // 17:00

                if (
                    $totalMinutosAgendamento < $horarioAbertura ||
                    $totalMinutosAgendamento > $horarioFechamento
                ) {
                    throw new Exception('Agendamentos apenas entre 07:00 e 17:00.');
                }
            }

            if (
                $paciente === '' ||
                $medico_id <= 0 ||
                $especialidade_id <= 0 ||
                $data === '' ||
                $horario === ''
            ) {
                throw new Exception('Preencha todos os campos obrigatórios do agendamento.');
            }

            // Verifica status do médico com Prepared Statement
            $sqlMedicoStatus = "SELECT status FROM medicos WHERE id = ?";
            $stmtMed = mysqli_prepare($conexao_bd, $sqlMedicoStatus);

            if ($stmtMed) {
                mysqli_stmt_bind_param($stmtMed, "i", $medico_id);
                mysqli_stmt_execute($stmtMed);

                $resultMedicoStatus = mysqli_stmt_get_result($stmtMed);

                if (!$resultMedicoStatus || mysqli_num_rows($resultMedicoStatus) === 0) {
                    mysqli_stmt_close($stmtMed);
                    throw new Exception('Médico inválido para agendamento.');
                }

                $medicoRow = mysqli_fetch_assoc($resultMedicoStatus);
                mysqli_stmt_close($stmtMed);

                if ($medicoRow['status'] !== 'Ativo') {
                    throw new Exception('Não é permitido agendar para médico inativo.');
                }
            }

            // Verifica conflito de agenda
            $sqlConflito = "
                SELECT id
                FROM agendamentos
                WHERE medico_id = ?
                AND data = ?
                AND horario = ?
                AND status <> 'Cancelado'
                LIMIT 1
            ";

            $stmtConf = mysqli_prepare($conexao_bd, $sqlConflito);

            if ($stmtConf) {
                mysqli_stmt_bind_param(
                    $stmtConf,
                    "iss",
                    $medico_id,
                    $data,
                    $horario
                );

                mysqli_stmt_execute($stmtConf);

                $resultConflito = mysqli_stmt_get_result($stmtConf);

                if ($resultConflito && mysqli_num_rows($resultConflito) > 0) {
                    mysqli_stmt_close($stmtConf);

                    throw new Exception(
                        'Já existe um agendamento para este médico neste dia e horário.'
                    );
                }

                mysqli_stmt_close($stmtConf);
            }

            // Inserção Limpa e Segura
            $sqlInsert = "
                INSERT INTO agendamentos
                (
                    paciente,
                    medico_id,
                    especialidade_id,
                    data,
                    horario,
                    status
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?
                )
            ";

            $stmtIns = mysqli_prepare($conexao_bd, $sqlInsert);

            if ($stmtIns) {
                mysqli_stmt_bind_param(
                    $stmtIns,
                    "siisss",
                    $paciente,
                    $medico_id,
                    $especialidade_id,
                    $data,
                    $horario,
                    $status
                );

                if (!mysqli_stmt_execute($stmtIns)) {
                    mysqli_stmt_close($stmtIns);
                    throw new Exception('Não foi possível cadastrar o agendamento.');
                }

                mysqli_stmt_close($stmtIns);
            }

            $redirect .= '?alert=success&acao=novo';
        } elseif ($acao === 'editar') {
            $id_agenda = intval($_POST['id'] ?? 0);
            // VERIFICA SE A DATA DO AGENDAMENTO É ANTERIOR À DATA ATUAL (NÃO PERMITE EDIÇÃO)
            $sqlVerifica = "
                SELECT
                    data,
                    status,
                    medico_id,
                    especialidade_id
                FROM agendamentos
                WHERE id = ?
            ";
            $stmtVer = mysqli_prepare($conexao_bd, $sqlVerifica);

            if ($stmtVer) {
                mysqli_stmt_bind_param($stmtVer, "i", $id_agenda);
                mysqli_stmt_execute($stmtVer);

                $resultVerifica = mysqli_stmt_get_result($stmtVer);
                $agenda = mysqli_fetch_assoc($resultVerifica);

                mysqli_stmt_close($stmtVer);

                if ($agenda && $agenda['data'] < date('Y-m-d')) {
                    throw new Exception('Não é permitido editar agendamentos anteriores.');
                }
            }

            $paciente         = trim($_POST['paciente'] ?? '');
            $medico_id        = intval($_POST['medico_id'] ?? 0);
            $especialidade_id = intval($_POST['especialidade_id'] ?? 0);
            $data             = trim($_POST['data'] ?? '');

            // ALTERAÇÃO WALKIRIA (Validação de Fim de Semana)
            $diaSemana = date('w', strtotime($data));

            if ($diaSemana == 0 || $diaSemana == 6) {
                throw new Exception('Não é permitido agendar aos finais de semana.');
            }

            $horario = trim($_POST['horario'] ?? '');
            $status  = trim($_POST['status'] ?? 'Pendente');

            // Impede reativar agendamento cancelado se o médico não possuir mais a especialidade
            if (
                $agenda &&
                $agenda['status'] === 'Cancelado' &&
                in_array($status, ['Pendente', 'Confirmado'])
            ) {

                $sqlEspecialidade = "
                    SELECT 1
                    FROM medico_especialidades
                    WHERE medico_id = ?
                    AND especialidade_id = ?
                    LIMIT 1
                ";

                $stmtEsp = mysqli_prepare($conexao_bd, $sqlEspecialidade);

                if ($stmtEsp) {

                    mysqli_stmt_bind_param(
                        $stmtEsp,
                        "ii",
                        $agenda['medico_id'],
                        $agenda['especialidade_id']
                    );

                    mysqli_stmt_execute($stmtEsp);

                    $resEsp = mysqli_stmt_get_result($stmtEsp);

                    if (!$resEsp || mysqli_num_rows($resEsp) === 0) {

                        mysqli_stmt_close($stmtEsp);

                        throw new Exception(
                            'Este agendamento não pode ser reativado porque o médico não possui mais esta especialidade.'
                        );
                    }

                    mysqli_stmt_close($stmtEsp);
                }
            }

            // Validação de horário: apenas entre 07:00 e 17:00 (inclusive)
            $horarioParts = explode(':', $horario);

            if (count($horarioParts) === 2) {
                $horaAgendamento = intval($horarioParts[0]);
                $minutoAgendamento = intval($horarioParts[1]);
                $totalMinutosAgendamento = $horaAgendamento * 60 + $minutoAgendamento;

                $horarioAbertura = 7 * 60;
                $horarioFechamento = 17 * 60;

                if (
                    $totalMinutosAgendamento < $horarioAbertura ||
                    $totalMinutosAgendamento > $horarioFechamento
                ) {
                    throw new Exception('Agendamentos apenas entre 07:00 e 17:00.');
                }
            }

            if ($id_agenda <= 0) {
                throw new Exception('Agendamento inválido para edição.');
            }

            if (
                $paciente === '' ||
                $medico_id <= 0 ||
                $especialidade_id <= 0 ||
                $data === '' ||
                $horario === ''
            ) {
                throw new Exception('Preencha todos os campos obrigatórios do agendamento.');
            }

            // Verifica status do médico
            $sqlMedicoStatus = "SELECT status FROM medicos WHERE id = ?";
            $stmtMed = mysqli_prepare($conexao_bd, $sqlMedicoStatus);

            if ($stmtMed) {
                mysqli_stmt_bind_param($stmtMed, "i", $medico_id);
                mysqli_stmt_execute($stmtMed);

                $resultMedicoStatus = mysqli_stmt_get_result($stmtMed);

                if ($resultMedicoStatus && $medicoRow = mysqli_fetch_assoc($resultMedicoStatus)) {
                    if ($medicoRow['status'] !== 'Ativo') {
                        mysqli_stmt_close($stmtMed);
                        throw new Exception('Não é permitido agendar para médico inativo.');
                    }
                }

                mysqli_stmt_close($stmtMed);
            }

            // Verifica conflito de agenda
            $sqlConflito = "
                SELECT id
                FROM agendamentos
                WHERE medico_id = ?
                AND data = ?
                AND horario = ?
                AND status <> 'Cancelado'
                AND id <> ?
                LIMIT 1
            ";

            $stmtConf = mysqli_prepare($conexao_bd, $sqlConflito);

            if ($stmtConf) {
                mysqli_stmt_bind_param(
                    $stmtConf,
                    "issi",
                    $medico_id,
                    $data,
                    $horario,
                    $id_agenda
                );

                mysqli_stmt_execute($stmtConf);

                $resultConflito = mysqli_stmt_get_result($stmtConf);

                if ($resultConflito && mysqli_num_rows($resultConflito) > 0) {
                    mysqli_stmt_close($stmtConf);

                    throw new Exception(
                        'Já existe um agendamento para este médico neste dia e horário.'
                    );
                }

                mysqli_stmt_close($stmtConf);
            }

            // Executa a Atualização Segura
            $sqlUpdate = "
                UPDATE agendamentos
                SET
                    paciente = ?,
                    medico_id = ?,
                    especialidade_id = ?,
                    data = ?,
                    horario = ?,
                    status = ?
                WHERE id = ?
            ";

            $stmtUpd = mysqli_prepare($conexao_bd, $sqlUpdate);

            if ($stmtUpd) {
                mysqli_stmt_bind_param(
                    $stmtUpd,
                    "siisssi",
                    $paciente,
                    $medico_id,
                    $especialidade_id,
                    $data,
                    $horario,
                    $status,
                    $id_agenda
                );

                if (!mysqli_stmt_execute($stmtUpd)) {
                    mysqli_stmt_close($stmtUpd);
                    throw new Exception('Não foi possível atualizar o agendamento.');
                }

                mysqli_stmt_close($stmtUpd);
            }

            $redirect .= '?alert=success&acao=editar';
            

        } elseif ($acao === 'cancelar') {
            $id_agenda = intval($_POST['id'] ?? 0);
            if ($id_agenda <= 0) {
                throw new Exception('Agendamento inválido para cancelamento.');
            }
            
            $sqlCancel = "UPDATE agendamentos SET status = 'Cancelado' WHERE id = ?";
            $stmtCanc = mysqli_prepare($conexao_bd, $sqlCancel);
            if ($stmtCanc) {
                mysqli_stmt_bind_param($stmtCanc, "i", $id_agenda);
                if (!mysqli_stmt_execute($stmtCanc)) {
                    mysqli_stmt_close($stmtCanc);
                    throw new Exception('Não foi possível cancelar o agendamento.');
                }
                mysqli_stmt_close($stmtCanc);
            }
            $redirect .= '?alert=success&acao=cancelar';
        }
    } catch (Exception $e) {
        $redirect .= '?alert=error&message=' . rawurlencode($e->getMessage());
    }

    header("Location: " . $redirect);
    exit;
}

/* ============================================================
   FILTROS DE BUSCA (Substitui o bloco temporário)
============================================================ */
$filtroPaciente = trim(isset($_GET['paciente']) ? $_GET['paciente'] : '');
$filtroMedico   = trim(isset($_GET['medico'])   ? $_GET['medico']   : '');
$filtroStatus   = trim(isset($_GET['status'])   ? $_GET['status']   : '');
$filtroDataIni  = trim(isset($_GET['data_ini']) ? $_GET['data_ini'] : '');
$filtroDataFim  = trim(isset($_GET['data_fim']) ? $_GET['data_fim'] : '');

$where = array();
$params = array();
$types = "";

if ($filtroPaciente !== '') {
    $where[] = "a.paciente LIKE ?";
    $params[] = "%" . $filtroPaciente . "%";
    $types .= "s";
}
if ($filtroMedico !== '') {
    $where[] = "m.nome = ?";
    $params[] = $filtroMedico;
    $types .= "s";
}
if ($filtroStatus !== '') {
    $where[] = "a.status = ?";
    $params[] = $filtroStatus;
    $types .= "s";
}
if ($filtroDataIni !== '') {
    $where[] = "a.data >= ?";
    $params[] = $filtroDataIni;
    $types .= "s";
}
if ($filtroDataFim !== '') {
    $where[] = "a.data <= ?";
    $params[] = $filtroDataFim;
    $types .= "s";
}

$agendamentos = [];
$sqlBusca = "SELECT a.id, a.data, a.horario, a.paciente, a.status, a.medico_id, a.especialidade_id, 
                    m.nome AS medico, e.nome AS especialidade 
             FROM agendamentos a 
             JOIN medicos m ON a.medico_id = m.id 
             JOIN especialidades e ON a.especialidade_id = e.id";

if (!empty($where)) {
    $sqlBusca .= " WHERE " . implode(" AND ", $where);
}
$sqlBusca .= " ORDER BY a.data ASC, a.horario ASC";

$stmtBusca = mysqli_prepare($conexao_bd, $sqlBusca);
if ($stmtBusca) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmtBusca, $types, ...$params);
    }
    mysqli_stmt_execute($stmtBusca);
    $resultBusca = mysqli_stmt_get_result($stmtBusca);
    if ($resultBusca) {
        while ($row = mysqli_fetch_assoc($resultBusca)) {
            $agendamentos[] = [
                'id'               => $row['id'],
                'data'             => $row['data'],
                'horario'          => date("H:i", strtotime($row['horario'])),
                'paciente'         => $row['paciente'],
                'medico'           => $row['medico'],
                'medico_id'        => $row['medico_id'],
                'especialidade_id' => $row['especialidade_id'],
                'especialidade'    => $row['especialidade'],
                'status'           => $row['status']
            ];
        }
    }
    mysqli_stmt_close($stmtBusca);
} else {
    if ($pageError === '') {
        $pageError = mysqli_error($conexao_bd);
    }
}

/* ============================================================
   MÉDICOS COMPLETO / ATIVOS
============================================================ */
$medicos = [];
$sqlMedicos = "SELECT id, nome FROM medicos ORDER BY nome ASC";
$resMedicos = mysqli_query($conexao_bd, $sqlMedicos);
if ($resMedicos) {
    while ($row = mysqli_fetch_assoc($resMedicos)) {
        $medicos[] = $row;
    }
}

$medicosAtivos = [];
$sqlMedicosAtivos = "SELECT id, nome FROM medicos WHERE status = 'Ativo' ORDER BY nome ASC";
$resMedicosAtivos = mysqli_query($conexao_bd, $sqlMedicosAtivos);
if ($resMedicosAtivos) {
    while ($row = mysqli_fetch_assoc($resMedicosAtivos)) {
        $medicosAtivos[] = $row;
    }
}

/* ============================================================
   CARREGAMENTO SELETIVO DE EDICÃO (Preenchimento do Modal por Query)
============================================================ */
$agendaParaEdicao = null;
$editarId = isset($_GET['editar']) && $_GET['editar'] === '1' ? intval($_GET['id'] ?? 0) : 0;
if ($editarId > 0) {
    $sqlAgenda = "SELECT a.id, a.paciente, a.data, a.horario, a.status, a.medico_id, a.especialidade_id, m.nome AS medico, e.nome AS especialidade " .
                 "FROM agendamentos a " .
                 "JOIN medicos m ON a.medico_id = m.id " .
                 "JOIN especialidades e ON a.especialidade_id = e.id " .
                 "WHERE a.id = ?";
    $stmtAg = mysqli_prepare($conexao_bd, $sqlAgenda);
    if ($stmtAg) {
        mysqli_stmt_bind_param($stmtAg, "i", $editarId);
        mysqli_stmt_execute($stmtAg);
        $resAgenda = mysqli_stmt_get_result($stmtAg);
        if ($resAgenda && $rowAgenda = mysqli_fetch_assoc($resAgenda)) {
            $rowAgenda['horario'] = date("H:i", strtotime($rowAgenda['horario']));
            $agendaParaEdicao = $rowAgenda;
        }
        mysqli_stmt_close($stmtAg);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediAgenda - Cadastro de Agendas</title>

    <link rel="icon" type="image/x-icon" href="img/favicon.ico">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>

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

    <aside class="sidebar" id="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="principal.php"><i class="fa-solid fa-calendar-days"></i> Calendário</a>
            </li>
            <li class="nav-item">
                <a class="nav-link ativo" href="cadastro_agendas.php"><i class="fa-solid fa-calendar-plus"></i> Agendamentos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="cadastro_medicos.php"><i class="fa-solid fa-user-doctor"></i> Cadastro de Médicos</a>
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

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="conteudo-principal" id="conteudoPrincipal">

        <div class="page-header">
            <h2><i class="fa-solid fa-calendar-days"></i> Cadastro de Agendas</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalFormAgenda">
                <i class="fa-solid fa-plus me-1"></i> Novo Agendamento
            </button>
        </div>

        <div class="card-pagina">
            <div class="card-titulo"><i class="fa-solid fa-magnifying-glass"></i> Filtros</div>
            <form method="GET" action="cadastro_agendas.php">
                <div class="row g-3">
                    <div class="col-md-3">
                        <input type="text" class="form-control form-control-sm" id="filtroPaciente"
                               name="paciente" placeholder="Nome do paciente"
                               value="<?php echo htmlspecialchars($filtroPaciente) ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select form-select-lg" id="filtroMedico" name="medico">
                            <option value="">Todos os Médicos</option>
                            <?php foreach ($medicos as $m): ?>
                                <option value="<?php echo htmlspecialchars($m['nome']) ?>"
                                    <?php echo ($filtroMedico === $m['nome']) ? 'selected' : '' ?>>
                                    <?php echo htmlspecialchars($m['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-lg" id="filtroStatus" name="status">
                            <option value="">Todos os Status</option>
                            <option value="Confirmado" <?php echo ($filtroStatus === 'Confirmado') ? 'selected' : '' ?>>Confirmado</option>
                            <option value="Pendente"   <?php echo ($filtroStatus === 'Pendente')   ? 'selected' : '' ?>>Pendente</option>
                            <option value="Cancelado"  <?php echo ($filtroStatus === 'Cancelado')  ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control form-control-sm" id="filtroDataIni"
                               name="data_ini" value="<?php echo htmlspecialchars($filtroDataIni) ?>">
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control form-control-sm" id="filtroDataFim"
                               name="data_fim" value="<?php echo htmlspecialchars($filtroDataFim) ?>">
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Filtrar
                    </button>
                    <a href="cadastro_agendas.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-xmark me-1"></i> Limpar
                    </a>
                </div>
            </form>
        </div>

        <div class="card-pagina">
            <div class="card-titulo d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-table-list"></i> Agendamentos</span>
                <span id="contadorRegistros" class="text-muted" style="font-size:0.82rem; font-weight:400;">
                    <?php echo count($agendamentos) ?> registro(s) encontrado(s)
                </span>
            </div>

            <div class="table-responsive">
                <table class="tabela-agendamentos">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Data</th>
                            <th>Horário</th>
                            <th>Paciente</th>
                            <th>Médico</th>
                            <th>Especialidade</th>
                            <th>Status</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($agendamentos)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="fa-solid fa-calendar-xmark me-2"></i>Nenhum agendamento encontrado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($agendamentos as $ag):
                                // Formata data de YYYY-MM-DD para DD/MM/YYYY
                                $dataFormatada = date('d/m/Y', strtotime($ag['data']));

                                // Define classe do badge conforme status
                                if ($ag['status'] === 'Confirmado') {
                                    $classeBadge = 'badge-confirmado';
                                } elseif ($ag['status'] === 'Pendente') {
                                    $classeBadge = 'badge-pendente';
                                } else {
                                    $classeBadge = 'badge-cancelado';
                                }
                            ?>
                            <tr>
                                <td class="text-muted"><?php echo $ag['id'] ?></td>
                                <td><?php echo $dataFormatada ?></td>
                                <td><?php echo htmlspecialchars($ag['horario']) ?></td>
                                <td><?php echo htmlspecialchars($ag['paciente']) ?></td>
                                <td><?php echo htmlspecialchars($ag['medico']) ?></td>
                                <td><?php echo htmlspecialchars($ag['especialidade']) ?></td>
                                <td><span class="badge-status <?php echo $classeBadge ?>"><?php echo htmlspecialchars($ag['status']) ?></span></td>
                                <td class="text-center" style="white-space:nowrap;">
                                    <?php $agendamentoExpirado = ($ag['data'] < date('Y-m-d')); ?>

                                    <button
                                            class="btn btn-sm btn-icon-sm <?= $agendamentoExpirado ? 'btn-secondary disabled' : 'btn-outline-primary' ?> btn-editar"
                                            title="Editar"
                                            data-id="<?php echo $ag['id'] ?>"
                                            data-paciente="<?php echo htmlspecialchars($ag['paciente']) ?>"
                                            data-medico="<?php echo htmlspecialchars($ag['medico']) ?>"
                                            data-medico-id="<?php echo isset($ag['medico_id']) ? $ag['medico_id'] : '' ?>"
                                            data-especialidade="<?php echo htmlspecialchars($ag['especialidade']) ?>"
                                            data-especialidade-id="<?php echo isset($ag['especialidade_id']) ? $ag['especialidade_id'] : '' ?>"
                                            data-data="<?php echo $ag['data'] ?>"
                                            data-horario="<?php echo htmlspecialchars($ag['horario']) ?>"
                                            data-status="<?php echo htmlspecialchars($ag['status']) ?>">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button class="btn btn-sm btn-icon-sm btn-outline-danger btn-cancelar"
                                            title="Cancelar agendamento"
                                            data-id="<?php echo $ag['id'] ?>"
                                            data-paciente="<?php echo htmlspecialchars($ag['paciente']) ?>">
                                        <i class="fa-solid fa-ban"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

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

    <div class="modal fade modal-form" id="modalFormAgenda" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFormTitulo">
                        <i class="fa-solid fa-calendar-plus me-2"></i>Novo Agendamento
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <form id="formAgenda" action="cadastro_agendas.php" method="POST"> 
                    <input type="hidden" name="acao" id="formAcao" value="novo">
                    <input type="hidden" name="id"   id="formId"   value="">

                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="formPaciente">Paciente <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="formPaciente" 
                                    name="paciente"
                                    placeholder="Nome completo do paciente" required>
                                </div>
                            <div class="col-md-6">
                                <label for="formMedico">Médico <span class="text-danger">*</span></label>
                                <select class="form-select" id="formMedico" 
                                 name="medico_id" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($medicosAtivos as $m): ?>
                                        <option value="<?php echo $m['id'] ?>"><?php echo htmlspecialchars($m['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="formEspecialidade">Especialidade <span class="text-danger">*</span></label>
                                <select class="form-select" id="formEspecialidade" name="especialidade_id" required>
                                    <option value="">Selecione um médico primeiro...</option>
                                </select>
                                </div>
                            <div class="col-md-6">
                                <label for="formData">Data <span class="text-danger">*</span></label>
                                <input type="date" class="form-control"  
                                 id="formData" name="data" required>
                            </div>
                            <div class="col-md-6">
                                <label for="formHorario">Horário <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="formHorario" 
                                name="horario" required>
                            </div>
                            <div class="col-12">
                                <label for="formStatus">Status</label>
                                <select class="form-select" id="formStatus" name="status">
                                    <option value="Pendente">Pendente</option>
                                    <option value="Confirmado">Confirmado</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        <button type="button" class="btn btn-primary" onclick="salvarAgendamento()">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        // ==================================================
        // TOGGLE DA SIDEBAR (responsivo)
        // ==================================================
        var btnSanduiche      = document.getElementById('btnSanduiche');
        var sidebar           = document.getElementById('sidebar');
        var conteudoPrincipal = document.getElementById('conteudoPrincipal');
        var sidebarOverlay    = document.getElementById('sidebarOverlay');
        var urlParams         = new URLSearchParams(window.location.search);
        var pageAlert         = urlParams.get('alert');
        var pageAction        = urlParams.get('acao');
        var serverErrorMessage = <?php echo json_encode($pageError); ?>;
        var agendaParaEdicao  = <?php echo json_encode($agendaParaEdicao); ?>;

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
                message = 'Agendamento cadastrado com sucesso!';
            } else if (pageAction === 'editar') {
                message = 'Agendamento updated com sucesso!';
            } else if (pageAction === 'cancelar') {
                message = 'Agendamento cancelado com sucesso!';
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
        var modalFormAgendaEl = document.getElementById('modalFormAgenda');
        var modalFormAgenda   = new bootstrap.Modal(modalFormAgendaEl);
        var modoEdicao        = false;

        // ==================================================
        // AJAX: BUSCA DE ESPECIALIDADES
        // ==================================================
        var selectMedico = document.getElementById('formMedico');
        var selectEspecialidade = document.getElementById('formEspecialidade');

        function carregarEspecialidades(medicoId, especialidadeSelecionadaId = null) {
            selectEspecialidade.innerHTML = '<option value="">Carregando...</option>';
            if (!medicoId) {
                selectEspecialidade.innerHTML = '<option value="">Selecione um médico primeiro...</option>';
                return;
            }

            fetch('buscar_especialidades.php?medico_id=' + medicoId)
                .then(response => response.json())
                .then(data => {
                    selectEspecialidade.innerHTML = '<option value="">Selecione a especialidade...</option>';
                    if(data.length === 0) {
                        selectEspecialidade.innerHTML = '<option value="">Médico sem especialidade</option>';
                        return;
                    }
                    data.forEach(function(esp) {
                        var option = document.createElement('option');
                        option.value = esp.id;
                        option.textContent = esp.nome;
                        if (especialidadeSelecionadaId && esp.id == especialidadeSelecionadaId) {
                            option.selected = true;
                        }
                        selectEspecialidade.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Erro ao buscar especialidades:', error);
                    selectEspecialidade.innerHTML = '<option value="">Erro na busca</option>';
                });
        }

        if(selectMedico) {
            selectMedico.addEventListener('change', function() {
                carregarEspecialidades(this.value);
            });
        }

        modalFormAgendaEl.addEventListener('show.bs.modal', function() {
            if (!modoEdicao) {
                document.getElementById('modalFormTitulo').innerHTML =
                    '<i class="fa-solid fa-calendar-plus me-2"></i>Novo Agendamento';
                document.getElementById('formAcao').value = 'novo';
                document.getElementById('formId').value   = '';
                document.getElementById('formAgenda').reset();
                document.getElementById('formEspecialidade').innerHTML = '<option value="">Selecione um médico primeiro...</option>';

                var selectStatus = document.getElementById('formStatus');
                var optionCancelado = selectStatus.querySelector('option[value="Cancelado"]');
                if (optionCancelado) {
                    optionCancelado.remove();
                }
            }
            modoEdicao = false;
        });

        function abrirModalEdicaoPorQuery() {
            if (!agendaParaEdicao) {
                return;
            }

            modoEdicao = true;
            document.getElementById('modalFormTitulo').innerHTML =
                '<i class="fa-solid fa-pen me-2"></i>Editar Agendamento';
            document.getElementById('formAcao').value = 'editar';
            document.getElementById('formId').value   = agendaParaEdicao.id;
            document.getElementById('formPaciente').value = agendaParaEdicao.paciente;
            document.getElementById('formData').value      = agendaParaEdicao.data;
            document.getElementById('formHorario').value   = agendaParaEdicao.horario;
            document.getElementById('formMedico').value    = agendaParaEdicao.medico_id;
            carregarEspecialidades(agendaParaEdicao.medico_id, agendaParaEdicao.especialidade_id);

            var selectStatus = document.getElementById('formStatus');
            if (!Array.from(selectStatus.options).some(function(opt) { return opt.value === 'Cancelado'; })) {
                var optionCancelado = document.createElement('option');
                optionCancelado.value = 'Cancelado';
                optionCancelado.textContent = 'Cancelado';
                selectStatus.appendChild(optionCancelado);
            }
            selectStatus.value = agendaParaEdicao.status;
            modalFormAgenda.show();
        }

        abrirModalEdicaoPorQuery();

        // ==================================================
        // EVENT DELEGATION — Editar e Cancelar (cobre linhas dinâmicas)
        // ==================================================
        document.querySelector('.tabela-agendamentos').addEventListener('click', function(e) {
            var btnEditar   = e.target.closest('.btn-editar');
            var btnCancelar = e.target.closest('.btn-cancelar');
           
            if (btnEditar) {
                modoEdicao = true;
                document.getElementById('modalFormTitulo').innerHTML =
                    '<i class="fa-solid fa-pen me-2"></i>Editar Agendamento';
                document.getElementById('formAcao').value          = 'editar';
                document.getElementById('formId').value            = btnEditar.dataset.id;
                document.getElementById('formPaciente').value      = btnEditar.dataset.paciente;
                document.getElementById('formData').value          = btnEditar.dataset.data;
                document.getElementById('formHorario').value       = btnEditar.dataset.horario;
                
                document.getElementById('formMedico').value = btnEditar.dataset.medicoId;
                carregarEspecialidades(btnEditar.dataset.medicoId, btnEditar.dataset.especialidadeId);

                var selectStatus = document.getElementById('formStatus');
                if (!Array.from(selectStatus.options).some(function(opt) { return opt.value === 'Cancelado'; })) {
                    var optionCancelado = document.createElement('option');
                    optionCancelado.value = 'Cancelado';
                    optionCancelado.textContent = 'Cancelado';
                    selectStatus.appendChild(optionCancelado);
                }
                selectStatus.value = btnEditar.dataset.status;
                modalFormAgenda.show();
            }

            if (btnCancelar) {
                Swal.fire({
                    title: 'Cancelar agendamento?',
                    html: 'Deseja cancelar o agendamento de <strong>' + btnCancelar.dataset.paciente + '</strong>?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor:  '#6c757d',
                    confirmButtonText:  'Sim, cancelar',
                    cancelButtonText:   'Voltar'
                }).then(function(result) {
                    var var_acao = "cancelar";
                    var id_agenda = btnCancelar.dataset.id;

                    if (result.isConfirmed) {
                        $.ajax({
                            url: "cadastro_agendas.php",
                            global: false,
                            type: "POST",
                            data: ({id: id_agenda, acao: var_acao}),
                            dataType: "html",
                            async:false,
                            success: function(msg){
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Cancelado!',
                                    text: 'O agendamento foi cancelado.',
                                    confirmButtonColor: '#0d6efd',
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                window.location.href = "cadastro_agendas.php";
                            }
                        });
                    }
                });
            }
        });

        function salvarAgendamento() {
            var form = document.getElementById('formAgenda');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            form.submit();
        }

        // Cria um <tr> completo para a tabela de agendamentos
        function criarLinhaAgendamento(id, dataFmt, horario, paciente, medico, especialidade, status, dataISO) {
            var tr = document.createElement('tr');

            var tdId = document.createElement('td'); tdId.className = 'text-muted'; tdId.textContent = '—';
            var tdDt = document.createElement('td'); tdDt.textContent = dataFmt;
            var tdHr = document.createElement('td'); tdHr.textContent = horario;
            var tdPa = document.createElement('td'); tdPa.textContent = paciente;
            var tdMe = document.createElement('td'); tdMe.textContent = medico;
            var tdEs = document.createElement('td'); tdEs.textContent = especialidade;

            var tdSt  = document.createElement('td');
            var badge = document.createElement('span');
            badge.className   = 'badge-status ' + getBadgeClassAgenda(status);
            badge.textContent = status;
            tdSt.appendChild(badge);

            var tdAc = document.createElement('td');
            tdAc.className        = 'text-center';
            tdAc.style.whiteSpace = 'nowrap';

            var btnEdit = document.createElement('button');
            btnEdit.className              = 'btn btn-sm btn-icon-sm btn-outline-primary btn-editar';
            btnEdit.title                  = 'Editar';
            btnEdit.innerHTML              = '<i class="fa-solid fa-pen"></i>';
            btnEdit.dataset.id             = id;
            btnEdit.dataset.paciente       = paciente;
            btnEdit.dataset.medico         = medico;
            btnEdit.dataset.especialidade  = especialidade;
            btnEdit.dataset.data           = dataISO;
            btnEdit.dataset.horario        = horario;
            btnEdit.dataset.status         = status;

            var btnCan = document.createElement('button');
            btnCan.className        = 'btn btn-sm btn-icon-sm btn-outline-danger btn-cancelar';
            btnCan.title            = 'Cancelar agendamento';
            btnCan.innerHTML        = '<i class="fa-solid fa-ban"></i>';
            btnCan.dataset.id       = id;
            btnCan.dataset.paciente = paciente;

            tdAc.appendChild(btnEdit);
            tdAc.appendChild(btnCan);
            tr.appendChild(tdId); tr.appendChild(tdDt); tr.appendChild(tdHr);
            tr.appendChild(tdPa); tr.appendChild(tdMe); tr.appendChild(tdEs);
            tr.appendChild(tdSt); tr.appendChild(tdAc);
            return tr;
        }

        // Retorna a classe CSS do badge de status
        function getBadgeClassAgenda(status) {
            if (status === 'Confirmado') return 'badge-confirmado';
            if (status === 'Pendente')   return 'badge-pendente';
            return 'badge-cancelado';
        }

        // ALTERAÇÃO WALKIRIA
        document.getElementById('formData').addEventListener('blur', function() {

            if (!this.value) {
                return;
            }

            const partes = this.value.split('-');

            if (partes.length !== 3) {
                return;
            }

            const ano = Number(partes[0]);
            const mes = Number(partes[1]) - 1;
            const dia = Number(partes[2]);

            if (isNaN(ano) || isNaN(mes) || isNaN(dia)) {
                return;
            }

            const dataSelecionada = new Date(ano, mes, dia, 12, 0, 0);
            const diaSemana = dataSelecionada.getDay();

            if (diaSemana === 0 || diaSemana === 6) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Data inválida',
                    text: 'Não é permitido realizar agendamentos aos finais de semana.'
                });

                this.value = '';
                this.focus();
            }
        });

        document.getElementById('formHorario').addEventListener('blur', function () {

            const horarioSelecionado = this.value;

            if (!horarioSelecionado || horarioSelecionado.indexOf(':') === -1) {
                return;
            }

            const partes = horarioSelecionado.split(':');

            if (partes.length !== 2) {
                return;
            }

            const hora = Number(partes[0]);
            const minuto = Number(partes[1]);

            if (isNaN(hora) || isNaN(minuto)) {
                return;
            }

            const totalMinutos = hora * 60 + minuto;

            const horarioAbertura = 7 * 60;
            const horarioFechamento = 17 * 60;

            if (
                totalMinutos < horarioAbertura ||
                totalMinutos > horarioFechamento
            ) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Horário inválido',
                    text: 'Agendamentos apenas entre 07:00 e 17:00.'
                });

                this.value = '';
            }
        });
    </script>
</body>
</html>
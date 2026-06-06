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
   PREPARED STATEMENT 1: Buscar operador logado
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
   CARREGAR LISTA CBO DO JSON (Estrutura estática interna)
============================================================ */
$especialidadesList = [];
$sqlCbo = "SELECT id, nome, cbo FROM especialidades ORDER BY nome";
$resultCbo = mysqli_query($conexao_bd, $sqlCbo);

if ($resultCbo && mysqli_num_rows($resultCbo) > 0) {
    while ($rowCbo = mysqli_fetch_assoc($resultCbo)) {
        $especialidadesList[] = [
            'id' => $rowCbo['id'],
            'cod_cbo' => $rowCbo['cbo'],
            'nome_cbo' => $rowCbo['nome']
        ];
    }
} else {
    // Fallback em caso de erro (mesmo que raro)
    $especialidadesList = []; 
}

/* ============================================================
   PROCESSAMENTO DE AÇÕES (POST)
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = isset($_POST['acao']) ? trim($_POST['acao']) : '';
    $acao = strtolower($acao);
    $redirect = 'cadastro_especialidades.php';

    try {
        if ($acao === 'novo' || $acao === 'editar') {
            $nome   = trim($_POST['nome'] ?? '');
            $cbo    = trim($_POST['cbo'] ?? '');
            $status = isset($_POST['status']) && $_POST['status'] === 'Inativo' ? 'Inativo' : 'Ativo'; 
            $id     = isset($_POST['id']) ? intval($_POST['id']) : 0;
            
            if ($nome === '' || $cbo === '') {
                throw new Exception('Selecione uma especialidade válida da lista CBO.');
            }
            
            /* ============================================================
               PREPARED STATEMENT 2: Verificar duplicidade
            ============================================================ */
            if ($acao === 'editar') {
                $sqlCheck = "SELECT id FROM especialidades WHERE (cbo = ? OR nome = ?) AND id != ?";
                $stmtCheck = mysqli_prepare($conexao_bd, $sqlCheck);
                if ($stmtCheck) {
                    mysqli_stmt_bind_param($stmtCheck, "ssi", $cbo, $nome, $id);
                }
            } else {
                $sqlCheck = "SELECT id FROM especialidades WHERE cbo = ? OR nome = ?";
                $stmtCheck = mysqli_prepare($conexao_bd, $sqlCheck);
                if ($stmtCheck) {
                    mysqli_stmt_bind_param($stmtCheck, "ss", $cbo, $nome);
                }
            }
            
            if ($stmtCheck) {
                mysqli_stmt_execute($stmtCheck);
                $resultCheck = mysqli_stmt_get_result($stmtCheck);
                if (mysqli_num_rows($resultCheck) > 0) {
                    mysqli_stmt_close($stmtCheck);
                    throw new Exception('Esta especialidade (CBO: ' . $cbo . ') já foi adicionada anteriormente.');
                }
                mysqli_stmt_close($stmtCheck);
            }
            
            /* ============================================================
               PREPARED STATEMENT 3: Inativação Condicional
            ============================================================ */
            if ($acao === 'editar' && $status === 'Inativo') {
                $sqlCheckAtivos = "SELECT COUNT(*) AS total FROM medico_especialidades me 
                                   JOIN medicos m ON m.id = me.medico_id 
                                   WHERE me.especialidade_id = ? AND m.status = 'Ativo'";
                $stmtCheckAtivos = mysqli_prepare($conexao_bd, $sqlCheckAtivos);
                if ($stmtCheckAtivos) {
                    mysqli_stmt_bind_param($stmtCheckAtivos, "i", $id);
                    mysqli_stmt_execute($stmtCheckAtivos);
                    $resultCheckAtivos = mysqli_stmt_get_result($stmtCheckAtivos);
                    if ($resultCheckAtivos) {
                        $rowCheckAtivos = mysqli_fetch_assoc($resultCheckAtivos);
                        if (intval($rowCheckAtivos['total']) > 0) {
                            mysqli_stmt_close($stmtCheckAtivos);
                            throw new Exception('Não é possível inativar esta especialidade enquanto houver médicos ativos vinculados a ela.');
                        }
                    }
                    mysqli_stmt_close($stmtCheckAtivos);
                }
            }
            
            // Inserção ou Edição de fato
            if ($acao === 'novo') {
                $sql = "INSERT INTO especialidades (nome, cbo, status) VALUES (?, ?, ?)";
                $stmtExec = mysqli_prepare($conexao_bd, $sql);
                if ($stmtExec) {
                    mysqli_stmt_bind_param($stmtExec, "sss", $nome, $cbo, $status);
                    if (!mysqli_stmt_execute($stmtExec)) {
                        mysqli_stmt_close($stmtExec);
                        throw new Exception('Não foi possível criar a especialidade.');
                    }
                    mysqli_stmt_close($stmtExec);
                }
                $redirect .= '?alert=success&acao=novo';
            } elseif ($acao === 'editar') {
                if ($id <= 0) {
                    throw new Exception('Especialidade inválida para edição.');
                }

                /* ============================================================
                   PREPARED STATEMENT: Validar bloqueio de edição de CBO
                ============================================================ */
                $sqlCheckCbo = "SELECT cbo, 
                                (SELECT COUNT(*) FROM agendamentos WHERE especialidade_id = ? AND status != 'Cancelado') AS agendamentos_ativos 
                                FROM especialidades WHERE id = ?";
                $stmtCheckCbo = mysqli_prepare($conexao_bd, $sqlCheckCbo);
                if ($stmtCheckCbo) {
                    mysqli_stmt_bind_param($stmtCheckCbo, "ii", $id, $id);
                    mysqli_stmt_execute($stmtCheckCbo);
                    $resultCheckCbo = mysqli_stmt_get_result($stmtCheckCbo);
                    if ($rowCboCheck = mysqli_fetch_assoc($resultCheckCbo)) {
                        if (intval($rowCboCheck['agendamentos_ativos']) > 0 && $rowCboCheck['cbo'] !== $cbo) {
                            mysqli_stmt_close($stmtCheckCbo);
                            throw new Exception('Não é possível alterar o CBO desta especialidade pois existem agendamentos não-cancelados vinculados a ela.');
                        }
                    }
                    mysqli_stmt_close($stmtCheckCbo);
                }

                $sql = "UPDATE especialidades SET nome = ?, cbo = ?, status = ? WHERE id = ?";
                $stmtExec = mysqli_prepare($conexao_bd, $sql);
                if ($stmtExec) {
                    mysqli_stmt_bind_param($stmtExec, "sssi", $nome, $cbo, $status, $id);
                    if (!mysqli_stmt_execute($stmtExec)) {
                        mysqli_stmt_close($stmtExec);
                        throw new Exception('Não foi possível atualizar a especialidade.');
                    }
                    mysqli_stmt_close($stmtExec);
                }
                $redirect .= '?alert=success&acao=editar';
            }
        } elseif ($acao === 'excluir') {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

            if ($id <= 0) {
                throw new Exception('Especialidade inválida para exclusão.');
            }

            /* ============================================================
               [SEGURANÇA] PREPARED STATEMENT 4: Validar Vínculos antes de Deletar
            ============================================================ */
            $sqlCheckVinc = "SELECT (SELECT COUNT(*) FROM medico_especialidades WHERE especialidade_id = ?) +
                                    (SELECT COUNT(*) FROM agendamentos WHERE especialidade_id = ?) AS total";
            $stmtCheckVinc = mysqli_prepare($conexao_bd, $sqlCheckVinc);
            if ($stmtCheckVinc) {
                mysqli_stmt_bind_param($stmtCheckVinc, "ii", $id, $id);
                mysqli_stmt_execute($stmtCheckVinc);
                $resultCheck = mysqli_stmt_get_result($stmtCheckVinc);
                if ($resultCheck) {
                    $rowCheck = mysqli_fetch_assoc($resultCheck);
                    if ((int)$rowCheck['total'] > 0) {
                        mysqli_stmt_close($stmtCheckVinc);
                        throw new Exception('Esta especialidade possui vínculos com médicos ou agendamentos e não pode ser excluída.');
                    }
                }
                mysqli_stmt_close($stmtCheckVinc);
            }

            $sqlDel = "DELETE FROM especialidades WHERE id = ?";
            $stmtDel = mysqli_prepare($conexao_bd, $sqlDel);
            if ($stmtDel) {
                mysqli_stmt_bind_param($stmtDel, "i", $id);
                if (!mysqli_stmt_execute($stmtDel)) {
                    mysqli_stmt_close($stmtDel);
                    throw new Exception('Não foi possível excluir a especialidade.');
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
   FILTROS DE BUSCA DINÂMICOS COM PREPARED STATEMENT
============================================================ */
$filtroBusca = trim(isset($_GET['busca']) ? $_GET['busca'] : '');
$especialidades = array();
$where = array();
$params = array();
$types = "";

if ($filtroBusca !== '') {
    if (strpos($filtroBusca, ' - ') !== false) {
        $partesBusca = explode(' - ', $filtroBusca);
        $where[] = "e.cbo = ?";
        $params[] = $partesBusca[0];
        $types .= "s";
    } else {
        $where[] = "(e.nome LIKE ? OR e.cbo LIKE ?)";
        $params[] = "%" . $filtroBusca . "%";
        $params[] = "%" . $filtroBusca . "%";
        $types .= "ss";
    }
}

$sqlConsulta = "SELECT e.id, e.nome, e.cbo, e.status, e.data_criacao,
                       COUNT(DISTINCT me.medico_id) AS medico_count,
                       COUNT(DISTINCT a.id) AS agenda_count,
                       (SELECT COUNT(*) FROM agendamentos sub_a WHERE sub_a.especialidade_id = e.id AND sub_a.status != 'Cancelado') AS agendamentos_validos
                FROM especialidades e
                LEFT JOIN medico_especialidades me ON me.especialidade_id = e.id
                LEFT JOIN agendamentos a ON a.especialidade_id = e.id";

if (count($where) > 0) {
    $sqlConsulta .= " WHERE " . implode(' AND ', $where);
}
$sqlConsulta .= " GROUP BY e.id, e.nome, e.cbo, e.status, e.data_criacao ORDER BY e.nome ASC";

$stmtBusca = mysqli_prepare($conexao_bd, $sqlConsulta);
if ($stmtBusca) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmtBusca, $types, ...$params);
    }
    mysqli_stmt_execute($stmtBusca);
    $resultEsp = mysqli_stmt_get_result($stmtBusca);
    if ($resultEsp) {
        while ($row = mysqli_fetch_assoc($resultEsp)) {
            $especialidades[] = $row;
        }
    }
    mysqli_stmt_close($stmtBusca);
} else {
    $pageError = mysqli_error($conexao_bd);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediAgenda - Cadastro de Especialidades</title>

    <link rel="icon" type="image/x-icon" href="img/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>

    <datalist id="listaSugestoesCboCombinado">
    <?php foreach ($especialidadesList as $itemCbo): ?>
        <option value="<?php echo htmlspecialchars($itemCbo['cod_cbo'] . ' - ' . $itemCbo['nome_cbo']); ?>">
    <?php endforeach; ?>
    </datalist>

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
                <a class="nav-link" href="cadastro_agendas.php"><i class="fa-solid fa-calendar-plus"></i> Agendamentos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="cadastro_medicos.php"><i class="fa-solid fa-user-doctor"></i> Cadastro de Médicos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link ativo" href="cadastro_especialidades.php"><i class="fa-solid fa-list-check"></i> Cadastro de Especialidades</a>
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

        <!-- Cabeçalho da página -->
        <div class="page-header">
            <h2><i class="fa-solid fa-list-check"></i> Cadastro de Especialidades</h2>
            <button id="btnNovaEspecialidade" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalFormEspecialidade">
                <i class="fa-solid fa-plus me-1"></i> Nova Especialidade
            </button>
        </div>

        <div class="card-pagina">
            <div class="card-titulo"><i class="fa-solid fa-magnifying-glass"></i> Filtros</div>
            <form method="GET" action="cadastro_especialidades.php" id="formFiltro">
                <div class="row g-3">
                    <div class="col-12">
                        <input type="text" class="form-control form-control-sm" id="filtroBusca" name="busca" 
                               list="listaFiltroCbo" placeholder="Digite para buscar por Especialidade / CBO..." 
                               value="<?php echo htmlspecialchars($filtroBusca); ?>" autocomplete="off">
                        
                        <datalist id="listaFiltroCbo">
                        <?php foreach ($especialidadesList as $itemCbo): 
                            $opcaoFormatada = $itemCbo['cod_cbo'] . ' - ' . $itemCbo['nome_cbo'];
                        ?>
                            <option value="<?php echo htmlspecialchars($opcaoFormatada); ?>">
                        <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Filtrar
                    </button>
                    <a href="cadastro_especialidades.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-xmark me-1"></i> Limpar
                    </a>
                </div>
            </form>
        </div>

        <div class="card-pagina">
            <div class="card-titulo d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-table-list"></i> Especialidades</span>
                <span id="contadorRegistros" class="text-muted" style="font-size:0.82rem; font-weight:400;">
                    <?php echo count($especialidades) ?> registo(s) encontrado(s)
                </span>
            </div>

            <div class="table-responsive">
                <table class="tabela-especialidades">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nome</th>
                            <th>CBO</th>
                            <th>Status</th>
                            <th>Adicionado em</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($especialidades)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fa-solid fa-list-dots me-2"></i>Nenhuma especialidade encontrada.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($especialidades as $esp): ?>
                                <?php 
                                    $classeStatus = $esp['status'] === 'Ativo' ? 'badge-ativo' : 'badge-inativo';
                                ?>
                                <tr>
                                    <td class="text-muted"><?php echo intval($esp['id']) ?></td>
                                    <td><?php echo htmlspecialchars($esp['nome']) ?></td>
                                    <td><?php echo htmlspecialchars($esp['cbo'] ?? '-') ?></td>
                                    <td><span class="badge-status <?php echo $classeStatus ?>"><?php echo htmlspecialchars($esp['status']) ?></span></td>
                                    <td class="text-muted">
                                        <?php echo !empty($esp['data_criacao']) ? date('d/m/Y H:i', strtotime($esp['data_criacao'])) : '-'; ?>
                                    </td>
                                    <td class="text-center" style="white-space: nowrap;">
                                        <button class="btn btn-sm btn-icon-sm btn-outline-primary btn-editar"
                                                type="button"
                                                data-id="<?php echo intval($esp['id']) ?>"
                                                data-nome="<?php echo htmlspecialchars($esp['nome']) ?>"
                                                data-cbo="<?php echo htmlspecialchars($esp['cbo'] ?? '') ?>"
                                                data-status="<?php echo htmlspecialchars($esp['status']) ?>"
                                                data-bloqueio-cbo="<?php echo (intval($esp['agendamentos_validos']) > 0) ? 'true' : 'false' ?>"
                                                title="Editar">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <?php $referencias = intval($esp['medico_count']) + intval($esp['agenda_count']); ?>
                                        <?php if ($referencias === 0): ?>
                                            <button class="btn btn-sm btn-icon-sm btn-outline-danger btn-excluir"
                                                    type="button"
                                                    data-id="<?php echo intval($esp['id']) ?>"
                                                    data-nome="<?php echo htmlspecialchars($esp['nome']) ?>"
                                                    title="Excluir">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-icon-sm btn-outline-secondary" type="button" disabled
                                                    title="Esta especialidade está vinculada a registos e não pode ser excluída">
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
        </div>
    </main>

    <div class="modal fade modal-form" id="modalFormEspecialidade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFormTitulo">
                        <i class="fa-solid fa-plus me-2"></i>Nova Especialidade
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form id="formEspecialidade" action="cadastro_especialidades.php" method="POST">
                    <input type="hidden" name="acao" id="formAcao" value="novo">
                    <input type="hidden" name="id" id="formId" value="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="formNome" class="form-label">Especialidade <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="formNome" name="nome" required>
                        </div>
                        <div class="mb-3">
                            <label for="formCbo" class="form-label">CBO <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="formCbo" name="cbo" required pattern="\d*" maxlength="6" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                        <div class="mb-3">
                            <label technicians for="formStatus" class="form-label">Status</label>
                            <select class="form-select" id="formStatus" name="status">
                                <option value="Ativo">Ativo</option>
                                <option value="Inativo">Inativo</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        <button type="button" class="btn btn-primary" onclick="salvarEspecialidade()">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <form id="formExcluir" action="cadastro_especialidades.php" method="POST" style="display:none;">
        <input type="hidden" name="acao" value="excluir">
        <input type="hidden" name="id" id="formExcluirId" value="">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        var btnSanduiche      = document.getElementById('btnSanduiche');
        var sidebar           = document.getElementById('sidebar');
        var conteudoPrincipal = document.getElementById('conteudoPrincipal');
        var sidebarOverlay    = document.getElementById('sidebarOverlay');
        var btnNovaEspecialidade = document.getElementById('btnNovaEspecialidade');
        var modalFormEspecialidadeEl = document.getElementById('modalFormEspecialidade');
        var modalFormEspecialidade   = new bootstrap.Modal(modalFormEspecialidadeEl);
        var formEspecialidade        = document.getElementById('formEspecialidade');
        
        var formNome = document.getElementById('formNome');
        var formCbo  = document.getElementById('formCbo');

        // 1. Array com todas as especialidades registradas para validação front-end
        var especialidadesRegistradas = <?php echo json_encode(array_map(function($item) { 
            return ['id' => $item['id'], 'nome' => strtolower(trim($item['nome_cbo'])), 'cbo' => $item['cod_cbo']]; 
        }, $especialidadesList)); ?>;

        // Reset do formulário quando abre modal para nova especialidade
        modalFormEspecialidadeEl.addEventListener('show.bs.modal', function() {
            var btnClicado = document.activeElement;
            if (btnClicado && btnClicado.id === 'btnNovaEspecialidade') {
                resetarFormularioEspecialidade();
            }
        });

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

        function resetarFormularioEspecialidade() {
            document.getElementById('modalFormTitulo').innerHTML = '<i class="fa-solid fa-plus me-2"></i>Nova Especialidade';
            document.getElementById('formAcao').value = 'novo';
            document.getElementById('formId').value = '';
            formNome.value = '';
            formCbo.value = '';
            formCbo.readOnly = false;
            formCbo.removeAttribute('title');
            document.getElementById('formStatus').value = 'Ativo';
        }

        document.querySelector('.tabela-especialidades').addEventListener('click', function(e) {
            var btnEditar = e.target.closest('.btn-editar');
            var btnExcluir = e.target.closest('.btn-excluir');

            if (btnEditar) {
                document.getElementById('modalFormTitulo').innerHTML = '<i class="fa-solid fa-pen me-2"></i>Editar Especialidade';
                document.getElementById('formAcao').value = 'editar';
                document.getElementById('formId').value = btnEditar.dataset.id;
                
                var cbo_salvo = btnEditar.dataset.cbo;
                var nome_salvo = btnEditar.dataset.nome;
                var status_salvo = btnEditar.dataset.status;
                var bloqueio_cbo = btnEditar.dataset.bloqueioCbo === 'true';
                
                formCbo.value = cbo_salvo; 
                formNome.value = nome_salvo;
                document.getElementById('formStatus').value = status_salvo;

                if (bloqueio_cbo) {
                    formCbo.readOnly = true;
                    formCbo.setAttribute('title', 'CBO bloqueado: Existem agendamentos ativos vinculados.');
                } else {
                    formCbo.readOnly = false;
                    formCbo.removeAttribute('title');
                }
                modalFormEspecialidade.show();
                return;
            }

            if (btnExcluir) {
                Swal.fire({
                    title: 'Excluir especialidade?',
                    html: 'Deseja excluir <strong>' + btnExcluir.dataset.nome + '</strong>?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sim, excluir',
                    cancelButtonText: 'Voltar'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        document.getElementById('formExcluirId').value = btnExcluir.dataset.id;
                        document.getElementById('formExcluir').submit();
                    }
                });
            }
        });

        function salvarEspecialidade() {
            var nomeInput = formNome.value.trim();
            var cboInput = formCbo.value.trim();
            var currentId = document.getElementById('formId').value;

            if (nomeInput === '' || cboInput === '') {
                Swal.fire('Atenção', 'Preencha os campos Especialidade e CBO!', 'warning');
                return;
            }

            var nomeFormatado = nomeInput.toLowerCase();
            
            var duplicado = especialidadesRegistradas.find(function(esp) {
                // Em caso de edição, ignora o match com o próprio registro atual
                if (currentId !== '' && esp.id == currentId) return false;
                
                return esp.nome === nomeFormatado || esp.cbo === cboInput;
            });

            if (duplicado) {
                Swal.fire('Atenção', 'Já existe uma especialidade com este Nome ou CBO cadastrado!', 'warning');
                return;
            }

            formEspecialidade.submit();
        }

        // Verificar alertas de sucesso/erro na URL
        document.addEventListener('DOMContentLoaded', function() {
            var urlParams = new URLSearchParams(window.location.search);
            var alertType = urlParams.get('alert');
            var message = urlParams.get('message');
            var acao = urlParams.get('acao');
            
            if (alertType === 'success') {
                var titulo = 'Sucesso!';
                if (acao === 'novo') {
                    titulo = 'Especialidade criada!';
                } else if (acao === 'editar') {
                    titulo = 'Especialidade atualizada!';
                } else if (acao === 'excluir') {
                    titulo = 'Especialidade excluída!';
                }
                Swal.fire(titulo, message || 'Operação realizada com sucesso.', 'success').then(function() {
                    // Recarrega a página limpa dos parâmetros de alerta
                    window.location.href = 'cadastro_especialidades.php';
                });
            } else if (alertType === 'error') {
                Swal.fire('Erro', message || 'Ocorreu um erro durante a operação.', 'error');
            }
        });
    </script>
</body>
</html>
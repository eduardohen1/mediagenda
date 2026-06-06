-- ============================================================
-- MediAgenda - Script de criação do banco de dados
-- Compatível com MySQL 5.6+ / MariaDB 10.1+
-- ============================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS labdbprog2
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE labdbprog2;

CREATE TABLE IF NOT EXISTS usuario (
    cod_usuario INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    username VARCHAR(255) NOT NULL UNIQUE,
    perfil VARCHAR(20) NOT NULL DEFAULT 'user',
    pass VARCHAR(255) NOT NULL,
    PRIMARY KEY (cod_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- SUPER USUÁRIO INICIAL
-- ============================================================
INSERT INTO usuario (nome, email, username, pass, perfil) VALUES
    ('Administrador', 'admin@mediagenda.com', 'admin', '$2y$10$4SnRvd6aCpkYM5UdyUD3/O96w09EGCxx5DXQGuZxtfin8Z4p85fBK', 'admin');

-- ============================================================
-- TABELA: convite_usuario
-- Cadastro de convites para novos usuários.
-- ============================================================
CREATE TABLE convite_usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    perfil VARCHAR(20) NOT NULL,
    usado TINYINT(1) DEFAULT 0,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABELA: especialidades
-- Cadastro de especialidades médicas.
-- ============================================================
CREATE TABLE IF NOT EXISTS especialidades (
    id            INT          UNSIGNED NOT NULL AUTO_INCREMENT,
    nome          VARCHAR(100) NOT NULL,
    cbo           VARCHAR(20)  NOT NULL,
    status        ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
    data_criacao  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_especialidade_nome (nome),
    UNIQUE KEY uq_especialidade_cbo (cbo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: medicos
-- Cadastro de médicos. (A especialidade foi movida para a tabela pivô)
-- ============================================================
CREATE TABLE IF NOT EXISTS medicos (
    id               INT          UNSIGNED NOT NULL AUTO_INCREMENT,
    nome             VARCHAR(150) NOT NULL,
    crm              VARCHAR(20)  NOT NULL,
    telefone         VARCHAR(20)           DEFAULT NULL,
    email            VARCHAR(150)          DEFAULT NULL,
    status           ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_medico_crm (crm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA PIVÔ: medico_especialidades
-- Relacionamento N:N entre Médicos e Especialidades
-- ============================================================
CREATE TABLE IF NOT EXISTS medico_especialidades (
    medico_id        INT UNSIGNED NOT NULL,
    especialidade_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (medico_id, especialidade_id),
    
    CONSTRAINT fk_me_medico 
        FOREIGN KEY (medico_id) REFERENCES medicos(id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
        
    CONSTRAINT fk_me_especialidade 
        FOREIGN KEY (especialidade_id) REFERENCES especialidades(id) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: agendamentos
-- Cadastro de consultas agendadas.
-- ============================================================
CREATE TABLE IF NOT EXISTS agendamentos (
    id               INT          UNSIGNED NOT NULL AUTO_INCREMENT,
    paciente         VARCHAR(150) NOT NULL,
    medico_id        INT          UNSIGNED NOT NULL,
    especialidade_id INT          UNSIGNED NOT NULL,
    data             DATE         NOT NULL,
    horario          TIME         NOT NULL,
    status           ENUM('Confirmado','Pendente','Cancelado') NOT NULL DEFAULT 'Pendente',
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY fk_agendamento_medico_idx       (medico_id),
    KEY fk_agendamento_especialidade_idx (especialidade_id),
    KEY idx_agendamento_data            (data),
    KEY idx_agendamento_status          (status),

    CONSTRAINT fk_agendamento_medico
        FOREIGN KEY (medico_id)
        REFERENCES medicos (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_agendamento_especialidade
        FOREIGN KEY (especialidade_id)
        REFERENCES especialidades (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DADOS INICIAIS: especialidades
-- ============================================================
INSERT INTO especialidades (id, nome, cbo, status) VALUES
    (1, 'Médico infectologista', '225103', 'Ativo'),
    (2, 'Médico acupunturista', '225105', 'Ativo'),
    (3, 'Médico legista', '225106', 'Ativo'),
    (4, 'Médico nefrologista', '225109', 'Ativo'),
    (5, 'Médico alergista e imunologista', '225110', 'Ativo'),
    (6, 'Médico neurologista', '225112', 'Ativo'),
    (7, 'Médico angiologista', '225115', 'Ativo'),
    (8, 'Médico nutrologista', '225118', 'Ativo'),
    (9, 'Médico cardiologista', '225120', 'Ativo'),
    (10, 'Médico oncologista clínico', '225121', 'Ativo'),
    (11, 'Médico cancerologista pediátrico', '225122', 'Ativo'),
    (12, 'Médico pediatra', '225124', 'Ativo'),
    (13, 'Médico clínico', '225125', 'Ativo'),
    (14, 'Médico pneumologista', '225127', 'Ativo'),
    (15, 'Médico de família e comunidade', '225130', 'Ativo'),
    (16, 'Médico psiquiatra', '225133', 'Ativo'),
    (17, 'Médico dermatologista', '225135', 'Ativo'),
    (18, 'Médico reumatologista', '225136', 'Ativo'),
    (19, 'Médico sanitarista', '225139', 'Ativo'),
    (20, 'Médico do trabalho', '225140', 'Ativo'),
    (21, 'Médico da estratégia de saúde da família', '225142', 'Ativo'),
    (22, 'Médico em medicina de tráfego', '225145', 'Ativo'),
    (23, 'Médico anatomopatologista', '225148', 'Ativo'),
    (24, 'Médico em medicina intensiva', '225150', 'Ativo'),
    (25, 'Médico anestesiologista', '225151', 'Ativo'),
    (26, 'Médico antroposófico', '225154', 'Ativo'),
    (27, 'Médico endocrinologista e metabologista', '225155', 'Ativo'),
    (28, 'Médico fisiatra', '225160', 'Ativo'),
    (29, 'Médico gastroenterologista', '225165', 'Ativo'),
    (30, 'Médico generalista', '225170', 'Ativo'),
    (31, 'Médico geneticista', '225175', 'Ativo'),
    (32, 'Médico geriatra', '225180', 'Ativo'),
    (33, 'Médico hematologista', '225185', 'Ativo'),
    (34, 'Médico homeopata', '225195', 'Ativo'),
    (35, 'Médico em cirurgia vascular', '225203', 'Ativo'),
    (36, 'Médico cirurgião cardiovascular', '225210', 'Ativo'),
    (37, 'Médico cirurgião de cabeça e pescoço', '225215', 'Ativo'),
    (38, 'Médico cirurgião do aparelho digestivo', '225220', 'Ativo'),
    (39, 'Médico cirurgião geral', '225225', 'Ativo'),
    (40, 'Médico cirurgião pediátrico', '225230', 'Ativo'),
    (41, 'Médico cirurgião plástico', '225235', 'Ativo'),
    (42, 'Médico cirurgião torácico', '225240', 'Ativo'),
    (43, 'Médico ginecologista e obstetra', '225250', 'Ativo'),
    (44, 'Médico mastologista', '225255', 'Ativo'),
    (45, 'Médico neurocirurgião', '225260', 'Ativo'),
    (46, 'Médico oftalmologista', '225265', 'Ativo'),
    (47, 'Médico ortopedista e traumatologista', '225270', 'Ativo'),
    (48, 'Médico otorrinolaringologista', '225275', 'Ativo'),
    (49, 'Médico coloproctologista', '225280', 'Ativo'),
    (50, 'Médico urologista', '225285', 'Ativo'),
    (51, 'Médico cancerologista cirurgíco', '225290', 'Ativo'),
    (52, 'Médico cirurgião da mão', '225295', 'Ativo'),
    (53, 'Médico citopatologista', '225305', 'Ativo'),
    (54, 'Médico em endoscopia', '225310', 'Ativo'),
    (55, 'Médico em medicina nuclear', '225315', 'Ativo'),
    (56, 'Médico em radiologia e diagnóstico por imagem', '225320', 'Ativo'),
    (57, 'Médico patologista', '225325', 'Ativo'),
    (58, 'Médico radioterapeuta', '225330', 'Ativo'),
    (59, 'Médico patologista clínico / medicina laboratorial', '225335', 'Ativo'),
    (60, 'Médico hemoterapeuta', '225340', 'Ativo'),
    (61, 'Médico hiperbarista', '225345', 'Ativo'),
    (62, 'Médico neurofisiologista clínico', '225350', 'Ativo'),
    (63, 'Médico radiologista intervencionista', '225355', 'Ativo');

-- ============================================================
-- DADOS INICIAIS: medicos
-- ============================================================
INSERT INTO medicos (id, nome, crm, telefone, email, status) VALUES
    (1, 'Dr. Carlos Lima',    'CRM/SP 12345', '(11) 91234-5678', 'carlos.lima@clinica.com',    'Ativo'),
    (2, 'Dra. Ana Paula',     'CRM/SP 23456', '(11) 92345-6789', 'ana.paula@clinica.com',      'Ativo'),
    (3, 'Dr. Pedro Alves',    'CRM/SP 34567', '(11) 93456-7890', 'pedro.alves@clinica.com',    'Ativo'),
    (4, 'Dra. Marina Reis',   'CRM/SP 45678', '(11) 94567-8901', 'marina.reis@clinica.com',    'Ativo'),
    (5, 'Dr. Ricardo Souza',  'CRM/SP 56789', '(11) 95678-9012', 'ricardo.souza@clinica.com',  'Inativo'),
    (6, 'Dra. Fernanda Melo', 'CRM/SP 67890', '(11) 96789-0123', 'fernanda.melo@clinica.com',  'Ativo'),
    (7, 'Dr. João Pereira',     'CRM/SP 71234', '(11) 97123-4567', 'joao.pereira@clinica.com',   'Ativo'),
    (8, 'Dra. Beatriz Santos',   'CRM/SP 82345', '(11) 98234-5678', 'beatriz.santos@clinica.com', 'Ativo'),
    (9, 'Dr. Alexandre Costa',   'CRM/SP 93456', '(11) 99345-6789', 'alexandre.costa@clinica.com','Ativo'),
    (10, 'Dra. Patrícia Mendes', 'CRM/SP 04567', '(11) 90456-7890', 'patricia.mendes@clinica.com','Ativo'),
    (11, 'Dr. Rafael Oliveira',  'CRM/SP 15678', '(11) 91567-8901', 'rafael.oliveira@clinica.com','Ativo');


-- ============================================================
-- DADOS INICIAIS: Vínculo Médico x Especialidade
-- ============================================================
INSERT INTO medico_especialidades (medico_id, especialidade_id) VALUES
    (1, 1), -- Carlos Lima: infectologista
    (2, 2), -- Ana Paula: acupunturista
    (3, 5), -- Pedro Alves: alergista e imunologista
    (4, 6), -- Marina Reis: neurologista
    (5, 4), -- Ricardo Souza: nefrologista
    (6, 3), -- Fernanda Melo: legista
    -- Dr. João Pereira: 3 especialidades
    (7, 1),  -- infectologista
    (7, 9),  -- cardiologista
    (7, 43), -- ginecologista e obstetra
    -- Dra. Beatriz Santos: 2 especialidades
    (8, 6),   -- neurologista
    (8, 17), -- dermatologista
    -- Dr. Alexandre Costa: 4 especialidades
    (9, 5),   -- alergista e imunologista
    (9, 33),  -- hematologista
    (9, 29),  -- gastroenterologista
    (9, 16),  -- psiquiatra
    -- Dra. Patrícia Mendes: 3 especialidades
    (10, 47), -- ortopedista e traumatologista
    (10, 50), -- urologista
    (10, 49), -- coloproctologista
    -- Dr. Rafael Oliveira: 2 especialidades
    (11, 28), -- fisiatra
    (11, 38); -- cirurgião do aparelho digestivo

-- ============================================================
-- DADOS INICIAIS: agendamentos
-- ============================================================
INSERT INTO agendamentos (id, paciente, medico_id, especialidade_id, data, horario, status) VALUES
    ( 1, 'Maria Souza',     1, 1, '2026-06-05', '09:00', 'Confirmado'),
    ( 2, 'Carlos Andrade',  2, 2, '2026-06-08', '10:30', 'Confirmado'),
    ( 3, 'Juliana Reis',    3, 5, '2026-06-08', '14:00', 'Pendente'),
    ( 4, 'Pedro Henrique',  2, 2, '2026-06-12', '08:00', 'Confirmado'),
    ( 5, 'Júlia Mendes',    1, 1, '2026-06-15', '11:00', 'Confirmado'),
    ( 6, 'Roberto Dias',    3, 5, '2026-06-15', '15:30', 'Confirmado'),
    ( 7, 'Fernanda Costa',  4, 6, '2026-06-15', '16:30', 'Pendente'),
    ( 8, 'Lucas Silva',     1, 1, '2026-06-15', '17:00', 'Confirmado'),
    ( 9, 'Luiz Henrique',   4, 6, '2026-06-19', '09:30', 'Confirmado'),
    (10, 'Beatriz Ramos',   2, 2, '2026-06-23', '10:00', 'Pendente'),
    (11, 'Marcos Vinícius', 3, 5, '2026-06-26', '14:00', 'Confirmado'),

    (12, 'Ana Carolina',    7, 9, '2026-05-04', '08:30', 'Confirmado'),   
    (13, 'Bruno Silva',     7, 43, '2026-05-04', '09:00', 'Pendente'),      
    (14, 'Camila Oliveira', 8, 6,  '2026-05-05', '10:00', 'Confirmado'),   
    (15, 'Daniel Rocha',    9, 29, '2026-05-05', '11:30', 'Confirmado'),     
    (16, 'Elena Martins',   7, 1,  '2026-05-06', '14:00', 'Confirmado'),   
    (17, 'Fabio Torres',    8, 17, '2026-05-06', '15:30', 'Pendente'),      
    (18, 'Giovana Lima',    9, 5,  '2026-05-08', '07:00', 'Confirmado'),   
    (19, 'Hugo Almeida',    7, 9,  '2026-05-08', '12:00', 'Pendente'),      
    (20, 'Irene Souza',     10,47, '2026-05-11', '09:30', 'Confirmado'),     
    (21, 'João Vicente',    8, 6,  '2026-05-11', '16:00', 'Pendente'),      
    (22, 'Karina Nunes',    9,33,  '2026-05-12', '10:45', 'Confirmado'),     
    (23, 'Lucas Borges',    7, 43, '2026-05-12', '13:30', 'Cancelado'),       
    (24, 'Mariana Castro',  10,50, '2026-05-13', '11:00', 'Confirmado'),     
    (25, 'Nicolas Pinto',   9,16,  '2026-05-14', '15:00', 'Pendente'),     
    (26, 'Olivia Freitas',  8, 17, '2026-05-14', '09:15', 'Confirmado'),  
    (27, 'Paulo Mendes',    7, 1,  '2026-05-18', '17:00', 'Pendente'), 
    (28, 'Querida Silva',   9,33,  '2026-05-19', '14:30', 'Confirmado'),     
    (29, 'Roberto Campos',  10,47, '2026-05-20', '08:00', 'Pendente'),  
    (30, 'Sofia Alves',     7, 9,  '2026-05-21', '11:45', 'Confirmado'),

    (31, 'Tiago Ferreira',     8, 6,  '2026-06-01', '07:30', 'Confirmado'),      
    (32, 'Ursula Dias',        9, 5,   '2026-06-02', '10:30', 'Pendente'),       
    (33, 'Vitoriana Lima',     7, 43, '2026-06-03', '16:45', 'Confirmado'),      
    (34, 'Wagner Oliveira',    8,17,   '2026-06-04', '09:45', 'Pendente'),        
    (35, 'Ximena Rodrigues',   10,50, '2026-06-08', '13:00', 'Confirmado'),      
    (36, 'Yasmin Carvalho',    9,33,  '2026-06-09', '17:00', 'Pendente'),       
    (37, 'Zé Eduardo',         7, 9,   '2026-06-10', '14:15', 'Cancelado'),      
    (38, 'Amanda Correia',     8, 6,   '2026-06-11', '09:00', 'Confirmado'),      
    (39, 'Breno Araújo',       7, 1,   '2026-06-12', '11:30', 'Pendente'),        
    (40, 'Claudia Nascimento', 9,16,  '2026-06-15', '10:00', 'Confirmado'),      
    (41, 'Davi Moura',         8,17,  '2026-06-16', '15:30', 'Pendente'),       
    (42, 'Erika Vasconcelos',  7,43,   '2026-06-17', '08:45', 'Confirmado'),      
    (43, 'Felipe Mendes',      10,47, '2026-06-19', '17:00', 'Cancelado'),      
    (44, 'Gabriela Pinto',     8, 6,   '2026-06-22', '12:00', 'Confirmado'),      
    (45, 'Henrique Santos',    9,33,  '2026-06-23', '09:30', 'Pendente'),        
    (46, 'Isabela Gomes',      7, 9,   '2026-06-24', '16:00', 'Confirmado'),       
    (47, 'Jorge Almeida',      8,17,  '2026-06-25', '14:30', 'Pendente'),        
    (48, 'Kátia Ferreira',     9,29,   '2026-06-26', '11:00', 'Confirmado'),     

    (49, 'Leonardo Costa',     7, 43, '2026-07-01', '10:00', 'Confirmado'),      
    (50, 'Mônica Ribeiro',     8,17,   '2026-07-02', '15:45', 'Pendente'),        
    (51, 'Nelson Cunha',       9,33,  '2026-07-03', '08:30', 'Confirmado'),      
    (52, 'Olimpia Silva',      7, 9,   '2026-07-06', '13:30', 'Pendente'),       
    (53, 'Pedro Henrique',     8, 6,   '2026-07-07', '11:15', 'Confirmado'),      
    (54, 'Quintino Souza',     9,5,    '2026-07-08', '17:00', 'Pendente'),       
    (55, 'Regina Martins',     7, 1,   '2026-07-09', '09:45', 'Cancelado'),      
    (56, 'Samuel Dias',        8,17,  '2026-07-10', '14:30', 'Confirmado'),       
    (57, 'Tatiane Oliveira',   7,43,  '2026-07-13', '10:15', 'Pendente'),        
    (58, 'Ubaldo Gomes',       9,33,  '2026-07-14', '16:00', 'Confirmado'),      
    (59, 'Viviane Pinto',      7,9,   '2026-07-15', '08:00', 'Pendente'),       
    (60, 'Wellington Costa',   8,6,   '2026-07-16', '13:45', 'Cancelado'),         
    (61, 'Xavier Almeida',     7,1,  '2026-07-17', '11:00', 'Confirmado'),       
    (62, 'Yara Mendes',        8,17, '2026-07-20', '15:30', 'Pendente'),         
    (63, 'Zeca Pereira',       9,5,   '2026-07-21', '09:15', 'Confirmado'),      
    (64, 'Alice Barbosa',      7,43,'2026-07-22', '17:00', 'Pendente'),         
    (65, 'Benedito Torres',    8,17,  '2026-07-23', '12:45', 'Confirmado');      

-- ============================================================
-- VIEWS ÚTEIS
-- ============================================================

-- Agendamentos com nome do médico e especialidade resolvidos
CREATE OR REPLACE VIEW vw_agendamentos AS
    SELECT
        a.id,
        a.paciente,
        m.nome              AS medico,
        e.nome              AS especialidade,
        a.data,
        a.horario,
        a.status,
        a.created_at,
        a.updated_at
    FROM agendamentos  a
    JOIN medicos       m ON m.id = a.medico_id
    JOIN especialidades e ON e.id = a.especialidade_id;

-- Médicos com nome da especialidade resolvido (Atualizado para N:N)
CREATE OR REPLACE VIEW vw_medicos AS
    SELECT
        m.id,
        m.nome,
        m.crm,
        GROUP_CONCAT(e.nome SEPARATOR ', ') AS especialidades,
        m.telefone,
        m.email,
        m.status,
        m.created_at,
        m.updated_at
    FROM medicos m
    LEFT JOIN medico_especialidades me ON m.id = me.medico_id
    LEFT JOIN especialidades e ON me.especialidade_id = e.id
    GROUP BY m.id;


-- ============================================================
-- MediAgenda - Script de criação do banco de dados
-- Compatível com MySQL 5.6+ / MariaDB 10.1+
-- ============================================================

CREATE DATABASE IF NOT EXISTS labdbprog2
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE labdbprog2;

CREATE TABLE IF NOT EXISTS usuario (
    cod_usuario INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    username VARCHAR(255) NOT NULL UNIQUE,
    pass VARCHAR(30) NOT NULL,
    PRIMARY KEY (cod_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO usuario (nome, email, username, pass) VALUES
    ('aluno', 'aluno@a', 'aluno', '123456'),
    ('professor', 'professor@a', 'professor', 'professor123');

-- ============================================================
-- TABELA: especialidades
-- Cadastro de especialidades médicas.
-- ============================================================
CREATE TABLE IF NOT EXISTS especialidades (
    id         INT          UNSIGNED NOT NULL AUTO_INCREMENT,
    nome       VARCHAR(100) NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_especialidade_nome (nome)
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
INSERT INTO especialidades (id, nome) VALUES
    (1, 'Cardiologia'),
    (2, 'Dermatologia'),
    (3, 'Ginecologia'),
    (4, 'Neurologia'),
    (5, 'Ortopedia'),
    (6, 'Pediatria');

-- ============================================================
-- DADOS INICIAIS: medicos
-- ============================================================
INSERT INTO medicos (id, nome, crm, telefone, email, status) VALUES
    (1, 'Dr. Carlos Lima',    'CRM/SP 12345', '(11) 91234-5678', 'carlos.lima@clinica.com',    'Ativo'),
    (2, 'Dra. Ana Paula',     'CRM/SP 23456', '(11) 92345-6789', 'ana.paula@clinica.com',      'Ativo'),
    (3, 'Dr. Pedro Alves',    'CRM/SP 34567', '(11) 93456-7890', 'pedro.alves@clinica.com',    'Ativo'),
    (4, 'Dra. Marina Reis',   'CRM/SP 45678', '(11) 94567-8901', 'marina.reis@clinica.com',    'Ativo'),
    (5, 'Dr. Ricardo Souza',  'CRM/SP 56789', '(11) 95678-9012', 'ricardo.souza@clinica.com',  'Inativo'),
    (6, 'Dra. Fernanda Melo', 'CRM/SP 67890', '(11) 96789-0123', 'fernanda.melo@clinica.com',  'Ativo');

-- ============================================================
-- DADOS INICIAIS: Vínculo Médico x Especialidade
-- ============================================================
INSERT INTO medico_especialidades (medico_id, especialidade_id) VALUES
    (1, 1), -- Carlos Lima: Cardiologia
    (2, 2), -- Ana Paula: Dermatologia
    (3, 5), -- Pedro Alves: Ortopedia
    (4, 6), -- Marina Reis: Pediatria
    (5, 4), -- Ricardo Souza: Neurologia
    (6, 3); -- Fernanda Melo: Ginecologia

-- ============================================================
-- DADOS INICIAIS: agendamentos
-- ============================================================
INSERT INTO agendamentos (id, paciente, medico_id, especialidade_id, data, horario, status) VALUES
    ( 1, 'Maria Souza',     1, 1, '2026-04-05', '09:00', 'Confirmado'),
    ( 2, 'Carlos Andrade',  2, 2, '2026-04-08', '10:30', 'Confirmado'),
    ( 3, 'Juliana Reis',    3, 5, '2026-04-08', '14:00', 'Pendente'),
    ( 4, 'Pedro Henrique',  2, 2, '2026-04-12', '08:00', 'Confirmado'),
    ( 5, 'Júlia Mendes',    1, 1, '2026-04-15', '11:00', 'Confirmado'),
    ( 6, 'Roberto Dias',    3, 5, '2026-04-15', '15:30', 'Confirmado'),
    ( 7, 'Fernanda Costa',  4, 6, '2026-04-15', '16:30', 'Pendente'),
    ( 8, 'Lucas Silva',     1, 1, '2026-04-15', '17:30', 'Confirmado'),
    ( 9, 'Luiz Henrique',   4, 6, '2026-04-20', '09:30', 'Confirmado'),
    (10, 'Beatriz Ramos',   2, 2, '2026-04-23', '10:00', 'Pendente'),
    (11, 'Marcos Vinícius', 3, 5, '2026-04-27', '14:00', 'Confirmado');

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

-- ============================================================
-- ALTERAÇÕES EXTRAS (Mantidas conforme o original)
-- ============================================================
ALTER TABLE especialidades ADD COLUMN cbo VARCHAR(20) DEFAULT NULL AFTER nome;
ALTER TABLE especialidades ADD COLUMN data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP;
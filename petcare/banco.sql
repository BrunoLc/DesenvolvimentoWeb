-- =====================================================================
-- PetCare · banco.sql
-- Apaga e recria o banco do zero, com tabelas e dados de exemplo.
-- Uso:  mysql -u root < banco.sql     (ou importar no phpMyAdmin)
-- =====================================================================

DROP DATABASE IF EXISTS petcare;
CREATE DATABASE petcare
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE petcare;

-- ---------------------------------------------------------------------
-- Tutores (donos dos pets)
-- ---------------------------------------------------------------------
CREATE TABLE tutores (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome       VARCHAR(100) NOT NULL,
    telefone   VARCHAR(20)  NOT NULL,
    email      VARCHAR(150) NOT NULL,
    criado_em  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tutores_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Pets
-- Regra de exclusão: ON DELETE RESTRICT -> não é possível excluir um
-- tutor que ainda tenha pets (a API responde 409). Justificativa no README.
-- ---------------------------------------------------------------------
CREATE TABLE pets (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nome             VARCHAR(60)   NOT NULL,
    especie          ENUM('cachorro','gato','ave','roedor','outro') NOT NULL,
    raca             VARCHAR(60)   NULL,
    data_nascimento  DATE          NOT NULL,
    peso_kg          DECIMAL(5,2)  NOT NULL,
    tutor_id         INT UNSIGNED  NOT NULL,
    criado_em        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pets_tutor (tutor_id),
    KEY idx_pets_especie (especie),
    CONSTRAINT fk_pets_tutor FOREIGN KEY (tutor_id)
        REFERENCES tutores (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT ck_pets_peso CHECK (peso_kg > 0 AND peso_kg <= 200)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Usuários (funcionários da clínica)
-- senha_hash guarda o resultado de password_hash() do PHP (bcrypt).
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL,
    senha_hash  VARCHAR(255) NOT NULL,
    criado_em   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tokens de acesso (bin2hex(random_bytes(32)) = 64 caracteres)
-- Ao excluir um usuário, seus tokens são apagados junto.
-- ---------------------------------------------------------------------
CREATE TABLE tokens (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED NOT NULL,
    token       CHAR(64)     NOT NULL,
    expira_em   DATETIME     NOT NULL,
    criado_em   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tokens_token (token),
    KEY idx_tokens_expira (expira_em),
    CONSTRAINT fk_tokens_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- DADOS DE EXEMPLO
-- =====================================================================

-- Usuário de teste para o professor
--   e-mail: professor@petcare.com
--   senha : petcare123
INSERT INTO usuarios (nome, email, senha_hash) VALUES
('Professor Teste', 'professor@petcare.com',
 '$2y$10$nHcp4l7odPO3ejHRjBKCTuBIlV/jwt.K33A120SLywxGmKtseTMxm');

-- 6 tutores
INSERT INTO tutores (nome, telefone, email) VALUES
('Mariana Souza',    '(11) 98765-4321', 'mariana.souza@email.com'),
('Carlos Pereira',   '(21) 99876-1234', 'carlos.pereira@email.com'),
('Fernanda Lima',    '(31) 97654-3210', 'fernanda.lima@email.com'),
('Roberto Almeida',  '(41) 98888-7777', 'roberto.almeida@email.com'),
('Juliana Costa',    '(51) 99123-4567', 'juliana.costa@email.com'),
('Anderson Martins', '(61) 98456-7890', 'anderson.martins@email.com');

-- 12 pets (Mariana tem 3, Carlos tem 2, Fernanda tem 2; Anderson ainda sem pets)
INSERT INTO pets (nome, especie, raca, data_nascimento, peso_kg, tutor_id) VALUES
('Thor',     'cachorro', 'Golden Retriever',     '2019-03-15', 32.50, 1),
('Luna',     'gato',     'Siamês',               '2021-07-22',  4.20, 1),
('Piu',      'ave',      'Calopsita',            '2022-01-10',  0.09, 1),
('Bidu',     'cachorro', 'Vira-lata',            '2018-11-05', 14.80, 2),
('Mel',      'cachorro', 'Poodle',               '2020-05-30',  6.30, 2),
('Mia',      'gato',     'Persa',                '2020-09-12',  3.80, 3),
('Simba',    'gato',     'Maine Coon',           '2023-02-01',  6.10, 3),
('Rex',      'cachorro', 'Pastor Alemão',        '2017-08-19', 38.00, 4),
('Nina',     'roedor',   'Hamster Sírio',        '2024-04-03',  0.12, 5),
('Bolinha',  'roedor',   'Porquinho-da-índia',   '2023-10-18',  0.95, 5),
('Kiara',    'gato',     NULL,                   '2022-12-25',  4.50, 4),
('Jade',     'outro',    'Jabuti',               '2015-06-06',  8.40, 5);

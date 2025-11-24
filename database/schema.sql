-- Banco de dados: ferramentaria
-- Execute cada bloco no MySQL (XAMPP) para preparar o sistema.

CREATE DATABASE IF NOT EXISTS ferramentaria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ferramentaria;

-- Usuários do sistema
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(80) NOT NULL,
    sobrenome VARCHAR(80) NOT NULL,
    login VARCHAR(60) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    email VARCHAR(120),
    matricula VARCHAR(40) UNIQUE,
    setor VARCHAR(80) NOT NULL,
    perfil ENUM('Administrador', 'Operador', 'Usuário') NOT NULL DEFAULT 'Usuário',
    qr_token VARCHAR(64) UNIQUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO usuarios (nome, sobrenome, login, senha_hash, email, matricula, setor, perfil, qr_token)
VALUES ('Admin', 'Principal', 'admin', '$2y$12$RXvJ4CzHNVoGzUB7y/xP7OxeDmYrhdyjlrPuJ5rKDkDMomWphvZbC', 'admin@jomaga.com', 'ADM001', 'TI', 'Administrador', 'seed_admin_token');

-- Classes de ferramentas
CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(80) NOT NULL UNIQUE,
    descricao VARCHAR(255),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Modelos
CREATE TABLE IF NOT EXISTS modelos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    requer_calibracao TINYINT(1) NOT NULL DEFAULT 0,
    intervalo_dias INT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Ferramentas
CREATE TABLE IF NOT EXISTS ferramentas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(40) NOT NULL UNIQUE,
    descricao VARCHAR(255) NOT NULL,
    classe_id INT NOT NULL,
    modelo_id INT NOT NULL,
    quantidade_total INT NOT NULL DEFAULT 1,
    quantidade_disponivel INT NOT NULL DEFAULT 1,
    localizacao VARCHAR(120),
    status ENUM('Disponível', 'Emprestada', 'Em manutenção', 'Indisponível') NOT NULL DEFAULT 'Disponível',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (classe_id) REFERENCES classes(id),
    FOREIGN KEY (modelo_id) REFERENCES modelos(id)
);

-- Empréstimos
CREATE TABLE IF NOT EXISTS emprestimos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ferramenta_id INT NOT NULL,
    usuario_id INT NOT NULL,
    operador_id INT NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    data_saida DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_prevista DATE,
    data_retorno DATE,
    status ENUM('Emprestado', 'Devolvido', 'Atrasado') NOT NULL DEFAULT 'Emprestado',
    observacao TEXT,
    FOREIGN KEY (ferramenta_id) REFERENCES ferramentas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (operador_id) REFERENCES usuarios(id)
);

-- Histórico/Auditoria
CREATE TABLE IF NOT EXISTS historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emprestimo_id INT,
    acao VARCHAR(60) NOT NULL,
    descricao TEXT,
    data_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (emprestimo_id) REFERENCES emprestimos(id) ON DELETE SET NULL
);

-- Calibrações agendadas
CREATE TABLE IF NOT EXISTS calibracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ferramenta_id INT NOT NULL,
    ultima_calibracao DATE,
    proxima_calibracao DATE,
    status ENUM('Em dia', 'Próxima', 'Vencida') NOT NULL DEFAULT 'Em dia',
    FOREIGN KEY (ferramenta_id) REFERENCES ferramentas(id)
);

CREATE DATABASE IF NOT EXISTS viano_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE viano_db;

CREATE TABLE IF NOT EXISTS utilizadores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  telefone VARCHAR(32) NULL,
  perfil ENUM('gestor', 'entregador') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_utilizadores_email (email),
  KEY idx_utilizadores_perfil (perfil)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS entregadores_perfil (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  utilizador_id BIGINT UNSIGNED NOT NULL,
  localizacao_inicial_lat DECIMAL(10,7) NOT NULL,
  localizacao_inicial_lng DECIMAL(10,7) NOT NULL,
  localizacao POINT NOT NULL,
  morada_base VARCHAR(255) NOT NULL,
  estado_disponibilidade ENUM('disponivel', 'em_rota', 'inativo') NOT NULL DEFAULT 'disponivel',
  PRIMARY KEY (id),
  UNIQUE KEY uq_entregadores_utilizador (utilizador_id),
  KEY idx_entregadores_estado (estado_disponibilidade),
  SPATIAL KEY spx_entregadores_localizacao (localizacao),
  CONSTRAINT fk_entregadores_utilizador FOREIGN KEY (utilizador_id)
    REFERENCES utilizadores (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS entregas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_nome VARCHAR(120) NOT NULL,
  morada_destino VARCHAR(500) NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  localizacao POINT NOT NULL,
  telefone_contacto VARCHAR(32) NULL,
  notas TEXT NULL,
  estado ENUM('pendente', 'atribuida', 'em_transito', 'entregue', 'falhada') NOT NULL DEFAULT 'pendente',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_entregas_estado_criacao (estado, created_at),
  KEY idx_entregas_created_at (created_at),
  SPATIAL KEY spx_entregas_localizacao (localizacao)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rotas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  gestor_id BIGINT UNSIGNED NOT NULL,
  entregador_id BIGINT UNSIGNED NOT NULL,
  data_rota DATE NOT NULL,
  distancia_total_km DECIMAL(9,2) NOT NULL DEFAULT 0,
  tempo_estimado_min INT UNSIGNED NOT NULL DEFAULT 0,
  estado ENUM('planeada', 'em_curso', 'concluida') NOT NULL DEFAULT 'planeada',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rotas_entregador_estado_data (entregador_id, estado, data_rota),
  KEY idx_rotas_gestor_data (gestor_id, data_rota),
  KEY idx_rotas_data_estado (data_rota, estado),
  CONSTRAINT fk_rotas_gestor FOREIGN KEY (gestor_id)
    REFERENCES utilizadores (id) ON DELETE RESTRICT,
  CONSTRAINT fk_rotas_entregador FOREIGN KEY (entregador_id)
    REFERENCES utilizadores (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rota_paragens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  rota_id BIGINT UNSIGNED NOT NULL,
  entrega_id BIGINT UNSIGNED NOT NULL,
  ordem_sequencia SMALLINT UNSIGNED NOT NULL,
  hora_prevista DATETIME NULL,
  hora_chegada DATETIME NULL,
  hora_conclusao DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rota_ordem (rota_id, ordem_sequencia),
  UNIQUE KEY uq_rota_entrega (rota_id, entrega_id),
  KEY idx_paragens_entrega (entrega_id),
  CONSTRAINT fk_paragens_rota FOREIGN KEY (rota_id)
    REFERENCES rotas (id) ON DELETE CASCADE,
  CONSTRAINT fk_paragens_entrega FOREIGN KEY (entrega_id)
    REFERENCES entregas (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS entregadores_localizacoes (
  entregador_id BIGINT UNSIGNED NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  localizacao POINT NOT NULL,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (entregador_id),
  KEY idx_localizacoes_atualizado (atualizado_em),
  SPATIAL KEY spx_localizacoes_ponto (localizacao),
  CONSTRAINT fk_localizacoes_entregador FOREIGN KEY (entregador_id)
    REFERENCES utilizadores (id) ON DELETE CASCADE
) ENGINE=InnoDB;
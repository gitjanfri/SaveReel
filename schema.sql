-- Schema Salva Reel (MySQL 5.7+/8 o MariaDB 10.3+)
-- Sull'hosting: importarlo da phpMyAdmin nel database creato in cPanel.

CREATE TABLE IF NOT EXISTS utenti (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(190) NOT NULL,
    nome            VARCHAR(100) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    ruolo           ENUM('admin','utente') NOT NULL DEFAULT 'utente',
    token_api_hash  CHAR(64) NULL,
    attivo          TINYINT(1) NOT NULL DEFAULT 1,
    creato_il       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_utenti_email (email),
    UNIQUE KEY uq_utenti_token (token_api_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categorie (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utente_id  INT UNSIGNED NOT NULL,
    nome       VARCHAR(100) NOT NULL,
    creato_il  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_categorie_utente_nome (utente_id, nome),
    CONSTRAINT fk_categorie_utente FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reels (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utente_id      INT UNSIGNED NOT NULL,
    categoria_id   INT UNSIGNED NULL,
    url            VARCHAR(500) NOT NULL,
    titolo         VARCHAR(500) NOT NULL,
    testo          TEXT NULL,
    autore         VARCHAR(255) NULL,
    fonte          ENUM('browser','server','manuale') NOT NULL DEFAULT 'manuale',
    creato_il      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reels_utente_url (utente_id, url),
    KEY ix_reels_utente_data (utente_id, creato_il),
    CONSTRAINT fk_reels_utente FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    CONSTRAINT fk_reels_categoria FOREIGN KEY (categoria_id) REFERENCES categorie(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

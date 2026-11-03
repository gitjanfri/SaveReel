-- Aggiunge le categorie a un database creato con la prima versione di schema.sql.
-- (Le installazioni nuove non ne hanno bisogno: schema.sql include già tutto.)

CREATE TABLE IF NOT EXISTS categorie (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utente_id  INT UNSIGNED NOT NULL,
    nome       VARCHAR(100) NOT NULL,
    creato_il  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_categorie_utente_nome (utente_id, nome),
    CONSTRAINT fk_categorie_utente FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE reels
    ADD COLUMN categoria_id INT UNSIGNED NULL AFTER utente_id,
    ADD CONSTRAINT fk_reels_categoria FOREIGN KEY (categoria_id) REFERENCES categorie(id) ON DELETE SET NULL;

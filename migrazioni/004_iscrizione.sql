-- Iscrizione pubblica: verifica dell'email e limite dei tentativi.
-- Le installazioni nuove non ne hanno bisogno: schema.sql include già tutto.

ALTER TABLE utenti
    ADD COLUMN email_verificata_il DATETIME NULL AFTER attivo,
    ADD COLUMN verifica_token_hash CHAR(64) NULL AFTER email_verificata_il,
    ADD COLUMN verifica_scadenza   DATETIME NULL AFTER verifica_token_hash,
    ADD UNIQUE KEY uq_utenti_verifica (verifica_token_hash);

-- Gli utenti creati finora (da riga di comando) sono considerati verificati.
UPDATE utenti SET email_verificata_il = creato_il WHERE email_verificata_il IS NULL;

CREATE TABLE IF NOT EXISTS tentativi (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    azione     VARCHAR(30) NOT NULL,
    chiave     CHAR(64) NOT NULL,
    creato_il  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_tentativi (azione, chiave, creato_il)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

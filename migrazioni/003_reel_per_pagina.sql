-- Preferenza utente: numero di reel per pagina nell'elenco (default 10).
-- Le installazioni nuove non ne hanno bisogno: schema.sql include già la colonna.

ALTER TABLE utenti
    ADD COLUMN reel_per_pagina SMALLINT UNSIGNED NOT NULL DEFAULT 10 AFTER token_api_hash;

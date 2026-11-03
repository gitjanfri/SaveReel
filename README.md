# Salva Reel

Web app (PHP 8.3 + MySQL) per salvare **titolo, testo e link** dei reel Facebook.
Da PC: un pulsante nei preferiti legge i dati dal reel aperto, apre una pagina di conferma
modificabile e salva nel database. Multiutente: ogni utente vede solo i propri reel.

Produzione: https://reel.facchinigianfranco.it · Piano e cose da fare: [docs/TODO.md](docs/TODO.md)

## Struttura

```
public/          document root (pagine, API, assets)
  index.php      elenco, ricerca, eliminazione
  conferma.php   anteprima modificabile + salvataggio (nuovo o ?id= per modificare)
  account.php    pulsante "Salva reel", token API, cambio password
  export.php     export .txt / .csv
  api/reels.php  API JSON con token (GET elenco, POST salva)
src/             bootstrap (config, DB, sessione, CSRF), auth, logica reel
bin/             crea-utente.php
schema.sql       tabelle utenti e reels
config.php       credenziali (NON in git; copiare da config.example.php)
```

## Sviluppo locale (Docker)

```bash
cp config.example.php config.php
docker compose up -d --build
docker compose exec app php bin/crea-utente.php --email=tua@email.it --nome="Nome" --admin
```

Apri http://localhost:8080. Il DB locale (MariaDB) è su `localhost:3307`, utente/password `salvareel`.
Per ricreare il DB da zero: `docker compose down -v`.

## Pubblicazione su DominiOK (cPanel)

1. **Database**: in cPanel → *Database MySQL* crea database e utente, assegna *tutti i privilegi*.
   In *phpMyAdmin* seleziona il database e importa `schema.sql`.
   Aggiornando un database esistente, importa invece i file in `migrazioni/` non ancora applicati, in ordine.
2. **Primo utente**: in locale genera l'SQL e incollalo in phpMyAdmin (scheda *SQL*):
   `docker compose exec app php bin/crea-utente.php --email=tua@email.it --nome="Nome" --admin --sql`
3. **File**: carica via File Manager/FTP la cartella del progetto **senza** `.git`, `docker/`,
   `docker-compose.yml`, `docs/`, ad esempio in `~/salvareel/` (fuori da `public_html`).
4. **Sottodominio**: in cPanel → *Domini* imposta la document root di `reel.facchinigianfranco.it`
   su `~/salvareel/public`. Attiva SSL (AutoSSL/Let's Encrypt).
5. **Configurazione**: crea `~/salvareel/config.php` da `config.example.php` con
   `base_url = https://reel.facchinigianfranco.it`, `debug = false`, `host = localhost` e le credenziali del punto 1.
6. **Verifica**: apri il sito, accedi, vai su *Account* e trascina il pulsante "Salva reel" nei preferiti.

## API

```
GET  /api/reels.php?q=testo&categoria=nome Authorization: Bearer <token>
POST /api/reels.php                {"url": "...", "titolo": "...", "testo": "...", "autore": "...", "categoria": "...", "fonte": "server"}
```
Il token si genera nella pagina *Account* (viene mostrato una sola volta).

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
schema.sql       tabelle utenti, categorie e reels (migrazioni/ per database esistenti)
docker/php/      immagine di sviluppo · docker/prod/ immagine di produzione
docs/deploy/     esempi di configurazione server (nginx)
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

### Creare utenti

```bash
docker compose exec app php bin/crea-utente.php --email=tua@email.it --nome="Nome" [--admin] [--sql] [--min=N]
```

| Opzione | Significato |
|---|---|
| `--admin` | ruolo amministratore (altrimenti `utente`) |
| `--sql` | non scrive nel DB: stampa l'`INSERT` da incollare in phpMyAdmin |
| `--min=N` | lunghezza minima della password per questa esecuzione |
| `--password=...` | password in chiaro sulla riga di comando (sconsigliato: resta nella cronologia) |

La lunghezza minima vale, in ordine: `--min=N`, poi `'password_min'` in `config.php`
(la stessa regola usata dal cambio password nella pagina *Account*), poi 10. Non scende mai sotto 8.

## Pubblicazione (qualsiasi hosting)

L'app non dipende da DominiOK: funziona su qualunque server con

- **PHP 8.1+** (consigliato 8.3) con le estensioni `pdo_mysql` e `mbstring`;
- **MySQL 5.7+/8 o MariaDB 10.3+**;
- **HTTPS** (necessario: il pulsante "Salva reel" parte da una pagina https di Facebook);
- un dominio/sottodominio la cui **document root è la cartella `public/`**: `src/`, `bin/`
  e `config.php` devono restare fuori dalla parte raggiungibile dal web.

Passi comuni, validi ovunque:

1. **Database**: crea database e utente con tutti i privilegi, importa `schema.sql`.
   Aggiornando un database esistente, importa invece i file in `migrazioni/` non ancora applicati, in ordine.
2. **Primo utente**: con accesso SSH esegui `php bin/crea-utente.php ... --admin` sul server;
   senza SSH genera l'SQL in locale con `--sql` e importalo (phpMyAdmin, Adminer, client MySQL).
3. **File**: copia il progetto **senza** `.git`, `docker/`, `docker-compose*.yml`, `docs/`, `config.php` locale.
4. **Document root** del dominio su `<cartella progetto>/public` e certificato SSL attivo.
5. **Configurazione**: crea `config.php` da `config.example.php` con il `base_url` pubblico (https),
   `debug = false`, i dati del database (di solito `host = localhost`) ed eventualmente `password_min`.
6. **Verifica**: apri il sito, accedi, vai su *Account* e trascina il pulsante "Salva reel" nei preferiti.

Per cambiare hosting basta esportare il database (mysqldump/phpMyAdmin), reimportarlo nel nuovo
e adattare `base_url` e credenziali in `config.php`.

### Web server

- **Apache** (hosting condivisi, cPanel, Plesk, `php:apache`): nessuna configurazione, bastano i due `.htaccess`
  (blocco della cartella principale, header `Authorization` per l'API, redirect a HTTPS).
  Serve `AllowOverride All` (o almeno `FileInfo Options Indexes AuthConfig`), già attivo sugli hosting condivisi.
- **nginx + PHP-FPM**: i `.htaccess` sono ignorati; usa le regole equivalenti in
  [docs/deploy/nginx.conf.example](docs/deploy/nginx.conf.example).
- **Dietro un reverse proxy** (Caddy, Traefik, Cloudflare Tunnel): il proxy deve inviare
  `X-Forwarded-Proto: https`, così l'app riconosce l'HTTPS (cookie sicuri) e non va in loop di redirect.

### Esempio: DominiOK (cPanel)

1. cPanel → *Database MySQL*: crea database e utente, assegna *tutti i privilegi*; in *phpMyAdmin* importa `schema.sql`.
2. Primo utente con `--sql` (se il piano non include SSH/Terminale) e incolla l'SQL nella scheda *SQL* di phpMyAdmin.
3. File Manager/FTP: carica il progetto in `~/salvareel/` (fuori da `public_html`).
4. cPanel → *Domini*: document root di `reel.facchinigianfranco.it` = `~/salvareel/public`; attiva AutoSSL.
5. `~/salvareel/config.php` con `base_url = https://reel.facchinigianfranco.it`, `debug = false`, `host = localhost`.

### Esempio: container Docker (VPS, PC di casa, cloud)

`docker/prod/Dockerfile` crea un'immagine con il codice dentro (a differenza di quella di sviluppo,
che monta la cartella). `config.php` non è nell'immagine: si monta in sola lettura.

```bash
cp config.example.php config.php        # db.host = 'db', debug = false, base_url pubblico
printf 'DB_PASSWORD=...\nDB_ROOT_PASSWORD=...\n' > .env   # stessa DB_PASSWORD in config.php
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php bin/crea-utente.php --email=... --nome="..." --admin
```

L'app ascolta su `127.0.0.1:8080`: davanti serve un reverse proxy con HTTPS
(es. Caddy: `reel.esempio.it { reverse_proxy 127.0.0.1:8080 }`), oppure un Cloudflare Tunnel sul PC di casa.
Con un database esterno/gestito basta togliere il servizio `db` e puntare `config.php` al suo host.
Backup: `docker compose -f docker-compose.prod.yml exec db mariadb-dump -u root -p salvareel > backup.sql`.

## API

```
GET  /api/reels.php?q=testo&categoria=nome Authorization: Bearer <token>
POST /api/reels.php                {"url": "...", "titolo": "...", "testo": "...", "autore": "...", "categoria": "...", "fonte": "server"}
```
Il token si genera nella pagina *Account* (viene mostrato una sola volta).

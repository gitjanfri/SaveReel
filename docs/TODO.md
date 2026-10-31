# Salva Reel – note e TODO

Obiettivo: salvare **titolo, testo (didascalia) e link** di reel Facebook di terzi
(es. `https://www.facebook.com/reel/1441013027406219`) tramite una web app sul mio dominio.
Se il reel non ha testo, bastano titolo + link.

> Approccio precedente (lettura email da cartella Outlook) **abbandonato**:
> non servono più Outlook, Microsoft Graph né Azure.

## Decisioni prese

- **Priorità: Fase 1 (web app PHP)**. La Fase 2 (lettura lato server con Docker/yt-dlp) si valuta solo dopo.
- **Hosting**: quello già esistente su DominiOK (cPanel su CloudLinux, PHP 8.x, MySQL/MariaDB, SSL gratuito, cron).
- **Database**: MySQL già esistente, **in entrambe le fasi**.
- **Utenti**: progettata **multiutente** fin da subito, ma per ora l'unico utente sono io.
  Ogni reel appartiene a un utente; nessuna registrazione pubblica (gli utenti li crea l'amministratore).
- **Un solo backend (PHP) scrive nel DB**. Tutto il resto (bookmarklet, condivisione da telefono, lettore yt-dlp)
  passa dalla sua API HTTPS con token, senza collegarsi direttamente a MySQL.
  → Il DB resta raggiungibile anche quando si aggiungerà la Fase 2, ovunque giri il lettore.

## Perché il browser e non il server (verificato)

- La API ufficiale di Facebook (Graph) funziona solo per Pagine gestite da me → **non utilizzabile** per reel di terzi.
- Una richiesta anonima dal server al link del reel restituisce una pagina vuota ("Facebook"), senza titolo né testo.
- Il mio browser invece è già loggato: un bookmarklet può leggere titolo e didascalia direttamente dalla pagina aperta.

## Costi

| Cosa | Costo |
|---|---|
| Fase 1 (web app + bookmarklet su hosting attuale) | **0 €** in più |
| Fase 2 – yt-dlp sull'hosting (se cPanel ha "Setup Python App"/Terminale) | 0 € |
| Fase 2 – yt-dlp su PC di casa con Docker (deve restare acceso) | 0 € |
| Fase 2 – VPS gratuito (es. Oracle Cloud Always Free, serve carta per la registrazione) | 0 € |
| Fase 2 – VPS economico esterno (es. Hetzner) | ~4–6 €/mese |
| Fase 2 – VPS DominiOK | da 400 €/anno (sproporzionato) |
| Account Facebook secondario per i cookie (Fase 2) | 0 € |

---

## Fase 1 – Web app PHP + MySQL (PRIORITÀ) · stima ~2 giorni

Flusso: sono sul reel (PC) → clic sul bookmarklet → si apre la pagina di conferma precompilata
(titolo, testo, link, modificabili) → **Conferma** → salvato in MySQL.

### Struttura prevista
```
public/              # document root (es. sottodominio reel.<dominio>)
  index.php          # elenco + ricerca
  conferma.php       # anteprima modificabile, salva
  login.php
  api/reels.php      # POST salva/aggiorna, GET elenco (sessione o token)
  api/export.php     # export .txt / .csv
  bookmarklet.js
src/
  db.php             # PDO MySQL
  auth.php
  reel.php           # normalizzazione URL facebook.com/reel/<id>
config.php           # credenziali DB (fuori dal document root, escluso da git)
schema.sql
```

Tabelle:
- `utenti`: `id, email (UNIQUE), nome, password_hash, ruolo ENUM('admin','utente'), token_api_hash, attivo, creato_il`
- `reels`: `id, utente_id (FK utenti), url (normalizzato), titolo, testo NULL, autore NULL,
  fonte ENUM('browser','server','manuale'), creato_il, aggiornato_il` — `UNIQUE(utente_id, url)`:
  lo stesso reel può essere salvato da utenti diversi, ma non duplicato dallo stesso utente.

Multiutente in pratica:
- Ogni utente vede/esporta solo i propri reel; il token del bookmarklet è personale (salvato come hash, rigenerabile).
- Password con `password_hash()`; sessioni sicure (cookie HttpOnly/Secure), protezione CSRF sui form.
- Per ora un solo utente admin creato con uno script/SQL iniziale; pagina di gestione utenti più avanti.

### TODO
- [ ] Verificare in cPanel: versione PHP attiva, creazione nuovo DB MySQL e utente, sottodominio
- [x] Decidere se l'app è solo per me o multiutente → multiutente, per ora solo io
- [ ] Ambiente di sviluppo locale con Docker compose (php:8.3-apache + mariadb) che replica l'hosting
- [ ] `schema.sql` + connessione PDO + `config.php` fuori dal document root
- [ ] Tabella `utenti` + script di creazione del primo utente admin
- [ ] Login + token personale per il bookmarklet (ogni utente vede solo i propri reel)
- [ ] (più avanti) Pagina admin per creare/disattivare utenti
- [ ] API `POST/GET /api/reels` con normalizzazione URL (nessun duplicato: risalvare aggiorna)
- [ ] Pagina di conferma con anteprima modificabile
- [ ] Elenco reel salvati, ricerca, export .txt/.csv
- [ ] Bookmarklet: legge titolo e didascalia dal DOM del reel e apre la conferma precompilata
- [ ] Deploy su DominiOK (FTP/File Manager), HTTPS, backup del DB
- [ ] Test con il reel d'esempio: salvataggio, risalvataggio senza duplicati, reel senza testo

---

## Fase 2 – Lettura lato server con yt-dlp / Docker (DA VALUTARE DOPO) · stima ~1,5–2 giorni

Scopo: salvare un reel **dal telefono** (app Facebook → Condividi → web app) senza bookmarklet.
Il server legge titolo/didascalia con **yt-dlp** (solo metadati, nessun download) usando i cookie
di un account Facebook dedicato, mostra l'anteprima, l'utente conferma, il salvataggio passa dalla stessa API PHP.

### Da valutare
- [ ] In cPanel esiste "Setup Python App" / "Terminale" / `exec` abilitato? → yt-dlp sull'hosting a 0 €
- [ ] Altrimenti scegliere dove far girare yt-dlp in Docker: PC di casa, VPS gratuito, VPS economico
- [ ] Ci serve davvero? (se salvo soprattutto da PC, la Fase 1 basta)

### TODO (se si procede)
- [ ] Servizio yt-dlp (Docker) con endpoint `POST /api/fetch` → restituisce anteprima, non salva
- [ ] Gestione cookie Facebook di un account secondario (rinnovo periodico)
- [ ] PWA con Web Share Target (Android); Comando Rapido per iPhone (Share Target non supportato su iOS)
- [ ] Fallback: se la lettura fallisce, anteprima con solo il link da completare a mano

### Rischi
- I Termini di Meta vietano l'automazione → ritmo basso, account secondario
- I cookie scadono → rinnovo manuale
- Facebook può bloccare letture da IP di server

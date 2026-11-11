<?php
declare(strict_types=1);

// Iscrizione pubblica con conferma dell'email.
//
// Regole di sicurezza:
// - la risposta al modulo è sempre la stessa (non rivela se un'email è già registrata);
// - l'account resta inattivo finché il link ricevuto per email non viene confermato
//   inserendo la password scelta: chi iscrive l'email di un altro non può attivarla;
// - limiti per IP e per email contro iscrizioni di massa e invii ripetuti di email;
// - campo trappola e tempo minimo di compilazione contro i bot più semplici.

const VERIFICA_DURATA_ORE = 24;
const ISCRIZIONE_TEMPO_MINIMO = 3;   // secondi tra apertura e invio del modulo
const PASSWORD_MAX_BYTE = 72;        // oltre, bcrypt ignora i caratteri

function iscrizioni_aperte(): bool
{
    return (bool) config('iscrizioni_aperte', true);
}

// ---- Limite tentativi ----

function ip_client(): string
{
    // Solo REMOTE_ADDR: X-Forwarded-For è falsificabile. Dietro un reverse proxy tutti
    // risultano con l'IP del proxy: in quel caso i limiti per IP diventano globali.
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
}

/** true se per (azione, chiave) ci sono già $max tentativi negli ultimi $secondi. */
function limite_superato(string $azione, string $chiave, int $max, int $secondi): bool
{
    $st = db()->prepare('SELECT COUNT(*) FROM tentativi
                         WHERE azione = ? AND chiave = ? AND creato_il > NOW() - INTERVAL ? SECOND');
    $st->execute([$azione, hash('sha256', $chiave), $secondi]);
    return (int) $st->fetchColumn() >= $max;
}

function registra_tentativo(string $azione, string $chiave): void
{
    db()->prepare('INSERT INTO tentativi (azione, chiave) VALUES (?, ?)')
        ->execute([$azione, hash('sha256', $chiave)]);
    if (random_int(1, 50) === 1) {
        db()->exec('DELETE FROM tentativi WHERE creato_il < NOW() - INTERVAL 2 DAY');
    }
}

// ---- Email ----

/**
 * Invia un'email di testo. Con 'mail' => ['modo' => 'log'] (predefinito con debug attivo)
 * la scrive in var/mail.log invece di inviarla: utile in locale, dove non c'è un server di posta.
 */
function invia_email(string $a, string $oggetto, string $testo): bool
{
    if (!filter_var($a, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $mail = (array) config('mail', []);
    $modo = $mail['modo'] ?? (config('debug') ? 'log' : 'mail');
    $host = parse_url((string) config('base_url'), PHP_URL_HOST) ?: 'localhost';
    $mittente = (string) ($mail['mittente'] ?? 'noreply@' . $host);
    $mittente = str_replace(["\r", "\n"], '', $mittente);

    if ($modo === 'log') {
        $dir = __DIR__ . '/../var';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $riga = sprintf("==== %s\nA: %s\nOggetto: %s\n\n%s\n\n", date('c'), $a, $oggetto, $testo);
        return file_put_contents($dir . '/mail.log', $riga, FILE_APPEND | LOCK_EX) !== false;
    }

    $intestazioni = [
        'From'                      => 'Salva Reel <' . $mittente . '>',
        'MIME-Version'              => '1.0',
        'Content-Type'              => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
    ];
    $parametri = filter_var($mittente, FILTER_VALIDATE_EMAIL) ? '-f' . $mittente : '';
    $ok = mail($a, mb_encode_mimeheader($oggetto, 'UTF-8'), $testo, $intestazioni, $parametri);
    if (!$ok) {
        // All'utente mostriamo comunque lo stesso messaggio: l'errore resta nel log del server
        error_log("Salva Reel: invio email non riuscito (oggetto: $oggetto)");
    }
    return $ok;
}

// ---- Iscrizione ----

/**
 * Prepara il modulo: memorizza l'ora della prima apertura per il controllo anti-bot.
 * Non la aggiorna quando il modulo viene ripresentato con errori, altrimenti chi corregge
 * in fretta un campo verrebbe scambiato per un bot (e non riceverebbe l'email).
 */
function iscrizione_apri_modulo(): void
{
    $_SESSION['iscrizione_aperta_il'] ??= time();
}

/**
 * Valida i dati del modulo. Restituisce gli errori per campo (vuoto = tutto ok).
 * Gli errori riguardano solo il formato: mai "email già registrata".
 */
function iscrizione_valida(array $d): array
{
    $err = [];
    if ($d['nome'] === '' || mb_strlen($d['nome']) > 100) {
        $err['nome'] = 'Inserisci un nome (massimo 100 caratteri).';
    }
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($d['email']) > 190) {
        $err['email'] = 'Inserisci un indirizzo email valido.';
    }
    if (mb_strlen($d['password']) < password_min()) {
        $err['password'] = 'La password deve avere almeno ' . password_min() . ' caratteri.';
    } elseif (strlen($d['password']) > PASSWORD_MAX_BYTE) {
        $err['password'] = 'La password è troppo lunga (massimo ' . PASSWORD_MAX_BYTE . ' caratteri).';
    } elseif (!hash_equals($d['password'], $d['conferma'])) {
        $err['conferma'] = 'Le due password non coincidono.';
    }
    return $err;
}

/** true se la richiesta sembra di un bot (campo trappola compilato o modulo inviato troppo in fretta). */
function iscrizione_sospetta(array $post): bool
{
    $aperto = (int) ($_SESSION['iscrizione_aperta_il'] ?? 0);
    return trim((string) ($post['sito_web'] ?? '')) !== ''
        || $aperto === 0
        || time() - $aperto < ISCRIZIONE_TEMPO_MINIMO;
}

/**
 * Registra (o rinnova) l'iscrizione e invia l'email. Il chiamante mostra sempre lo stesso messaggio.
 *  - email nuova: crea l'utente non verificato e invia il link;
 *  - email in attesa di conferma: aggiorna nome e password, invalida il link precedente e ne invia uno nuovo;
 *  - email già attiva: non cambia nulla e avvisa il titolare che c'è già un account.
 */
function iscrizione_registra(string $nome, string $email, string $password): void
{
    // Calcolato sempre, per tempi di risposta simili in tutti i casi
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $base = rtrim((string) config('base_url'), '/');

    // Massimo 3 email all'ora verso lo stesso indirizzo (evita di usare il sito per tempestare qualcuno)
    if (limite_superato('email_iscrizione', $email, 3, 3600)) {
        return;
    }
    registra_tentativo('email_iscrizione', $email);

    $st = db()->prepare('SELECT id, email_verificata_il FROM utenti WHERE email = ?');
    $st->execute([$email]);
    $esistente = $st->fetch();

    if ($esistente && $esistente['email_verificata_il'] !== null) {
        invia_email($email, 'Hai già un account Salva Reel',
            "Ciao,\n\nqualcuno (forse tu) ha provato a iscriversi a Salva Reel con questo indirizzo,"
            . " ma esiste già un account.\n\nPer accedere: $base/login.php\n\n"
            . "Se non sei stato tu puoi ignorare questo messaggio: il tuo account non è stato modificato.\n");
        return;
    }

    if ($esistente) {
        $id = (int) $esistente['id'];
        db()->prepare('UPDATE utenti SET nome = ?, password_hash = ? WHERE id = ? AND email_verificata_il IS NULL')
            ->execute([$nome, $hash, $id]);
    } else {
        try {
            db()->prepare('INSERT INTO utenti (email, nome, password_hash, ruolo) VALUES (?, ?, ?, \'utente\')')
                ->execute([$email, $nome, $hash]);
            $id = (int) db()->lastInsertId();
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                return; // iscrizione contemporanea con la stessa email
            }
            throw $ex;
        }
    }

    iscrizione_invia_link($id, $nome, $email);
}

/**
 * Reinvia il link di conferma a un account in attesa (chiamato dal login, dopo che l'utente
 * ha inserito email e password giuste). false se il limite di invii per l'indirizzo è superato.
 */
function iscrizione_reinvia(int $id): bool
{
    $st = db()->prepare('SELECT id, nome, email FROM utenti WHERE id = ? AND email_verificata_il IS NULL AND attivo = 1');
    $st->execute([$id]);
    $u = $st->fetch();
    if (!$u) {
        return true; // già confermato nel frattempo: niente da inviare
    }
    if (limite_superato('email_iscrizione', $u['email'], 3, 3600)) {
        return false;
    }
    registra_tentativo('email_iscrizione', $u['email']);
    iscrizione_invia_link((int) $u['id'], $u['nome'], $u['email']);
    return true;
}

/** Genera un nuovo link di conferma (invalidando il precedente) e lo invia per email. */
function iscrizione_invia_link(int $id, string $nome, string $email): void
{
    $base = rtrim((string) config('base_url'), '/');
    $token = bin2hex(random_bytes(32));
    $scadenza = date('Y-m-d H:i:s', time() + VERIFICA_DURATA_ORE * 3600);
    db()->prepare('UPDATE utenti SET verifica_token_hash = ?, verifica_scadenza = ? WHERE id = ? AND email_verificata_il IS NULL')
        ->execute([hash('sha256', $token), $scadenza, $id]);

    invia_email($email, 'Conferma la tua iscrizione a Salva Reel',
        "Ciao $nome,\n\nper attivare il tuo account Salva Reel apri questo link entro "
        . VERIFICA_DURATA_ORE . " ore e inserisci la password che hai scelto:\n\n"
        . "$base/verifica.php?t=$token\n\n"
        . "Se non ti sei iscritto tu, ignora questo messaggio: senza conferma l'account non viene attivato.\n");
}

/** Utente in attesa di conferma per il token, o null se il token non è valido o è scaduto. */
function iscrizione_da_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $st = db()->prepare('SELECT id, nome, email, password_hash FROM utenti
                         WHERE verifica_token_hash = ? AND verifica_scadenza > NOW()
                           AND email_verificata_il IS NULL AND attivo = 1');
    $st->execute([hash('sha256', $token)]);
    return $st->fetch() ?: null;
}

/** Conferma l'email se la password corrisponde a quella scelta in iscrizione; poi accede. */
function iscrizione_conferma(string $token, string $password): bool
{
    $u = iscrizione_da_token($token);
    if (!$u || !password_verify($password, $u['password_hash'])) {
        sleep(1);
        return false;
    }
    db()->prepare('UPDATE utenti SET email_verificata_il = NOW(), verifica_token_hash = NULL, verifica_scadenza = NULL
                   WHERE id = ?')
        ->execute([$u['id']]);
    session_regenerate_id(true);
    $_SESSION['utente_id'] = (int) $u['id'];
    unset($_SESSION['iscrizione_aperta_il']);
    return true;
}

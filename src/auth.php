<?php
declare(strict_types=1);

// Autenticazione: sessione per le pagine, token personale per l'API.

function utente_corrente(): ?array
{
    static $utente = false;
    if ($utente !== false) {
        return $utente;
    }
    $utente = null;
    $id = $_SESSION['utente_id'] ?? null;
    if ($id) {
        $st = db()->prepare('SELECT id, email, nome, ruolo, reel_per_pagina FROM utenti WHERE id = ? AND attivo = 1');
        $st->execute([$id]);
        $utente = $st->fetch() ?: null;
        if (!$utente) {
            unset($_SESSION['utente_id']);
        }
    }
    return $utente;
}

/** Pagina riservata: se non loggato manda al login e poi torna qui. */
function richiedi_login(): array
{
    $u = utente_corrente();
    if (!$u) {
        redirect('/login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/'));
    }
    return $u;
}

function login(string $email, string $password): bool
{
    $st = db()->prepare('SELECT id, password_hash FROM utenti WHERE email = ? AND attivo = 1');
    $st->execute([mb_strtolower(trim($email))]);
    $row = $st->fetch();
    // password_verify anche se l'utente non esiste, per tempi di risposta uniformi
    $hash = $row['password_hash'] ?? '$2y$10$usesomesillystringforsaltusesomesillystringfore.abcdefghijk';
    if (!password_verify($password, $hash) || !$row) {
        sleep(1); // rallenta i tentativi a forza bruta
        return false;
    }
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE utenti SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['utente_id'] = (int) $row['id'];
    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Destinazione sicura dopo il login: solo percorsi locali. */
function next_sicuro(?string $next): string
{
    // Solo percorsi locali con caratteri sicuri: niente spazi/tab/a capo (i browser li rimuovono,
    // e "/\t/evil.it" diventerebbe "//evil.it"), niente "//" o "\" iniziali.
    if (!is_string($next) || !preg_match('~^/(?![/\\\\])[A-Za-z0-9/_.\-?=&%]*$~', $next)) {
        return '/';
    }
    return $next;
}

// ---- Token API (per future integrazioni e per la Fase 2) ----

/** Genera un nuovo token, ne salva l'hash e restituisce il token in chiaro (mostrato una sola volta). */
function genera_token_api(int $utenteId): string
{
    $token = bin2hex(random_bytes(32));
    db()->prepare('UPDATE utenti SET token_api_hash = ? WHERE id = ?')
        ->execute([hash('sha256', $token), $utenteId]);
    return $token;
}

function revoca_token_api(int $utenteId): void
{
    db()->prepare('UPDATE utenti SET token_api_hash = NULL WHERE id = ?')->execute([$utenteId]);
}

function utente_da_token(): ?array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $token = '';
    if (preg_match('/^Bearer\s+([a-f0-9]{64})$/i', trim($header), $m)) {
        $token = $m[1];
    } elseif (preg_match('/^[a-f0-9]{64}$/i', $_SERVER['HTTP_X_API_TOKEN'] ?? '')) {
        $token = $_SERVER['HTTP_X_API_TOKEN'];
    }
    if ($token === '') {
        return null;
    }
    $st = db()->prepare('SELECT id, email, nome, ruolo FROM utenti WHERE token_api_hash = ? AND attivo = 1');
    $st->execute([hash('sha256', strtolower($token))]);
    return $st->fetch() ?: null;
}

/** Valore minimo assoluto, anche se config.php indica meno. */
const PASSWORD_MIN_ASSOLUTO = 8;

/** Lunghezza minima delle password: 'password_min' in config.php (default 10). */
function password_min(): int
{
    return max(PASSWORD_MIN_ASSOLUTO, (int) config('password_min', 10));
}

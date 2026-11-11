<?php
declare(strict_types=1);

// Caricato da ogni pagina: configurazione, DB, sessione, utilità comuni.

$configFile = __DIR__ . '/../config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('config.php mancante: copia config.example.php in config.php');
}
$GLOBALS['config'] = require $configFile;

date_default_timezone_set('Europe/Rome');
ini_set('display_errors', config('debug') ? '1' : '0');
error_reporting(E_ALL);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/reel.php';
require_once __DIR__ . '/iscrizione.php';

function config(string $key, mixed $default = null): mixed
{
    return $GLOBALS['config'][$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'] ?? 3306, $c['name']);
        $pdo = new PDO($dsn, $c['user'], $c['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/** Escape per HTML. */
function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function avvia_sessione(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('salvareel');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,  // 30 giorni
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',             // Lax: il bookmarklet apre la pagina da facebook.com e la sessione resta valida
    ]);
    ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 30));
    session_start();
}

function intestazioni_sicurezza(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
}

// ---- CSRF ----

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verifica(): void
{
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(400);
        exit('Richiesta non valida (token CSRF). Ricarica la pagina e riprova.');
    }
}

// ---- Messaggi flash ----

function flash(string $msg, string $tipo = 'ok'): void
{
    $_SESSION['flash'][] = [$tipo, $msg];
}

function flash_html(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as [$tipo, $msg]) {
        $out .= '<p class="flash flash-' . e($tipo) . '">' . e($msg) . '</p>';
    }
    unset($_SESSION['flash']);
    return $out;
}

// ---- Layout ----

/** URL di un file in public/assets con versione (data di modifica): il browser ricarica dopo ogni aggiornamento. */
function asset(string $file): string
{
    $percorso = __DIR__ . '/../public/assets/' . $file;
    return '/assets/' . $file . (is_file($percorso) ? '?v=' . filemtime($percorso) : '');
}

function pagina_inizio(string $titolo): void
{
    $u = utente_corrente();
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($titolo) . ' · Salva Reel</title>'
        . '<link rel="stylesheet" href="' . e(asset('style.css')) . '"></head><body>'
        . '<header class="top"><a class="logo" href="/">Salva Reel</a>';
    if ($u) {
        echo '<nav><a href="/elenco.php">I miei reel</a><a href="/conferma.php">Nuovo</a>'
            . '<a href="/account.php">Account</a>'
            . '<form method="post" action="/logout.php" class="inline">' . csrf_campo()
            . '<button class="link">Esci</button></form></nav>';
    } else {
        echo '<nav><a href="/login.php">Accedi</a>'
            . (iscrizioni_aperte() ? '<a href="/iscrizione.php" class="bottone piccolo">Iscriviti</a>' : '')
            . '</nav>';
    }
    echo '</header><main>' . flash_html();
}

function pagina_fine(): void
{
    echo '</main></body></html>';
}

avvia_sessione();

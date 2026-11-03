<?php
declare(strict_types=1);
// Crea un utente.
//
// In locale (inserisce direttamente nel DB Docker):
//   docker compose exec app php bin/crea-utente.php --email=io@esempio.it --nome="Gianfranco" --admin
//
// Per l'hosting (senza SSH): stampa l'SQL da incollare in phpMyAdmin
//   docker compose exec app php bin/crea-utente.php --email=io@esempio.it --nome="Gianfranco" --admin --sql
//
// La password viene chiesta in modo interattivo, oppure passata con --password=... (sconsigliato).
//
// Lunghezza minima della password, in ordine di priorità:
//   --min=N                      opzione da riga di comando
//   'password_min' in config.php la stessa usata dalla pagina Account
//   10                           predefinita
// In ogni caso non scende mai sotto 8.

if (PHP_SAPI !== 'cli') {
    exit('Solo da riga di comando');
}

const MIN_ASSOLUTO = 8;
const MIN_PREDEFINITO = 10;

$o = getopt('', ['email:', 'nome:', 'password::', 'admin', 'sql', 'min:']);
$email = mb_strtolower(trim((string) ($o['email'] ?? '')));
$nome = trim((string) ($o['nome'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $nome === '') {
    fwrite(STDERR, "Uso: php bin/crea-utente.php --email=... --nome=... [--admin] [--sql] [--min=N] [--password=...]\n");
    exit(1);
}

// config.php è facoltativo per --sql: se manca si usa il valore predefinito.
$configFile = __DIR__ . '/../config.php';
$cfg = is_file($configFile) ? (require $configFile) : [];
if (isset($o['min'])) {
    if (!ctype_digit((string) $o['min'])) {
        fwrite(STDERR, "--min deve essere un numero intero.\n");
        exit(1);
    }
    $min = (int) $o['min'];
} else {
    $min = (int) ($cfg['password_min'] ?? MIN_PREDEFINITO);
}
if ($min < MIN_ASSOLUTO) {
    fwrite(STDERR, "Minimo richiesto $min troppo basso: uso " . MIN_ASSOLUTO . ".\n");
    $min = MIN_ASSOLUTO;
}

$password = (string) ($o['password'] ?? '');
if ($password === '') {
    fwrite(STDOUT, "Password (min. $min caratteri): ");
    if (DIRECTORY_SEPARATOR === '/') {
        shell_exec('stty -echo');
    }
    $password = trim((string) fgets(STDIN));
    if (DIRECTORY_SEPARATOR === '/') {
        shell_exec('stty echo');
    }
    fwrite(STDOUT, "\n");
}
if (mb_strlen($password) < $min) {
    fwrite(STDERR, "Password troppo corta (min. $min caratteri).\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$ruolo = isset($o['admin']) ? 'admin' : 'utente';

if (isset($o['sql'])) {
    $q = fn(string $s) => "'" . str_replace(["\\", "'"], ["\\\\", "''"], $s) . "'";
    echo "INSERT INTO utenti (email, nome, password_hash, ruolo) VALUES ({$q($email)}, {$q($nome)}, {$q($hash)}, {$q($ruolo)});\n";
    exit(0);
}

$_SERVER['REQUEST_METHOD'] = 'CLI';
require __DIR__ . '/../src/bootstrap.php';
try {
    db()->prepare('INSERT INTO utenti (email, nome, password_hash, ruolo) VALUES (?, ?, ?, ?)')
        ->execute([$email, $nome, $hash, $ruolo]);
    echo "Utente creato: $email ($ruolo)\n";
} catch (PDOException $ex) {
    fwrite(STDERR, 'Errore: ' . ($ex->getCode() === '23000' ? 'email già presente' : $ex->getMessage()) . "\n");
    exit(1);
}

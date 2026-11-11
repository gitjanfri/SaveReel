<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
intestazioni_sicurezza();
// Il token è nell'URL: non deve finire nei log di proxy/cache né essere riusato da altri siti
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

if (utente_corrente()) {
    redirect('/elenco.php');
}

$token = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$errore = '';

// Il link da solo non attiva nulla (i filtri antispam aprono i link delle email):
// serve la password scelta in iscrizione, inviata con il modulo.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    if (limite_superato('verifica_ip', ip_client(), 10, 900)) {
        http_response_code(429);
        $errore = 'Troppi tentativi. Riprova tra qualche minuto.';
    } else {
        registra_tentativo('verifica_ip', ip_client());
        if (iscrizione_conferma($token, (string) ($_POST['password'] ?? ''))) {
            flash('Account attivato, benvenuto! Per iniziare installa il pulsante "Salva reel" qui sotto.');
            redirect('/account.php');
        }
        $errore = 'Password non corretta, oppure link scaduto o già usato.';
    }
}

$valido = iscrizione_da_token($token) !== null;

pagina_inizio('Conferma iscrizione');
?>
<section class="card stretta">
<?php if (!$valido): ?>
  <h1>Link non valido</h1>
  <p>Il link è scaduto, è già stato usato oppure è stato sostituito da un'iscrizione più recente.</p>
  <p><a href="/iscrizione.php" class="bottone">Iscriviti di nuovo</a> <a href="/login.php" class="bottone secondario">Accedi</a></p>
<?php else: ?>
  <h1>Conferma l'iscrizione</h1>
  <p>Inserisci la password che hai scelto quando ti sei iscritto per attivare l'account.</p>
  <?php if ($errore): ?><p class="flash flash-errore"><?= e($errore) ?></p><?php endif; ?>
  <form method="post" action="/verifica.php">
    <?= csrf_campo() ?>
    <input type="hidden" name="t" value="<?= e($token) ?>">
    <label>Password <input type="password" name="password" required autocomplete="current-password" autofocus></label>
    <button type="submit">Attiva l'account</button>
  </form>
  <p class="nota">Non ti sei iscritto tu? Ignora questa pagina: senza la password l'account non viene attivato.</p>
<?php endif; ?>
</section>
<?php
pagina_fine();

<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
intestazioni_sicurezza();

if (utente_corrente()) {
    redirect('/elenco.php');
}

const ISCRIZIONI_PER_IP_ORA = 5;

$dati = ['nome' => '', 'email' => ''];
$errori = [];
$bloccato = false;

if (iscrizioni_aperte() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $ip = ip_client();
    if (limite_superato('iscrizione_ip', $ip, ISCRIZIONI_PER_IP_ORA, 3600)) {
        http_response_code(429);
        $bloccato = true;
    } else {
        $dati = [
            'nome'     => trim((string) ($_POST['nome'] ?? '')),
            'email'    => mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
            'password' => (string) ($_POST['password'] ?? ''),
            'conferma' => (string) ($_POST['conferma'] ?? ''),
        ];
        if (iscrizione_sospetta($_POST)) {
            // Ai bot rispondiamo come se fosse andata bene, senza fare nulla
            registra_tentativo('iscrizione_ip', $ip);
            $_SESSION['iscrizione_inviata'] = true;
            redirect('/iscrizione.php');
        }
        $errori = iscrizione_valida($dati);
        if (!$errori) {
            registra_tentativo('iscrizione_ip', $ip);
            iscrizione_registra($dati['nome'], $dati['email'], $dati['password']);
            unset($_SESSION['iscrizione_aperta_il']);
            // Stesso messaggio in ogni caso: non riveliamo se l'email era già registrata
            $_SESSION['iscrizione_inviata'] = true;
            redirect('/iscrizione.php');
        }
    }
}

$inviata = !empty($_SESSION['iscrizione_inviata']);
unset($_SESSION['iscrizione_inviata']);
if (!$inviata) {
    iscrizione_apri_modulo();
}

pagina_inizio('Iscriviti');
?>
<section class="card stretta">
<?php if (!iscrizioni_aperte()): ?>
  <h1>Iscrizioni chiuse</h1>
  <p>Al momento non è possibile creare nuovi account. Se hai già un account, <a href="/login.php">accedi</a>.</p>

<?php elseif ($inviata): ?>
  <h1>Controlla la tua email</h1>
  <p>Se l'indirizzo è valido riceverai a breve un messaggio con un link per attivare l'account
    (valido <?= VERIFICA_DURATA_ORE ?> ore). Aprilo e inserisci la password che hai scelto.</p>
  <p class="nota">Non trovi il messaggio? Controlla la cartella spam, oppure <a href="/login.php">accedi</a>
    con email e password: potrai chiedere di reinviarlo. Se hai già un account, accedi direttamente.</p>

<?php elseif ($bloccato): ?>
  <h1>Troppi tentativi</h1>
  <p>Da questa connessione sono state fatte troppe iscrizioni. Riprova tra un'ora.</p>

<?php else: ?>
  <h1>Crea il tuo account</h1>
  <form method="post" action="/iscrizione.php" novalidate>
    <?= csrf_campo() ?>
    <label>Nome
      <input type="text" name="nome" value="<?= e($dati['nome']) ?>" required maxlength="100" autocomplete="name" autofocus>
      <?php if (isset($errori['nome'])): ?><span class="errore-campo"><?= e($errori['nome']) ?></span><?php endif; ?>
    </label>
    <label>Email
      <input type="email" name="email" value="<?= e($dati['email']) ?>" required maxlength="190" autocomplete="email">
      <?php if (isset($errori['email'])): ?><span class="errore-campo"><?= e($errori['email']) ?></span><?php endif; ?>
    </label>
    <label>Password (almeno <?= password_min() ?> caratteri)
      <input type="password" name="password" required minlength="<?= password_min() ?>" maxlength="<?= PASSWORD_MAX_BYTE ?>" autocomplete="new-password">
      <?php if (isset($errori['password'])): ?><span class="errore-campo"><?= e($errori['password']) ?></span><?php endif; ?>
    </label>
    <label>Ripeti la password
      <input type="password" name="conferma" required autocomplete="new-password">
      <?php if (isset($errori['conferma'])): ?><span class="errore-campo"><?= e($errori['conferma']) ?></span><?php endif; ?>
    </label>
    <!-- Campo trappola per i bot: invisibile alle persone, va lasciato vuoto -->
    <div class="trappola" aria-hidden="true">
      <label>Sito web <input type="text" name="sito_web" value="" tabindex="-1" autocomplete="off"></label>
    </div>
    <button type="submit">Iscriviti</button>
  </form>
  <p class="nota">Hai già un account? <a href="/login.php">Accedi</a></p>
<?php endif; ?>
</section>
<?php
pagina_fine();

<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
intestazioni_sicurezza();

$next = next_sicuro($_GET['next'] ?? $_POST['next'] ?? '/elenco.php');
if (utente_corrente()) {
    redirect($next);
}

$errore = '';
$daConfermare = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reinvia'])) {
    // Reinvio del link di conferma: solo dopo un login con password giusta su un account non confermato
    csrf_verifica();
    $id = (int) ($_SESSION['da_confermare_id'] ?? 0);
    unset($_SESSION['da_confermare_id']);
    if ($id && iscrizione_reinvia($id)) {
        flash('Ti abbiamo inviato un nuovo link di conferma (valido ' . VERIFICA_DURATA_ORE
            . ' ore). Controlla anche la cartella spam.');
    } elseif ($id) {
        flash('Hai già richiesto diverse email nell\'ultima ora: attendi un po\' prima di riprovare.', 'errore');
    }
    redirect('/login.php');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    unset($_SESSION['da_confermare_id']);
    $esito = login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
    if ($esito === LOGIN_OK) {
        // Il frammento (#url=...&titolo=...) arriva dal bookmarklet: lo ripassiamo alla pagina di destinazione
        $frammento = (string) ($_POST['frammento'] ?? '');
        $frammento = ($frammento !== '' && $frammento[0] === '#') ? $frammento : '';
        redirect($next . $frammento);
    }
    $daConfermare = $esito === LOGIN_DA_CONFERMARE;
    $errore = $daConfermare
        ? 'Devi ancora confermare l\'iscrizione: apri il link che ti abbiamo inviato per email.'
        : 'Email o password non corretti.';
}

pagina_inizio('Accedi');
?>
<section class="card stretta">
  <h1>Accedi</h1>
  <?php if ($errore): ?><p class="flash flash-errore"><?= e($errore) ?></p><?php endif; ?>
  <?php if ($daConfermare): ?>
    <form method="post" action="/login.php" class="azioni">
      <?= csrf_campo() ?>
      <span>Non trovi l'email?</span>
      <button type="submit" name="reinvia" value="1" class="secondario">Reinvia l'email di conferma</button>
    </form>
  <?php endif; ?>
  <form method="post" action="/login.php" id="form-login">
    <?= csrf_campo() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <input type="hidden" name="frammento" id="frammento" value="">
    <label>Email <input type="email" name="email" required autocomplete="username" autofocus></label>
    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
    <button type="submit">Accedi</button>
  </form>
  <?php if (iscrizioni_aperte()): ?>
    <p class="nota">Non hai un account? <a href="/iscrizione.php">Iscriviti gratis</a></p>
  <?php endif; ?>
</section>
<script src="<?= e(asset('login.js')) ?>"></script>
<?php
pagina_fine();

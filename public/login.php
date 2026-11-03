<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
intestazioni_sicurezza();

$next = next_sicuro($_GET['next'] ?? $_POST['next'] ?? '/');
if (utente_corrente()) {
    redirect($next);
}

$errore = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    if (login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        // Il frammento (#url=...&titolo=...) arriva dal bookmarklet: lo ripassiamo alla pagina di destinazione
        $frammento = (string) ($_POST['frammento'] ?? '');
        $frammento = ($frammento !== '' && $frammento[0] === '#') ? $frammento : '';
        redirect($next . $frammento);
    }
    $errore = 'Email o password non corretti.';
}

pagina_inizio('Accedi');
?>
<section class="card stretta">
  <h1>Accedi</h1>
  <?php if ($errore): ?><p class="flash flash-errore"><?= e($errore) ?></p><?php endif; ?>
  <form method="post" action="/login.php" id="form-login">
    <?= csrf_campo() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <input type="hidden" name="frammento" id="frammento" value="">
    <label>Email <input type="email" name="email" required autocomplete="username" autofocus></label>
    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
    <button type="submit">Accedi</button>
  </form>
</section>
<script src="/assets/login.js"></script>
<?php
pagina_fine();

<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
intestazioni_sicurezza();
$u = richiedi_login();

$nuovoToken = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $azione = $_POST['azione'] ?? '';
    if ($azione === 'token') {
        $nuovoToken = genera_token_api((int) $u['id']);
    } elseif ($azione === 'revoca') {
        revoca_token_api((int) $u['id']);
        flash('Token API revocato.');
        redirect('/account.php');
    } elseif ($azione === 'preferenze') {
        if (imposta_reel_per_pagina((int) $u['id'], (int) ($_POST['reel_per_pagina'] ?? 0))) {
            flash('Preferenze salvate.');
        } else {
            flash('Valore non valido.', 'errore');
        }
        redirect('/account.php');
    } elseif ($azione === 'password') {
        $attuale = (string) ($_POST['attuale'] ?? '');
        $nuova = (string) ($_POST['nuova'] ?? '');
        $st = db()->prepare('SELECT password_hash FROM utenti WHERE id = ?');
        $st->execute([$u['id']]);
        if (!password_verify($attuale, (string) $st->fetchColumn())) {
            flash('Password attuale non corretta.', 'errore');
        } elseif (mb_strlen($nuova) < password_min()) {
            flash('La nuova password deve avere almeno ' . password_min() . ' caratteri.', 'errore');
        } else {
            db()->prepare('UPDATE utenti SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($nuova, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            flash('Password aggiornata.');
        }
        redirect('/account.php');
    }
}

pagina_inizio('Account');
?>
<section class="card">
  <h1>Ciao <?= e($u['nome']) ?></h1>
  <p class="meta"><?= e($u['email']) ?> · ruolo <?= e($u['ruolo']) ?></p>
</section>

<section class="card" id="preferenze">
  <h2>Preferenze</h2>
  <form method="post" class="riga-form">
    <?= csrf_campo() ?>
    <input type="hidden" name="azione" value="preferenze">
    <label for="reel_per_pagina">Reel per pagina nell'elenco</label>
    <select name="reel_per_pagina" id="reel_per_pagina">
      <?php foreach (REEL_PER_PAGINA_SCELTE as $n): ?>
        <option value="<?= $n ?>"<?= $n === reel_per_pagina($u) ? ' selected' : '' ?>><?= $n ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Salva</button>
  </form>
</section>

<section class="card">
  <h2>Pulsante "Salva reel"</h2>
  <p>Trascina questo pulsante nella <strong>barra dei preferiti</strong> del browser:</p>
  <p><a class="bookmarklet" href="<?= e(bookmarklet_href()) ?>">Salva reel</a></p>
  <ol>
    <li>Apri il reel su Facebook (da PC, con il tuo account).</li>
    <li>Se vuoi un testo preciso, <strong>selezionalo</strong> col mouse prima del clic.</li>
    <li>Clicca "Salva reel" nei preferiti: si apre la pagina di conferma, correggi e conferma.</li>
  </ol>
  <p class="nota">Se la barra dei preferiti non è visibile: Ctrl+Maiusc+B (Chrome/Edge).</p>
</section>

<section class="card">
  <h2>Token API</h2>
  <p class="nota">Serve solo per integrazioni (es. la futura lettura da telefono). Non condividerlo.</p>
  <?php if ($nuovoToken): ?>
    <p class="flash flash-ok">Nuovo token (copialo ora, non verrà più mostrato):</p>
    <pre class="token"><?= e($nuovoToken) ?></pre>
  <?php endif; ?>
  <form method="post" class="inline"><?= csrf_campo() ?>
    <button name="azione" value="token">Genera nuovo token</button></form>
  <form method="post" class="inline"><?= csrf_campo() ?>
    <button name="azione" value="revoca" class="secondario">Revoca token</button></form>
</section>

<section class="card">
  <h2>Cambia password</h2>
  <form method="post">
    <?= csrf_campo() ?>
    <input type="hidden" name="azione" value="password">
    <label>Password attuale <input type="password" name="attuale" required autocomplete="current-password"></label>
    <label>Nuova password (min. <?= password_min() ?> caratteri) <input type="password" name="nuova" required minlength="<?= password_min() ?>" autocomplete="new-password"></label>
    <button type="submit">Aggiorna password</button>
  </form>
</section>
<?php
pagina_fine();

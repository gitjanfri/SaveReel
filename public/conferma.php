<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
intestazioni_sicurezza();
$u = richiedi_login();

// Modifica di un reel esistente (?id=) oppure nuovo (precompilato dal bookmarklet via #frammento)
$reel = null;
if (isset($_GET['id'])) {
    $reel = trova_reel((int) $u['id'], (int) $_GET['id']);
    if (!$reel) {
        http_response_code(404);
        exit('Reel non trovato.');
    }
}

$dati = $reel ?? ['url' => '', 'titolo' => '', 'testo' => '', 'autore' => '', 'categoria' => ''];
$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $dati = [
        'url'    => (string) ($_POST['url'] ?? ''),
        'titolo' => (string) ($_POST['titolo'] ?? ''),
        'testo'  => (string) ($_POST['testo'] ?? ''),
        'autore' => (string) ($_POST['autore'] ?? ''),
        'categoria' => (string) ($_POST['categoria'] ?? ''),
    ];
    $fonte = ($_POST['fonte'] ?? '') === 'browser' ? 'browser' : 'manuale';
    try {
        [, $creato] = salva_reel((int) $u['id'], $dati, $reel['fonte'] ?? $fonte);
        flash($creato ? 'Reel salvato.' : 'Reel già presente: aggiornato.');
        redirect('/elenco.php');
    } catch (InvalidArgumentException $ex) {
        $errore = $ex->getMessage();
    }
}

$categorie = elenco_categorie((int) $u['id']);
pagina_inizio($reel ? 'Modifica reel' : 'Salva reel');
?>
<section class="card">
  <h1><?= $reel ? 'Modifica reel' : 'Salva reel' ?></h1>
  <p class="nota">Controlla e correggi i dati, poi conferma.</p>
  <?php if ($errore): ?><p class="flash flash-errore"><?= e($errore) ?></p><?php endif; ?>
  <form method="post" id="form-reel">
    <?= csrf_campo() ?>
    <input type="hidden" name="fonte" id="fonte" value="manuale">
    <label>Link <input type="url" name="url" id="url" required maxlength="2000" value="<?= e($dati['url']) ?>"></label>
    <label>Titolo <input type="text" name="titolo" id="titolo" required maxlength="500" value="<?= e($dati['titolo']) ?>"></label>
    <label>Autore <input type="text" name="autore" id="autore" maxlength="255" value="<?= e($dati['autore']) ?>"></label>
    <label>Categoria <small>(scegline una o scrivine una nuova)</small>
      <input type="text" name="categoria" id="categoria" maxlength="100" list="lista-categorie" autocomplete="off"
             value="<?= e($dati['categoria']) ?>"></label>
    <datalist id="lista-categorie">
      <?php foreach ($categorie as $c): ?><option value="<?= e($c['nome']) ?>"><?php endforeach; ?>
    </datalist>
    <label>Testo <small>(facoltativo)</small>
      <textarea name="testo" id="testo" rows="10"><?= e($dati['testo']) ?></textarea></label>
    <div class="azioni">
      <button type="submit">Conferma e salva</button>
      <a href="/elenco.php" class="bottone secondario">Annulla</a>
    </div>
  </form>
</section>
<script src="<?= e(asset('conferma.js')) ?>"></script>
<?php
pagina_fine();

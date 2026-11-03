<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
intestazioni_sicurezza();
$u = richiedi_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['elimina'])) {
    csrf_verifica();
    if (elimina_reel((int) $u['id'], (int) $_POST['elimina'])) {
        flash('Reel eliminato.');
    }
    redirect('/');
}

$q = trim((string) ($_GET['q'] ?? ''));
$cat = (isset($_GET['cat']) && $_GET['cat'] !== '') ? (int) $_GET['cat'] : null;
$pagina = max(1, (int) ($_GET['p'] ?? 1));
$perPagina = 50;
$reels = cerca_reels((int) $u['id'], $q, $perPagina + 1, ($pagina - 1) * $perPagina, $cat);
$altre = count($reels) > $perPagina;
$reels = array_slice($reels, 0, $perPagina);
$categorie = elenco_categorie((int) $u['id']);

/** Query string che conserva ricerca e categoria. */
function qs(array $extra = []): string
{
    global $q, $cat;
    $param = array_merge(['q' => $q, 'cat' => $cat], $extra);
    return e(http_build_query(array_filter($param, fn($v) => $v !== null && $v !== '')));
}

pagina_inizio('I miei reel');
?>
<section class="barra">
  <form method="get" class="cerca">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cerca in titolo, testo, autore…">
    <select name="cat" aria-label="Categoria">
      <option value="">Tutte le categorie</option>
      <option value="0"<?= $cat === 0 ? ' selected' : '' ?>>Senza categoria</option>
      <?php foreach ($categorie as $c): ?>
        <option value="<?= (int) $c['id'] ?>"<?= $cat === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['nome']) ?> (<?= (int) $c['n'] ?>)</option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Cerca</button>
  </form>
  <div class="export">
    Esporta: <a href="/export.php?<?= qs(['formato' => 'txt']) ?>">.txt</a>
    · <a href="/export.php?<?= qs(['formato' => 'csv']) ?>">.csv</a>
  </div>
</section>

<?php if (!$reels): ?>
  <section class="card">
    <p><?= ($q !== '' || $cat !== null) ? 'Nessun reel trovato.' : 'Nessun reel salvato.' ?></p>
    <p>Installa il pulsante dalla pagina <a href="/account.php">Account</a>, oppure <a href="/conferma.php">aggiungi un reel a mano</a>.</p>
  </section>
<?php endif; ?>

<?php if ($reels): ?>
<ul class="lista-reel card">
<?php foreach ($reels as $r): ?>
  <li class="riga-reel">
    <div class="info">
      <a class="titolo" href="<?= e($r['url']) ?>" target="_blank" rel="noopener noreferrer"
         title="Apri il reel su Facebook"><?= e($r['titolo']) ?></a>
      <div class="dettagli">
        <?php if ($r['categoria']): ?>
          <a class="categoria" href="/?cat=<?= (int) $r['categoria_id'] ?>"><?= e($r['categoria']) ?></a>
        <?php else: ?>
          <span class="categoria vuota">Senza categoria</span>
        <?php endif; ?>
        <span class="autore"><?= $r['autore'] ? e($r['autore']) : '<em>autore sconosciuto</em>' ?></span>
      </div>
    </div>
    <div class="servizio">
      <a href="<?= e($r['url']) ?>" target="_blank" rel="noopener noreferrer" class="bottone piccolo secondario">Apri</a>
      <button type="button" class="piccolo secondario copia-link" data-url="<?= e($r['url']) ?>">Copia link</button>
      <a href="/conferma.php?id=<?= (int) $r['id'] ?>" class="bottone piccolo secondario">Modifica</a>
      <form method="post" class="inline conferma-elimina">
        <?= csrf_campo() ?>
        <button name="elimina" value="<?= (int) $r['id'] ?>" class="piccolo pericolo">Elimina</button>
      </form>
    </div>
  </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<nav class="paginazione">
  <?php if ($pagina > 1): ?><a href="?<?= qs(['p' => $pagina - 1]) ?>">← Precedenti</a><?php endif; ?>
  <?php if ($altre): ?><a href="?<?= qs(['p' => $pagina + 1]) ?>">Successivi →</a><?php endif; ?>
</nav>
<script src="<?= e(asset('elenco.js')) ?>"></script>
<?php
pagina_fine();

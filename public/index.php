<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
intestazioni_sicurezza();

// Chi ha già fatto l'accesso va direttamente all'elenco dei suoi reel
if (utente_corrente()) {
    redirect('/elenco.php');
}

pagina_inizio('Salva i reel di Facebook');
?>
<section class="hero">
  <h1>I reel che ti piacciono, salvati e ritrovabili.</h1>
  <p class="sottotitolo">Salva Reel conserva <strong>titolo, autore, testo e link</strong> dei reel di Facebook
    con un clic, li ordina per categoria e te li fa ritrovare con una ricerca.</p>
  <div class="azioni centro">
    <?php if (iscrizioni_aperte()): ?>
      <a href="/iscrizione.php" class="bottone grande">Crea un account gratis</a>
    <?php endif; ?>
    <a href="/login.php" class="bottone grande secondario">Ho già un account</a>
  </div>
</section>

<section class="vantaggi">
  <article class="card">
    <h2>Un clic dal reel</h2>
    <p>Il pulsante "Salva reel" nei preferiti del browser legge titolo, autore e testo dal reel che stai guardando.</p>
  </article>
  <article class="card">
    <h2>Controlli prima di salvare</h2>
    <p>Si apre una pagina di conferma: correggi il titolo, scegli una categoria, poi salva.</p>
  </article>
  <article class="card">
    <h2>Ritrovi tutto</h2>
    <p>Cerca nel testo, filtra per categoria ed esporta l'elenco in .txt o .csv quando vuoi.</p>
  </article>
</section>

<section class="card">
  <h2>Come funziona</h2>
  <ol class="passi">
    <li><strong>Iscriviti</strong> con la tua email e conferma il link che ricevi.</li>
    <li>Dalla pagina <em>Account</em> trascina il pulsante <strong>"Salva reel"</strong> nella barra dei preferiti.</li>
    <li>Su Facebook apri un reel e clicca il pulsante: conferma e il reel è salvato.</li>
  </ol>
  <p class="nota">I tuoi reel sono visibili solo a te. Il servizio non accede al tuo account Facebook:
    legge solo la pagina che hai aperto, quando clicchi il pulsante.</p>
</section>

<?php if (iscrizioni_aperte()): ?>
<section class="card invito">
  <h2>Non hai ancora un account?</h2>
  <p>L'iscrizione è gratuita e richiede un minuto.</p>
  <a href="/iscrizione.php" class="bottone">Iscriviti</a>
</section>
<?php endif; ?>
<?php
pagina_fine();

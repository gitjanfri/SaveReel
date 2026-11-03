// Chiede conferma prima di eliminare un reel.
document.querySelectorAll('form.conferma-elimina').forEach(function (f) {
  f.addEventListener('submit', function (ev) {
    if (!confirm('Eliminare questo reel?')) ev.preventDefault();
  });
});

// "Copia link": copia l'URL del reel negli appunti.
document.querySelectorAll('button.copia-link').forEach(function (b) {
  b.addEventListener('click', function () {
    var testo = b.textContent;
    navigator.clipboard.writeText(b.dataset.url).then(function () {
      b.textContent = 'Copiato ✓';
    }, function () {
      b.textContent = 'Errore';
    }).then(function () {
      setTimeout(function () { b.textContent = testo; }, 1500);
    });
  });
});

// Chiede conferma prima di eliminare un reel.
document.querySelectorAll('form.conferma-elimina').forEach(function (f) {
  f.addEventListener('submit', function (ev) {
    if (!confirm('Eliminare questo reel?')) ev.preventDefault();
  });
});

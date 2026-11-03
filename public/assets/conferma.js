// Precompila il form con i dati inviati dal bookmarklet nel frammento (#url=...&titolo=...).
// Il frammento non arriva mai al server, quindi non finisce nei log.
(function () {
  if (location.hash.length < 2) return;
  var p = new URLSearchParams(location.hash.slice(1));
  var trovato = false;
  ['url', 'titolo', 'testo', 'autore'].forEach(function (k) {
    var el = document.getElementById(k);
    var v = p.get(k);
    if (el && v && !el.value) { el.value = v; trovato = true; }
  });
  if (trovato) document.getElementById('fonte').value = 'browser';
  history.replaceState(null, '', location.pathname + location.search);
  var t = document.getElementById('titolo');
  if (t) t.focus();
})();

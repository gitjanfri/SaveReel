// Conserva i dati del bookmarklet (#frammento) durante il login.
(function () {
  var f = document.getElementById('frammento');
  if (f && location.hash.length > 1) f.value = location.hash;
})();

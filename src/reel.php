<?php
declare(strict_types=1);

// Logica dei reel: normalizzazione link, salvataggio, ricerca.

const PARAMETRI_TRACCIAMENTO = ['fbclid', 'mibextid', 'rdid', 'share_url', 'sfnsn', 'ref', 'refsrc', '_rdr'];

/**
 * Normalizza un link così che lo stesso reel abbia sempre lo stesso URL.
 * Restituisce null se il link non è un http(s) valido.
 */
function normalizza_url(string $url): ?string
{
    $url = trim($url);
    $p = parse_url($url);
    if ($p === false || empty($p['host']) || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)) {
        return null;
    }
    $host = strtolower($p['host']);
    $path = $p['path'] ?? '/';
    parse_str($p['query'] ?? '', $query);

    if (preg_match('/(^|\.)facebook\.com$/', $host)) {
        // Link "esterni" di Facebook: l.facebook.com/l.php?u=<link vero>
        if ($host === 'l.facebook.com' && !empty($query['u']) && is_string($query['u'])) {
            return normalizza_url($query['u']);
        }
        if (preg_match('~^/reels?/(\d+)~', $path, $m)) {
            return 'https://www.facebook.com/reel/' . $m[1];
        }
        if (str_starts_with($path, '/watch') && !empty($query['v']) && ctype_digit((string) $query['v'])) {
            return 'https://www.facebook.com/watch/?v=' . $query['v'];
        }
        $host = 'www.facebook.com';
    }

    // Generico: via frammento e parametri di tracciamento
    $query = array_filter(
        $query,
        fn($k) => !str_starts_with((string) $k, 'utm_') && !in_array($k, PARAMETRI_TRACCIAMENTO, true),
        ARRAY_FILTER_USE_KEY
    );
    $out = 'https://' . $host . (isset($p['port']) ? ':' . $p['port'] : '') . $path;
    if ($query) {
        $out .= '?' . http_build_query($query);
    }
    return mb_substr($out, 0, 500);
}

/**
 * Salva o aggiorna (stesso utente + stesso URL) un reel.
 * Restituisce [id, creato(bool)] oppure lancia InvalidArgumentException con un messaggio per l'utente.
 */
function salva_reel(int $utenteId, array $dati, string $fonte): array
{
    $url = normalizza_url((string) ($dati['url'] ?? ''));
    if ($url === null) {
        throw new InvalidArgumentException('Il link non è valido.');
    }
    $titolo = pulisci_testo((string) ($dati['titolo'] ?? ''), 500);
    if ($titolo === '') {
        throw new InvalidArgumentException('Il titolo è obbligatorio.');
    }
    $testo  = pulisci_testo((string) ($dati['testo'] ?? ''), 60000);
    $autore = pulisci_testo((string) ($dati['autore'] ?? ''), 255);
    $categoriaId = trova_o_crea_categoria($utenteId, (string) ($dati['categoria'] ?? ''));
    if (!in_array($fonte, ['browser', 'server', 'manuale'], true)) {
        $fonte = 'manuale';
    }

    $st = db()->prepare('SELECT id FROM reels WHERE utente_id = ? AND url = ?');
    $st->execute([$utenteId, $url]);
    $esistente = $st->fetchColumn();

    if ($esistente) {
        db()->prepare('UPDATE reels SET titolo = ?, testo = ?, autore = ?, categoria_id = ?, fonte = ? WHERE id = ?')
            ->execute([$titolo, $testo ?: null, $autore ?: null, $categoriaId, $fonte, $esistente]);
        pulisci_categorie_vuote($utenteId); // la vecchia categoria potrebbe essere rimasta vuota
        return [(int) $esistente, false];
    }
    db()->prepare('INSERT INTO reels (utente_id, categoria_id, url, titolo, testo, autore, fonte) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$utenteId, $categoriaId, $url, $titolo, $testo ?: null, $autore ?: null, $fonte]);
    return [(int) db()->lastInsertId(), true];
}

// ---- Categorie (personali per utente) ----

/** Restituisce l'id della categoria con quel nome (creandola se manca), o null se il nome è vuoto. */
function trova_o_crea_categoria(int $utenteId, string $nome): ?int
{
    $nome = preg_replace('/\s+/u', ' ', pulisci_testo($nome, 100)) ?? '';
    if ($nome === '') {
        return null;
    }
    // INSERT IGNORE + SELECT: niente duplicati anche con salvataggi contemporanei
    db()->prepare('INSERT IGNORE INTO categorie (utente_id, nome) VALUES (?, ?)')->execute([$utenteId, $nome]);
    $st = db()->prepare('SELECT id FROM categorie WHERE utente_id = ? AND nome = ?');
    $st->execute([$utenteId, $nome]);
    return (int) $st->fetchColumn();
}

/** Categorie dell'utente con il numero di reel, in ordine alfabetico. */
function elenco_categorie(int $utenteId): array
{
    $st = db()->prepare('SELECT c.id, c.nome, COUNT(r.id) AS n FROM categorie c
        LEFT JOIN reels r ON r.categoria_id = c.id WHERE c.utente_id = ? GROUP BY c.id, c.nome ORDER BY c.nome');
    $st->execute([$utenteId]);
    return $st->fetchAll();
}

/** Elimina le categorie rimaste senza reel. */
function pulisci_categorie_vuote(int $utenteId): void
{
    db()->prepare('DELETE c FROM categorie c LEFT JOIN reels r ON r.categoria_id = c.id
        WHERE c.utente_id = ? AND r.id IS NULL')->execute([$utenteId]);
}

function pulisci_testo(string $s, int $max): string
{
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = preg_replace('/[^\P{C}\n\t]/u', '', $s) ?? '';   // via caratteri di controllo
    return mb_substr(trim($s), 0, $max);
}

/** Reel dell'utente, filtrati da una ricerca libera, più recenti prima. */
/** $categoriaId: null = tutte, 0 = senza categoria, >0 = quella categoria. */
function cerca_reels(int $utenteId, string $q = '', int $limite = 50, int $offset = 0, ?int $categoriaId = null): array
{
    [$where, $param] = filtro_reels($utenteId, $q, $categoriaId);
    $sql = 'SELECT r.id, r.url, r.titolo, r.testo, r.autore, r.fonte, r.categoria_id, c.nome AS categoria,
            r.creato_il, r.aggiornato_il
            FROM reels r LEFT JOIN categorie c ON c.id = r.categoria_id WHERE ' . $where
        . ' ORDER BY r.creato_il DESC, r.id DESC LIMIT ' . max(1, $limite) . ' OFFSET ' . max(0, $offset);
    $st = db()->prepare($sql);
    $st->execute($param);
    return $st->fetchAll();
}

/** Numero di reel che corrispondono agli stessi filtri di cerca_reels (per la paginazione). */
function conta_reels(int $utenteId, string $q = '', ?int $categoriaId = null): int
{
    [$where, $param] = filtro_reels($utenteId, $q, $categoriaId);
    $st = db()->prepare('SELECT COUNT(*) FROM reels r LEFT JOIN categorie c ON c.id = r.categoria_id WHERE ' . $where);
    $st->execute($param);
    return (int) $st->fetchColumn();
}

/** Condizione WHERE e parametri comuni a ricerca e conteggio. */
function filtro_reels(int $utenteId, string $q, ?int $categoriaId): array
{
    $where = 'r.utente_id = ?';
    $param = [$utenteId];
    $q = trim($q);
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where .= ' AND (r.titolo LIKE ? OR r.testo LIKE ? OR r.autore LIKE ? OR r.url LIKE ? OR c.nome LIKE ?)';
        array_push($param, $like, $like, $like, $like, $like);
    }
    if ($categoriaId === 0) {
        $where .= ' AND r.categoria_id IS NULL';
    } elseif ($categoriaId !== null) {
        $where .= ' AND r.categoria_id = ?';
        $param[] = $categoriaId;
    }
    return [$where, $param];
}

// ---- Preferenze utente ----

const REEL_PER_PAGINA_SCELTE = [5, 10, 20, 50, 100];
const REEL_PER_PAGINA_DEFAULT = 10;

function reel_per_pagina(array $utente): int
{
    $n = (int) ($utente['reel_per_pagina'] ?? REEL_PER_PAGINA_DEFAULT);
    return in_array($n, REEL_PER_PAGINA_SCELTE, true) ? $n : REEL_PER_PAGINA_DEFAULT;
}

function imposta_reel_per_pagina(int $utenteId, int $n): bool
{
    if (!in_array($n, REEL_PER_PAGINA_SCELTE, true)) {
        return false;
    }
    db()->prepare('UPDATE utenti SET reel_per_pagina = ? WHERE id = ?')->execute([$n, $utenteId]);
    return true;
}

/**
 * Numeri di pagina da mostrare: prima, ultima e quelle vicine all'attuale; null = "…".
 * Es. pagina 7 di 20 → [1, null, 5, 6, 7, 8, 9, null, 20]
 */
function pagine_da_mostrare(int $attuale, int $totale, int $vicine = 2): array
{
    $out = [];
    $prec = 0;
    for ($i = 1; $i <= $totale; $i++) {
        if ($i === 1 || $i === $totale || abs($i - $attuale) <= $vicine) {
            if ($prec && $i - $prec > 1) {
                $out[] = null;
            }
            $out[] = $i;
            $prec = $i;
        }
    }
    return $out;
}

function trova_reel(int $utenteId, int $id): ?array
{
    $st = db()->prepare('SELECT r.*, c.nome AS categoria FROM reels r
        LEFT JOIN categorie c ON c.id = r.categoria_id WHERE r.id = ? AND r.utente_id = ?');
    $st->execute([$id, $utenteId]);
    return $st->fetch() ?: null;
}

function elimina_reel(int $utenteId, int $id): bool
{
    $st = db()->prepare('DELETE FROM reels WHERE id = ? AND utente_id = ?');
    $st->execute([$id, $utenteId]);
    $ok = $st->rowCount() > 0;
    pulisci_categorie_vuote($utenteId);
    return $ok;
}

/** Codice del bookmarklet: legge titolo/testo dalla pagina del reel e apre la conferma. */
function bookmarklet_href(): string
{
    $base = rtrim((string) config('base_url'), '/');
    // Facebook da loggato non espone meta og: ; didascalia completa e autore stanno nei dati JSON
    // incorporati nella pagina (<script type="application/json">). Si cerca il nodo più profondo che
    // contiene l'ID del reel e un "message.text". Il testo selezionato col mouse ha la precedenza.
    // ATTENZIONE: niente commenti // dentro il JS (le righe vengono unite).
    $js = <<<'JS'
(()=>{const id=(location.pathname.match(/\/reels?\/(\d+)/)||[])[1]||'';
let testo='',autore='',best=null;
const walk=(o,d)=>{if(!o||typeof o!=='object')return false;let h=false;
for(const k in o){const v=o[k];if(v&&typeof v==='object'){if(walk(v,d+1))h=true}
else if(String(v)===id&&/^(id|video_id|post_id|top_level_post_id)$/.test(k))h=true}
if(h&&o.message&&typeof o.message.text==='string'&&(!best||d>best.d))best={d:d,o:o};return h};
if(id){document.querySelectorAll('script[type="application/json"]').forEach(s=>{
if(s.textContent.indexOf(id)>=0){try{walk(JSON.parse(s.textContent),0)}catch(e){}}});
if(best){testo=best.o.message.text;const a=best.o.actors||[];autore=(a[0]&&a[0].name)||''}}
const m=p=>{const e=document.querySelector('meta[property="'+p+'"]');return e?(e.content||'').trim():''};
const sel=String(window.getSelection()).trim();
if(sel)testo=sel;if(!testo)testo=m('og:description');
let titolo=m('og:title');
if(!titolo)titolo=(testo.split('\n').find(l=>l.trim())||'').trim();
if(!titolo){titolo=document.title.replace(/^\(\d+\)\s*/,'').replace(/\s*\|\s*Facebook\s*$/i,'').trim();
if(/^facebook$/i.test(titolo))titolo=autore?'Reel di '+autore:''}
if(titolo.length>120)titolo=titolo.slice(0,117)+'...';
const p=new URLSearchParams({url:location.href,titolo:titolo,testo:testo,autore:autore});
const w=window.open('__BASE__/conferma.php?n='+Date.now()+'#'+p.toString(),'salvareel');if(w)w.focus();})();
JS;
    // La scheda si chiama "salvareel": i clic successivi riusano quella già aperta (anche se ora mostra
    // l'elenco) invece di aprirne una nuova. ?n= rende ogni URL diverso, così la pagina si ricarica
    // davvero (cambiando solo il #frammento il browser non la ricaricherebbe).
    $js = str_replace(["\n", '__BASE__'], ['', $base], $js);
    return 'javascript:' . rawurlencode($js);
}

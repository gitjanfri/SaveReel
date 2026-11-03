<?php
declare(strict_types=1);
// API JSON con token personale (Authorization: Bearer <token>).
//   GET  /api/reels.php?q=...&categoria=...   elenco reel dell'utente
//   POST /api/reels.php            {"url","titolo","testo","autore","categoria","fonte"} salva o aggiorna
require __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function risposta(int $codice, array $corpo): never
{
    http_response_code($codice);
    echo json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$u = utente_da_token();
if (!$u) {
    risposta(401, ['errore' => 'Token mancante o non valido']);
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $catId = null;
        if (isset($_GET['categoria']) && is_string($_GET['categoria'])) {
            $catId = 0; // categoria inesistente o vuota → reel senza categoria
            foreach (elenco_categorie((int) $u['id']) as $c) {
                if (mb_strtolower($c['nome']) === mb_strtolower(trim($_GET['categoria']))) {
                    $catId = (int) $c['id'];
                }
            }
        }
        $reels = cerca_reels((int) $u['id'], (string) ($_GET['q'] ?? ''), min(500, max(1, (int) ($_GET['limite'] ?? 100))), 0, $catId);
        risposta(200, ['reels' => $reels]);

    case 'POST':
        $dati = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($dati)) {
            risposta(400, ['errore' => 'Corpo JSON non valido']);
        }
        try {
            [$id, $creato] = salva_reel((int) $u['id'], $dati, (string) ($dati['fonte'] ?? 'server'));
            risposta($creato ? 201 : 200, ['id' => $id, 'creato' => $creato]);
        } catch (InvalidArgumentException $ex) {
            risposta(422, ['errore' => $ex->getMessage()]);
        }

    default:
        header('Allow: GET, POST');
        risposta(405, ['errore' => 'Metodo non consentito']);
}

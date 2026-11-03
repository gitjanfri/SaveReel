<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
$u = richiedi_login();

$q = (string) ($_GET['q'] ?? '');
$formato = ($_GET['formato'] ?? 'txt') === 'csv' ? 'csv' : 'txt';
$cat = (isset($_GET['cat']) && $_GET['cat'] !== '') ? (int) $_GET['cat'] : null;
$reels = cerca_reels((int) $u['id'], $q, 100000, 0, $cat);
$nome = 'reel-' . date('Y-m-d') . '.' . $formato;

header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . $nome . '"');

if ($formato === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel legge correttamente gli accenti
    fputcsv($out, ['titolo', 'categoria', 'autore', 'link', 'testo', 'salvato_il'], ';');
    foreach ($reels as $r) {
        fputcsv($out, [$r['titolo'], $r['categoria'], $r['autore'], $r['url'], $r['testo'], $r['creato_il']], ';');
    }
    fclose($out);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
foreach ($reels as $r) {
    echo $r['titolo'], "\n";
    echo $r['url'], "\n";
    if ($r['categoria']) {
        echo 'Categoria: ', $r['categoria'], "\n";
    }
    if ($r['autore']) {
        echo 'Autore: ', $r['autore'], "\n";
    }
    echo 'Salvato il: ', date('d/m/Y H:i', strtotime($r['creato_il'])), "\n";
    if ($r['testo']) {
        echo "\n", $r['testo'], "\n";
    }
    echo "\n", str_repeat('-', 60), "\n\n";
}

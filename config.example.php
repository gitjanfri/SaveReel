<?php
// Copia questo file in config.php (escluso da git) e adatta i valori.
// I valori qui sotto funzionano con l'ambiente Docker locale.
// Sull'hosting: config.php sta FUORI dalla cartella public/.
return [
    'base_url' => 'http://localhost:8080',          // produzione: https://reel.facchinigianfranco.it
    'debug'    => true,                             // produzione: false

    'db' => [
        'host'     => 'db',                         // produzione: localhost
        'port'     => 3306,
        'name'     => 'salvareel',                  // produzione: nome DB creato in cPanel
        'user'     => 'salvareel',
        'password' => 'salvareel',
    ],
];

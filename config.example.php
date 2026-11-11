<?php
// Copia questo file in config.php (escluso da git) e adatta i valori.
// I valori qui sotto funzionano con l'ambiente Docker locale.
// Sull'hosting: config.php sta FUORI dalla cartella public/.
return [
    'base_url' => 'http://localhost:8080',          // produzione: https://reel.facchinigianfranco.it
    'debug'    => true,                             // produzione: false
    'password_min' => 10,                           // lunghezza minima password (mai sotto 8)
    'iscrizioni_aperte' => true,                    // false: nessuna nuova iscrizione dal sito

    // Email (conferma iscrizione). 'log' le scrive in var/mail.log invece di inviarle (sviluppo);
    // 'mail' usa la funzione mail() di PHP (hosting cPanel). Il mittente deve essere del tuo dominio.
    'mail' => [
        'modo'     => 'log',                        // produzione: 'mail'
        'mittente' => 'noreply@localhost',          // produzione: noreply@facchinigianfranco.it
    ],

    'db' => [
        'host'     => 'db',                         // produzione: localhost
        'port'     => 3306,
        'name'     => 'salvareel',                  // produzione: nome DB creato in cPanel
        'user'     => 'salvareel',
        'password' => 'salvareel',
    ],
];

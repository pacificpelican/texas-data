<?php
return [
    'mysql' => [
        'enabled' => true,
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'tex',
        'username' => 'root',
        'password' => '',
    ],
    'google' => [
        'enabled' => false,
        'client_id' => '',
        'client_secret' => '',
        'redirect_uri' => 'http://127.0.0.1:8000/google-callback.php',
        'allowed_domains' => ['gmail.com', 'googlemail.com'],
    ],
];

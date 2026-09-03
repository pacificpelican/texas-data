<?php
return [
    'mysql' => [
        'enabled' => false,
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'texas_region_drive',
        'username' => 'texas_user',
        'password' => 'change_me',
    ],
    'google' => [
        'enabled' => false,
        'client_id' => '',
        'client_secret' => '',
        'redirect_uri' => 'http://127.0.0.1:8000/google-callback.php',
        'allowed_domains' => ['gmail.com', 'googlemail.com'],
    ],
];

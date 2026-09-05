<?php
return [
    'mysql' => [
        'enabled' => true,
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'tex',
        'username' => 'tex',
        'password' => 'tex',
    ],
    'google' => [
        'enabled' => false,
        'client_id' => '',
        'client_secret' => '',
        'redirect_uri' => 'http://127.0.0.1:8000/google-callback.php',
        'allowed_domains' => ['gmail.com', 'googlemail.com'],
    ],
    'llm' => [
        'enabled' => true,
        'provider' => 'ollama',
        'api_url' => 'http://localhost:11434/api/generate',
        'model' => 'llama3.1:8b',
        'temperature' => 0.7,
    ],
];

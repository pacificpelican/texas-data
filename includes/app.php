<?php
session_start();

const APP_ROOT = __DIR__ . '/..';

function get_app_config()
{
    $configFile = app_path('config.php');
    if (!file_exists($configFile)) {
        return [
            'mysql' => [
                'enabled' => false,
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => '',
                'username' => '',
                'password' => '',
            ],
        ];
    }

    $config = require $configFile;
    if (!is_array($config)) {
        return [
            'mysql' => [
                'enabled' => false,
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => '',
                'username' => '',
                'password' => '',
            ],
        ];
    }

    return $config;
}

function get_mysql_config()
{
    $config = get_app_config();
    $mysql = $config['mysql'] ?? [];

    return [
        'enabled' => !empty($mysql['enabled']),
        'host' => (string) ($mysql['host'] ?? '127.0.0.1'),
        'port' => (int) ($mysql['port'] ?? 3306),
        'database' => (string) ($mysql['database'] ?? ''),
        'username' => (string) ($mysql['username'] ?? ''),
        'password' => (string) ($mysql['password'] ?? ''),
    ];
}

function get_google_config()
{
    $config = get_app_config();
    $google = $config['google'] ?? [];

    $allowedDomains = $google['allowed_domains'] ?? [];
    if (!is_array($allowedDomains)) {
        $allowedDomains = [];
    }

    return [
        'enabled' => !empty($google['enabled']),
        'client_id' => (string) ($google['client_id'] ?? ''),
        'client_secret' => (string) ($google['client_secret'] ?? ''),
        'redirect_uri' => (string) ($google['redirect_uri'] ?? ''),
        'allowed_domains' => array_values(array_map('strtolower', array_filter(array_map('trim', $allowedDomains), static fn($domain) => $domain !== ''))),
    ];
}

function google_oauth_enabled()
{
    $config = get_google_config();
    return $config['enabled']
        && $config['client_id'] !== ''
        && $config['client_secret'] !== ''
        && $config['redirect_uri'] !== '';
}

function google_email_allowed($email, $allowedDomains = [])
{
    $email = strtolower(trim((string) $email));
    if ($email === '' || !str_contains($email, '@')) {
        return false;
    }

    $domain = strtolower(substr($email, strpos($email, '@') + 1));
    if ($allowedDomains === []) {
        return true;
    }

    return in_array($domain, array_map('strtolower', array_map('trim', $allowedDomains)), true);
}

function build_google_auth_url($redirectUri = null)
{
    if (!google_oauth_enabled()) {
        return '';
    }

    $config = get_google_config();
    $redirectUri = $redirectUri ?: $config['redirect_uri'];
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;

    $params = [
        'client_id' => $config['client_id'],
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'access_type' => 'online',
        'prompt' => 'select_account',
        'state' => $state,
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

function exchange_google_code_for_tokens($code, $redirectUri = null)
{
    if (!google_oauth_enabled()) {
        return null;
    }

    $config = get_google_config();
    $redirectUri = $redirectUri ?: $config['redirect_uri'];
    $payload = http_build_query([
        'code' => $code,
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
            'content' => $payload,
            'ignore_errors' => true,
            'timeout' => 15,
        ],
    ]);

    $response = @file_get_contents('https://oauth2.googleapis.com/token', false, $context);
    if ($response === false) {
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded) || empty($decoded['access_token'])) {
        return null;
    }

    return $decoded;
}

function fetch_google_userinfo($accessToken)
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer " . $accessToken . "\r\nAccept: application/json\r\n",
            'ignore_errors' => true,
            'timeout' => 15,
        ],
    ]);

    $response = @file_get_contents('https://openidconnect.googleapis.com/v1/userinfo', false, $context);
    if ($response === false) {
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded) || empty($decoded['email'])) {
        return null;
    }

    return $decoded;
}

function mysql_extension_available()
{
    return extension_loaded('mysqli') && function_exists('mysqli_init');
}

function set_last_mysql_connection_error($message)
{
    $GLOBALS['_last_mysql_connection_error'] = trim((string) $message);
}

function get_last_mysql_connection_error()
{
    return (string) ($GLOBALS['_last_mysql_connection_error'] ?? '');
}

function log_mysql_connection_failure($config, $message)
{
    static $logged = false;
    if ($logged) {
        return;
    }

    $logged = true;
    $safeMessage = trim((string) $message);
    if ($safeMessage === '') {
        $safeMessage = 'Unknown MySQL connection error';
    }

    error_log(sprintf(
        '[texas-data] MySQL unavailable: %s (host=%s port=%d db=%s user=%s)',
        $safeMessage,
        (string) ($config['host'] ?? ''),
        (int) ($config['port'] ?? 0),
        (string) ($config['database'] ?? ''),
        (string) ($config['username'] ?? '')
    ));
}

function fail_mysql_connection($connection, $config, $message)
{
    set_last_mysql_connection_error($message);
    log_mysql_connection_failure($config, $message);
    if ($connection instanceof mysqli) {
        @mysqli_close($connection);
    }

    return null;
}

function get_mysql_connection()
{
    $config = get_mysql_config();
    set_last_mysql_connection_error('');

    if (!mysql_extension_available()) {
        set_last_mysql_connection_error('mysqli extension missing');
        return null;
    }

    if (!$config['enabled'] || $config['host'] === '' || $config['username'] === '' || $config['database'] === '') {
        set_last_mysql_connection_error('MySQL configuration is incomplete');
        return null;
    }

    $connection = @mysqli_init();
    if ($connection === false) {
        return fail_mysql_connection(null, $config, 'mysqli_init failed');
    }

    $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);

    try {
        $connected = @mysqli_real_connect($connection, $config['host'], $config['username'], $config['password'], $config['database'], $config['port']);
        if ($connected) {
            ensure_mysql_schema($connection);
            return $connection;
        }

        $fallback = @mysqli_real_connect($connection, $config['host'], $config['username'], $config['password'], null, $config['port']);
        if (!$fallback) {
            return fail_mysql_connection($connection, $config, mysqli_connect_error() ?: 'MySQL connect failed');
        }

        $databaseName = mysqli_real_escape_string($connection, $config['database']);
        $ddl = "CREATE DATABASE IF NOT EXISTS `$databaseName`;";
        $createResult = mysqli_query($connection, $ddl);
        if ($createResult === false) {
            return fail_mysql_connection($connection, $config, 'CREATE DATABASE failed: ' . (mysqli_error($connection) ?: 'unknown error'));
        }

        if (!mysqli_select_db($connection, $config['database'])) {
            return fail_mysql_connection($connection, $config, 'Database select failed: ' . (mysqli_error($connection) ?: 'unknown error'));
        }

        ensure_mysql_schema($connection);

        return $connection;
    } catch (mysqli_sql_exception $exception) {
        return fail_mysql_connection($connection, $config, $exception->getMessage());
    }
}

function mysql_column_exists($connection, $tableName, $columnName)
{
    if (!($connection instanceof mysqli)) {
        return false;
    }

    $safeTable = mysqli_real_escape_string($connection, (string) $tableName);
    $safeColumn = mysqli_real_escape_string($connection, (string) $columnName);
    $result = mysqli_query($connection, "SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn';");
    if ($result === false) {
        return false;
    }

    $hasColumn = mysqli_num_rows($result) > 0;
    mysqli_free_result($result);
    return $hasColumn;
}

function ensure_mysql_schema($connection)
{
    if (!($connection instanceof mysqli)) {
        return;
    }

    $userSql = "
        CREATE TABLE IF NOT EXISTS users (
            id VARCHAR(255) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) DEFAULT NULL,
            provider VARCHAR(50) NOT NULL DEFAULT 'email',
            created_at DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $regionSql = "
        CREATE TABLE IF NOT EXISTS region_files (
            id VARCHAR(255) PRIMARY KEY,
            region VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            size BIGINT NOT NULL DEFAULT 0,
            uploaded_by VARCHAR(255) NOT NULL,
            uploaded_by_id VARCHAR(255) DEFAULT NULL,
            uploaded_by_email VARCHAR(255) DEFAULT NULL,
            uploaded_at DATETIME NOT NULL,
            path VARCHAR(500) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $assistantHistorySql = "
        CREATE TABLE IF NOT EXISTS assistant_history (
            id VARCHAR(255) PRIMARY KEY,
            user_id VARCHAR(255) NOT NULL,
            user_name VARCHAR(255) NOT NULL,
            user_email VARCHAR(255) NOT NULL,
            region VARCHAR(50) NOT NULL DEFAULT '',
            task VARCHAR(50) NOT NULL,
            model VARCHAR(255) NOT NULL DEFAULT '',
            question MEDIUMTEXT NOT NULL,
            prompt MEDIUMTEXT NOT NULL,
            response MEDIUMTEXT NOT NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $llmHistorySql = "
        CREATE TABLE IF NOT EXISTS llm_history (
            id VARCHAR(255) PRIMARY KEY,
            user_id VARCHAR(255) NOT NULL,
            user_name VARCHAR(255) NOT NULL,
            user_email VARCHAR(255) NOT NULL,
            model VARCHAR(255) NOT NULL DEFAULT '',
            prompt MEDIUMTEXT NOT NULL,
            response MEDIUMTEXT NOT NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    mysqli_query($connection, $userSql);
    mysqli_query($connection, $regionSql);
    mysqli_query($connection, $assistantHistorySql);
    mysqli_query($connection, $llmHistorySql);

    if (!mysql_column_exists($connection, 'users', 'created_at')) {
        mysqli_query($connection, 'ALTER TABLE users ADD COLUMN created_at DATETIME DEFAULT NULL;');
    }

    if (!mysql_column_exists($connection, 'region_files', 'uploaded_by_id')) {
        mysqli_query($connection, 'ALTER TABLE region_files ADD COLUMN uploaded_by_id VARCHAR(255) DEFAULT NULL;');
    }

    if (!mysql_column_exists($connection, 'region_files', 'uploaded_by_email')) {
        mysqli_query($connection, 'ALTER TABLE region_files ADD COLUMN uploaded_by_email VARCHAR(255) DEFAULT NULL;');
    }

    if (!mysql_column_exists($connection, 'assistant_history', 'model')) {
        mysqli_query($connection, "ALTER TABLE assistant_history ADD COLUMN model VARCHAR(255) NOT NULL DEFAULT '';");
    }
}

function app_path($path = '')
{
    $base = APP_ROOT;
    if ($path === '') {
        return $base;
    }

    return $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
}

function redirect($path)
{
    header('Location: ' . $path);
    exit;
}

function read_json_file($filePath, $default = [])
{
    if (!file_exists($filePath)) {
        return $default;
    }

    $raw = file_get_contents($filePath);
    if ($raw === false || trim($raw) === '') {
        return $default;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $default;
}

function write_json_file($filePath, $data)
{
    $directory = dirname($filePath);
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            error_log('Unable to create storage directory: ' . $directory);
            return false;
        }
    }

    if (!is_writable($directory)) {
        error_log('Storage directory is not writable: ' . $directory);
        return false;
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $written = file_put_contents($filePath, $json . PHP_EOL);
    if ($written === false) {
        error_log('Unable to write JSON file: ' . $filePath);
    }

    return $written !== false;
}

function assistant_history_file_path()
{
    return app_path('data/assistant-history.json');
}

function create_assistant_history($user, $region, $task, $question, $prompt, $response, $model = null)
{
    $model = $model ?? (string) (get_llm_config()['model'] ?? 'Unknown model');
    $entry = [
        'id' => 'assistant-' . uniqid('', true),
        'user_id' => (string) ($user['id'] ?? ''),
        'user_name' => (string) ($user['name'] ?? 'Unknown'),
        'user_email' => strtolower((string) ($user['email'] ?? '')),
        'region' => $region === '' ? '' : sanitize_region_key($region),
        'task' => (string) $task,
        'model' => (string) $model,
        'question' => trim((string) $question),
        'prompt' => (string) $prompt,
        'response' => (string) $response,
        'created_at' => gmdate('c'),
    ];

    $connection = get_mysql_connection();
    if ($connection) {
        $values = [];
        foreach ($entry as $value) {
            $values[] = "'" . mysqli_real_escape_string($connection, (string) $value) . "'";
        }
        $sql = 'INSERT INTO assistant_history (id, user_id, user_name, user_email, region, task, model, question, prompt, response, created_at) VALUES (' . implode(', ', $values) . ')';
        $saved = mysqli_query($connection, $sql) !== false;
        mysqli_close($connection);
        return $saved ? $entry : null;
    }

    $history = read_json_file(assistant_history_file_path(), []);
    $history[] = $entry;
    return write_json_file(assistant_history_file_path(), $history) ? $entry : null;
}

function assistant_history_for_user($user)
{
    $userId = (string) ($user['id'] ?? '');
    if ($userId === '') {
        return [];
    }

    $connection = get_mysql_connection();
    if ($connection) {
        $safeUserId = mysqli_real_escape_string($connection, $userId);
        $query = mysqli_query($connection, "SELECT id, user_id, user_name, user_email, region, task, model, question, prompt, response, created_at FROM assistant_history WHERE user_id = '$safeUserId' ORDER BY created_at DESC");
        $history = $query ? mysqli_fetch_all($query, MYSQLI_ASSOC) : [];
        if ($query) {
            mysqli_free_result($query);
        }
        mysqli_close($connection);
        return $history;
    }

    $history = read_json_file(assistant_history_file_path(), []);
    $history = array_values(array_filter($history, static fn($entry) => (string) ($entry['user_id'] ?? '') === $userId));
    usort($history, static fn($a, $b) => strtotime((string) ($b['created_at'] ?? '')) <=> strtotime((string) ($a['created_at'] ?? '')));
    return $history;
}

function assistant_history_entry_for_user($id, $user)
{
    $id = trim((string) $id);
    foreach (assistant_history_for_user($user) as $entry) {
        if (hash_equals((string) ($entry['id'] ?? ''), $id)) {
            return $entry;
        }
    }

    return null;
}

function delete_assistant_history_for_user($user)
{
    $userId = (string) ($user['id'] ?? '');
    if ($userId === '') {
        return true;
    }

    $connection = get_mysql_connection();
    if ($connection) {
        $safeUserId = mysqli_real_escape_string($connection, $userId);
        $deleted = mysqli_query($connection, "DELETE FROM assistant_history WHERE user_id = '$safeUserId'") !== false;
        mysqli_close($connection);
        return $deleted;
    }

    $history = read_json_file(assistant_history_file_path(), []);
    $remaining = array_values(array_filter($history, static fn($entry) => (string) ($entry['user_id'] ?? '') !== $userId));
    return write_json_file(assistant_history_file_path(), $remaining);
}

function llm_history_file_path()
{
    return app_path('data/llm-history.json');
}

function create_llm_history($user, $prompt, $response, $model = null)
{
    $model = $model ?? (string) (get_llm_config()['model'] ?? 'Unknown model');
    $entry = [
        'id' => 'llm-' . uniqid('', true),
        'user_id' => (string) ($user['id'] ?? ''),
        'user_name' => (string) ($user['name'] ?? 'Unknown'),
        'user_email' => strtolower((string) ($user['email'] ?? '')),
        'model' => (string) $model,
        'prompt' => (string) $prompt,
        'response' => (string) $response,
        'created_at' => gmdate('c'),
    ];

    $connection = get_mysql_connection();
    if ($connection) {
        $values = [];
        foreach ($entry as $value) {
            $values[] = "'" . mysqli_real_escape_string($connection, (string) $value) . "'";
        }
        $sql = 'INSERT INTO llm_history (id, user_id, user_name, user_email, model, prompt, response, created_at) VALUES (' . implode(', ', $values) . ')';
        $saved = mysqli_query($connection, $sql) !== false;
        mysqli_close($connection);
        return $saved ? $entry : null;
    }

    $history = read_json_file(llm_history_file_path(), []);
    $history[] = $entry;
    return write_json_file(llm_history_file_path(), $history) ? $entry : null;
}

function llm_history_for_user($user)
{
    $userId = (string) ($user['id'] ?? '');
    if ($userId === '') {
        return [];
    }

    $connection = get_mysql_connection();
    if ($connection) {
        $safeUserId = mysqli_real_escape_string($connection, $userId);
        $query = mysqli_query($connection, "SELECT id, user_id, user_name, user_email, model, prompt, response, created_at FROM llm_history WHERE user_id = '$safeUserId' ORDER BY created_at DESC");
        $history = $query ? mysqli_fetch_all($query, MYSQLI_ASSOC) : [];
        if ($query) {
            mysqli_free_result($query);
        }
        mysqli_close($connection);
        return $history;
    }

    $history = read_json_file(llm_history_file_path(), []);
    $history = array_values(array_filter($history, static fn($entry) => (string) ($entry['user_id'] ?? '') === $userId));
    usort($history, static fn($a, $b) => strtotime((string) ($b['created_at'] ?? '')) <=> strtotime((string) ($a['created_at'] ?? '')));
    return $history;
}

function llm_history_entry_for_user($id, $user)
{
    $id = trim((string) $id);
    foreach (llm_history_for_user($user) as $entry) {
        if (hash_equals((string) ($entry['id'] ?? ''), $id)) {
            return $entry;
        }
    }

    return null;
}

function delete_llm_history_for_user($user)
{
    $userId = (string) ($user['id'] ?? '');
    if ($userId === '') {
        return true;
    }

    $connection = get_mysql_connection();
    if ($connection) {
        $safeUserId = mysqli_real_escape_string($connection, $userId);
        $deleted = mysqli_query($connection, "DELETE FROM llm_history WHERE user_id = '$safeUserId'") !== false;
        mysqli_close($connection);
        return $deleted;
    }

    $history = read_json_file(llm_history_file_path(), []);
    $remaining = array_values(array_filter($history, static fn($entry) => (string) ($entry['user_id'] ?? '') !== $userId));
    return write_json_file(llm_history_file_path(), $remaining);
}

function current_user()
{
    return $_SESSION['user'] ?? null;
}

function require_login()
{
    if (!current_user()) {
        redirect('index.php');
    }
}

function human_filesize($bytes)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $size = (float) $bytes;
    $unitIndex = 0;

    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }

    return number_format($size, 1) . ' ' . $units[$unitIndex];
}

function shorten_text($value, $limit = 96)
{
    $value = trim((string) $value);
    if (strlen($value) <= $limit) {
        return $value;
    }

    return rtrim(substr($value, 0, max(0, $limit - 3))) . '...';
}

function region_options()
{
    return [
        'panhandle' => 'Panhandle',
        'north' => 'East',
        'central' => 'Central Texas',
        'gulf' => 'Gulf Coast',
        'south' => 'West',
    ];
}

function region_label($key)
{
    $regions = region_options();
    return $regions[$key] ?? ucfirst(str_replace('-', ' ', $key));
}

function default_user_list()
{
    return [
        [
            'id' => 'demo-user',
            'name' => 'Demo User',
            'email' => 'demo@texasdrive.app',
            'password' => password_hash('demo123', PASSWORD_DEFAULT),
            'provider' => 'email',
            'created_at' => gmdate('c'),
        ],
    ];
}

function ensure_initial_users()
{
    $file = app_path('data/users.json');
    if (file_exists($file)) {
        return;
    }

    write_json_file($file, default_user_list());
}

function get_users()
{
    $connection = get_mysql_connection();
    if ($connection) {
        $query = mysqli_query($connection, "SELECT id, name, email, password, provider, created_at FROM users");
        $users = [];
        if ($query) {
            while ($row = mysqli_fetch_assoc($query)) {
                $users[] = [
                    'id' => (string) $row['id'],
                    'name' => (string) $row['name'],
                    'email' => (string) $row['email'],
                    'password' => $row['password'] ?? null,
                    'provider' => $row['provider'] ?? 'email',
                    'created_at' => $row['created_at'] ?? null,
                ];
            }
        }
        mysqli_close($connection);

        if ($users === []) {
            $seedUsers = default_user_list();
            save_users($seedUsers);
            return $seedUsers;
        }

        return $users;
    }

    ensure_initial_users();
    $users = read_json_file(app_path('data/users.json'), []);
    foreach ($users as $index => $user) {
        if (!isset($users[$index]['created_at'])) {
            $users[$index]['created_at'] = gmdate('c');
        }
    }

    return $users;
}

function save_users($users)
{
    $connection = get_mysql_connection();
    if ($connection) {
        foreach ($users as $user) {
            $escapedId = mysqli_real_escape_string($connection, (string) ($user['id'] ?? ''));
            $escapedName = mysqli_real_escape_string($connection, (string) ($user['name'] ?? ''));
            $escapedEmail = mysqli_real_escape_string($connection, strtolower((string) ($user['email'] ?? '')));
            $escapedPassword = $user['password'] ?? null;
            $escapedPassword = $escapedPassword === null ? 'NULL' : "'" . mysqli_real_escape_string($connection, (string) $escapedPassword) . "'";
            $provider = mysqli_real_escape_string($connection, (string) (($user['provider'] ?? 'email')));
            $createdAt = mysqli_real_escape_string($connection, normalize_mysql_datetime((string) ($user['created_at'] ?? gmdate('c'))));
            $sql = "
                INSERT INTO users (id, name, email, password, provider, created_at)
                VALUES ('$escapedId', '$escapedName', '$escapedEmail', $escapedPassword, '$provider', '$createdAt')
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    email = VALUES(email),
                    password = VALUES(password),
                    provider = VALUES(provider),
                    created_at = VALUES(created_at)
            ";
            mysqli_query($connection, $sql);
        }

        mysqli_close($connection);
        return true;
    }

    return write_json_file(app_path('data/users.json'), $users);
}

function sign_in_with_email($email, $password)
{
    $email = strtolower(trim($email));
    $users = get_users();

    foreach ($users as $user) {
        if (strtolower($user['email'] ?? '') !== $email) {
            continue;
        }

        if (!isset($user['password']) || !password_verify($password, $user['password'])) {
            return null;
        }

        return [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'provider' => $user['provider'] ?? 'email',
        ];
    }

    return null;
}

function ensure_google_user($email, $name = null)
{
    $email = strtolower(trim((string) $email));
    $users = get_users();

    foreach ($users as $user) {
        if (strtolower((string) ($user['email'] ?? '')) === $email) {
            return [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'provider' => $user['provider'] ?? 'google',
            ];
        }
    }

    $friendlyName = trim((string) ($name ?? explode('@', $email)[0]));
    if ($friendlyName === '') {
        $friendlyName = explode('@', $email)[0];
    }

    $newUser = [
        'id' => 'google-' . uniqid(),
        'name' => $friendlyName,
        'email' => $email,
        'provider' => 'google',
        'created_at' => gmdate('c'),
    ];

    $users[] = $newUser;
    save_users($users);

    return [
        'id' => $newUser['id'],
        'name' => $newUser['name'],
        'email' => $newUser['email'],
        'provider' => $newUser['provider'],
    ];
}

function create_email_account($name, $email, $password)
{
    $name = trim((string) $name);
    $email = strtolower(trim((string) $email));
    $password = (string) $password;

    if ($name === '') {
        return ['ok' => false, 'message' => 'Please enter your full name.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Please enter a valid email address.'];
    }

    if (strlen($password) < 6) {
        return ['ok' => false, 'message' => 'Password must be at least 6 characters long.'];
    }

    $users = get_users();
    foreach ($users as $user) {
        if (strtolower((string) ($user['email'] ?? '')) === $email) {
            return ['ok' => false, 'message' => 'An account with that email already exists.'];
        }
    }

    $newUser = [
        'id' => 'user-' . uniqid(),
        'name' => $name,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'provider' => 'email',
        'created_at' => gmdate('c'),
    ];

    $users[] = $newUser;
    save_users($users);

    return [
        'ok' => true,
        'user' => [
            'id' => $newUser['id'],
            'name' => $newUser['name'],
            'email' => $newUser['email'],
            'provider' => $newUser['provider'],
        ],
    ];
}

function empty_region_payload()
{
    return [
        'panhandle' => [],
        'north' => [],
        'central' => [],
        'gulf' => [],
        'south' => [],
    ];
}

function ensure_region_files()
{
    $file = app_path('data/region-files.json');
    if (file_exists($file)) {
        return;
    }

    write_json_file($file, empty_region_payload());
}

function get_region_files()
{
    $connection = get_mysql_connection();
    if ($connection) {
        $query = mysqli_query($connection, "SELECT region, id, name, size, uploaded_by, uploaded_by_id, uploaded_by_email, uploaded_at, path FROM region_files ORDER BY uploaded_at DESC");
        $payload = [
            'panhandle' => [],
            'north' => [],
            'central' => [],
            'gulf' => [],
            'south' => [],
        ];

        if ($query) {
            while ($row = mysqli_fetch_assoc($query)) {
                $region = (string) ($row['region'] ?? 'panhandle');
                if (!isset($payload[$region])) {
                    $payload[$region] = [];
                }

                $payload[$region][] = [
                    'id' => (string) $row['id'],
                    'name' => (string) $row['name'],
                    'size' => (int) $row['size'],
                    'uploaded_by' => (string) $row['uploaded_by'],
                    'uploaded_by_id' => (string) ($row['uploaded_by_id'] ?? ''),
                    'uploaded_by_email' => (string) ($row['uploaded_by_email'] ?? ''),
                    'uploaded_at' => (string) $row['uploaded_at'],
                    'path' => (string) $row['path'],
                ];
            }
        }

        mysqli_close($connection);

        return $payload;
    }

    ensure_region_files();
    $default = empty_region_payload();

    $payload = read_json_file(app_path('data/region-files.json'), $default);
    foreach ($default as $key => $value) {
        if (!isset($payload[$key])) {
            $payload[$key] = $value;
        }
    }

    return $payload;
}

function save_region_files($payload)
{
    $connection = get_mysql_connection();
    if ($connection) {
        foreach ($payload as $region => $files) {
            foreach ($files as $file) {
                $id = (string) ($file['id'] ?? uniqid('mysql-', true));
                $name = mysqli_real_escape_string($connection, (string) ($file['name'] ?? 'untitled'));
                $size = (int) ($file['size'] ?? 0);
                $uploadedBy = mysqli_real_escape_string($connection, (string) ($file['uploaded_by'] ?? 'Unknown'));
                $uploadedById = mysqli_real_escape_string($connection, (string) ($file['uploaded_by_id'] ?? ''));
                $uploadedByEmail = mysqli_real_escape_string($connection, strtolower((string) ($file['uploaded_by_email'] ?? '')));
                $uploadedAt = mysqli_real_escape_string($connection, normalize_mysql_datetime((string) ($file['uploaded_at'] ?? gmdate('Y-m-d H:i:s'))));
                $path = mysqli_real_escape_string($connection, normalize_storage_relative_path((string) ($file['path'] ?? '')));
                $regionKey = mysqli_real_escape_string($connection, sanitize_region_key((string) $region));

                $sql = "
                    INSERT INTO region_files (id, region, name, size, uploaded_by, uploaded_by_id, uploaded_by_email, uploaded_at, path)
                    VALUES ('$id', '$regionKey', '$name', $size, '$uploadedBy', '$uploadedById', '$uploadedByEmail', '$uploadedAt', '$path')
                    ON DUPLICATE KEY UPDATE
                        region = VALUES(region),
                        name = VALUES(name),
                        size = VALUES(size),
                        uploaded_by = VALUES(uploaded_by),
                        uploaded_by_id = VALUES(uploaded_by_id),
                        uploaded_by_email = VALUES(uploaded_by_email),
                        uploaded_at = VALUES(uploaded_at),
                        path = VALUES(path)
                ";
                mysqli_query($connection, $sql);
            }
        }

        mysqli_close($connection);
        return true;
    }

    return write_json_file(app_path('data/region-files.json'), $payload);
}

function sanitize_region_key($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $regions = array_keys(region_options());
    return in_array($value, $regions, true) ? $value : 'panhandle';
}

function infer_file_type_label($fileName)
{
    $extension = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
    $map = [
        'pdf' => 'PDF',
        'doc' => 'Word',
        'docx' => 'Word',
        'xls' => 'Spreadsheet',
        'xlsx' => 'Spreadsheet',
        'csv' => 'Spreadsheet',
        'ppt' => 'Presentation',
        'pptx' => 'Presentation',
        'png' => 'Image',
        'jpg' => 'Image',
        'jpeg' => 'Image',
        'gif' => 'Image',
        'webp' => 'Image',
        'svg' => 'Image',
        'txt' => 'Text',
        'zip' => 'Archive',
        'gz' => 'Archive',
        'tar' => 'Archive',
        '7z' => 'Archive',
        'json' => 'Code',
        'php' => 'Code',
        'js' => 'Code',
        'css' => 'Code',
        'html' => 'Code',
        'md' => 'Code',
    ];

    return $map[$extension] ?? ($extension !== '' ? strtoupper($extension) : 'Unknown');
}

function user_uploaded_files($user)
{
    $payload = get_region_files();
    $userId = strtolower((string) ($user['id'] ?? ''));
    $userEmail = strtolower((string) ($user['email'] ?? ''));
    $userName = trim((string) ($user['name'] ?? ''));
    $files = [];

    foreach ($payload as $region => $regionFiles) {
        foreach ($regionFiles as $file) {
            $uploadedById = strtolower((string) ($file['uploaded_by_id'] ?? ''));
            $uploadedByEmail = strtolower((string) ($file['uploaded_by_email'] ?? ''));
            $uploadedByName = trim((string) ($file['uploaded_by'] ?? ''));

            $matchesUser = ($userId !== '' && $uploadedById === $userId)
                || ($userEmail !== '' && $uploadedByEmail === $userEmail)
                || ($userName !== '' && $uploadedByName === $userName);

            if (!$matchesUser) {
                continue;
            }

            $regionKey = sanitize_region_key((string) $region);
            $files[] = [
                'id' => (string) ($file['id'] ?? uniqid('vault-', true)),
                'name' => (string) ($file['name'] ?? 'untitled'),
                'size' => (int) ($file['size'] ?? 0),
                'uploaded_by' => (string) ($file['uploaded_by'] ?? $userName),
                'uploaded_at' => (string) ($file['uploaded_at'] ?? gmdate('c')),
                'region' => $regionKey,
                'region_label' => region_label($regionKey),
                'path' => normalize_storage_relative_path((string) ($file['path'] ?? '')),
                'type' => infer_file_type_label((string) ($file['name'] ?? 'untitled')) . ' (inferred)',
            ];
        }
    }

    usort($files, static fn($a, $b) => strtotime((string) $b['uploaded_at']) <=> strtotime((string) $a['uploaded_at']));

    return $files;
}

function delete_user_uploads_and_account($user)
{
    $userId = strtolower((string) ($user['id'] ?? ''));
    $userEmail = strtolower((string) ($user['email'] ?? ''));
    $userName = trim((string) ($user['name'] ?? ''));

    $payload = get_region_files();
    $updatedPayload = [];

    foreach ($payload as $region => $regionFiles) {
        $filteredFiles = [];
        foreach ($regionFiles as $file) {
            $uploadedById = strtolower((string) ($file['uploaded_by_id'] ?? ''));
            $uploadedByEmail = strtolower((string) ($file['uploaded_by_email'] ?? ''));
            $uploadedByName = trim((string) ($file['uploaded_by'] ?? ''));

            $matchesUser = ($userId !== '' && $uploadedById === $userId)
                || ($userEmail !== '' && $uploadedByEmail === $userEmail)
                || ($userName !== '' && $uploadedByName === $userName);

            if ($matchesUser) {
                $relativePath = normalize_storage_relative_path((string) ($file['path'] ?? ''));
                $absolutePath = $relativePath !== '' ? app_path($relativePath) : '';
                if ($absolutePath !== '' && is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
                continue;
            }

            $filteredFiles[] = $file;
        }

        $updatedPayload[$region] = $filteredFiles;
    }

    save_region_files($updatedPayload);

    $users = get_users();
    $filteredUsers = [];
    foreach ($users as $storedUser) {
        $storedId = strtolower((string) ($storedUser['id'] ?? ''));
        $storedEmail = strtolower((string) ($storedUser['email'] ?? ''));
        $storedName = trim((string) ($storedUser['name'] ?? ''));

        $sameUser = ($userId !== '' && $storedId === $userId)
            || ($userEmail !== '' && $storedEmail === $userEmail)
            || ($userName !== '' && $storedName === $userName);

        if (!$sameUser) {
            $filteredUsers[] = $storedUser;
        }
    }

    save_users($filteredUsers);
    delete_assistant_history_for_user($user);
    delete_llm_history_for_user($user);

    unset($_SESSION['user']);
    return true;
}

function clear_all_uploaded_data()
{
    $payload = get_region_files();
    $emptyPayload = [
        'panhandle' => [],
        'north' => [],
        'central' => [],
        'gulf' => [],
        'south' => [],
    ];

    foreach ($payload as $region => $regionFiles) {
        foreach ($regionFiles as $file) {
            $relativePath = normalize_storage_relative_path((string) ($file['path'] ?? ''));
            $absolutePath = $relativePath !== '' ? app_path($relativePath) : '';
            if ($absolutePath !== '' && is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }
    }

    $uploadsRoot = app_path('storage/uploads');
    if (is_dir($uploadsRoot)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadsRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileInfo) {
            $path = $fileInfo->getPathname();
            if ($fileInfo->isDir()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }

    save_region_files($emptyPayload);
    return true;
}

function all_region_files_for_vault()
{
    $payload = get_region_files();
    $entries = [];

    foreach ($payload as $region => $files) {
        foreach ($files as $file) {
            $uploadedAt = (string) ($file['uploaded_at'] ?? gmdate('c'));
            $path = normalize_storage_relative_path((string) ($file['path'] ?? ''));
            $regionKey = sanitize_region_key((string) $region);

            $entries[] = [
                'id' => (string) ($file['id'] ?? uniqid('vault-', true)),
                'name' => (string) ($file['name'] ?? 'untitled'),
                'size' => (int) ($file['size'] ?? 0),
                'uploaded_by' => (string) ($file['uploaded_by'] ?? 'Unknown'),
                'uploaded_at' => $uploadedAt,
                'region' => $regionKey,
                'region_label' => region_label($regionKey),
                'path' => $path,
                'type' => infer_file_type_label((string) ($file['name'] ?? 'untitled')) . ' (inferred)',
            ];
        }
    }

    usort($entries, static fn($a, $b) => strtotime((string) $b['uploaded_at']) <=> strtotime((string) $a['uploaded_at']));

    return $entries;
}

function normalize_mysql_datetime($value)
{
    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return gmdate('Y-m-d H:i:s');
    }

    return gmdate('Y-m-d H:i:s', $timestamp);
}

function normalize_storage_relative_path($path)
{
    $clean = str_replace('\\', '/', trim((string) $path));
    $clean = preg_replace('#/+#', '/', $clean) ?? $clean;

    if (preg_match('#(?:^|/)(storage/.+)$#', $clean, $matches)) {
        return ltrim($matches[1], '/');
    }

    if (preg_match('#(?:^|/)(uploads/.+)$#', $clean, $matches)) {
        return ltrim($matches[1], '/');
    }

    return ltrim($clean, '/.');
}

function append_region_file($regionKey, $payload)
{
    $regionKey = sanitize_region_key($regionKey);
    $files = get_region_files();
    $files[$regionKey][] = $payload;
    return save_region_files($files);
}

function get_storage_mode_status()
{
    $mysqlConfig = get_mysql_config();
    if (!empty($mysqlConfig['enabled'])) {
        if (!mysql_extension_available()) {
            return [
                'mode' => 'Files',
                'status' => 'fallback',
                'detail' => 'mysqli extension missing',
            ];
        }

        $connection = get_mysql_connection();
        if ($connection) {
            mysqli_close($connection);
            return [
                'mode' => 'MySQL',
                'status' => 'active',
                'detail' => 'configured and connected',
            ];
        }

        return [
            'mode' => 'Files',
            'status' => 'fallback',
            'detail' => 'MySQL configured but unavailable: ' . (get_last_mysql_connection_error() ?: 'unknown reason'),
        ];
    }

    return [
        'mode' => 'Files',
        'status' => 'default',
        'detail' => 'JSON data store is active',
    ];
}

function get_uploads_dir_status()
{
    $directory = app_path('storage/uploads');
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            return [
                'path' => $directory,
                'status' => 'missing',
                'detail' => 'directory could not be created',
            ];
        }
    }

    if (!is_dir($directory)) {
        return [
            'path' => $directory,
            'status' => 'missing',
            'detail' => 'not a valid directory',
        ];
    }

    if (!is_writable($directory)) {
        return [
            'path' => $directory,
            'status' => 'blocked',
            'detail' => 'read-only or not writable by PHP',
        ];
    }

    return [
        'path' => $directory,
        'status' => 'ready',
        'detail' => 'writable by PHP',
    ];
}

function get_llm_config()
{
    $config = get_app_config();
    $llm = $config['llm'] ?? [];

    return [
        'enabled' => !empty($llm['enabled']),
        'provider' => strtolower((string) ($llm['provider'] ?? 'ollama')),
        'api_url' => (string) ($llm['api_url'] ?? 'http://localhost:11434/api/generate'),
        'model' => (string) ($llm['model'] ?? 'llama3.1:8b'),
        'temperature' => (float) ($llm['temperature'] ?? 0.7),
    ];
}

function command_exists($command)
{
    $path = '';
    $shell = PHP_OS_FAMILY === 'Windows' ? 'where ' : 'which ';
    $result = @shell_exec($shell . escapeshellarg($command) . ' 2>/dev/null');
    if (is_string($result)) {
        $path = trim((string) $result);
    }

    return $path !== '';
}

function extract_text_from_file($filePath)
{
    if (!is_file($filePath)) {
        return '';
    }

    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $raw = @file_get_contents($filePath);
    if ($raw === false) {
        return '';
    }

    $textTypes = ['txt', 'md', 'csv', 'json', 'log', 'xml', 'yaml', 'yml', 'sql', 'js', 'ts', 'tsx', 'jsx', 'css', 'html', 'htm', 'php', 'py', 'rb', 'java'];
    if (in_array($extension, $textTypes, true)) {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $raw) ?? $raw;
        return trim((string) $text);
    }

    if ($extension === 'pdf' && command_exists('pdftotext')) {
        $output = @shell_exec('pdftotext ' . escapeshellarg($filePath) . ' - 2>/dev/null');
        return trim((string) ($output ?? ''));
    }

    if (in_array($extension, ['doc', 'docx'], true) && command_exists('pandoc')) {
        $output = @shell_exec('pandoc ' . escapeshellarg($filePath) . ' -t plain 2>/dev/null');
        return trim((string) ($output ?? ''));
    }

    if (in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'], true) && command_exists('tesseract')) {
        $output = @shell_exec('tesseract ' . escapeshellarg($filePath) . ' stdout 2>/dev/null');
        return trim((string) ($output ?? ''));
    }

    return '';
}

function collect_vault_documents($regionFilter = null)
{
    $allFiles = get_region_files();
    $regions = $regionFilter !== null ? [$regionFilter => $allFiles[$regionFilter] ?? []] : $allFiles;
    $documents = [];

    foreach ($regions as $regionKey => $files) {
        foreach ($files as $file) {
            $relativePath = normalize_storage_relative_path((string) ($file['path'] ?? ''));
            $absolutePath = $relativePath === '' ? '' : app_path($relativePath);
            $text = $absolutePath !== '' && file_exists($absolutePath) ? extract_text_from_file($absolutePath) : '';
            $trimmed = trim((string) $text);
            $documents[] = [
                'region' => sanitize_region_key((string) $regionKey),
                'region_label' => region_label(sanitize_region_key((string) $regionKey)),
                'name' => (string) ($file['name'] ?? 'untitled'),
                'uploaded_at' => (string) ($file['uploaded_at'] ?? gmdate('c')),
                'path' => $relativePath,
                'text' => $trimmed !== '' ? $trimmed : '(No readable text was available for this document. It may be binary or an unsupported file type.)',
            ];
        }
    }

    usort($documents, static fn($a, $b) => strtotime((string) $b['uploaded_at']) <=> strtotime((string) $a['uploaded_at']));

    return $documents;
}

function build_vault_assistant_prompt($task, $question, $documents)
{
    $taskMap = [
        'summary' => 'Provide a concise summary of the documents and highlight the main themes, risks, and action items.',
        'story' => 'Turn the documents into a clear narrative or story that reads like a polished summary of what happened, why it matters, and what is likely next.',
        'confirmation' => 'Review the documents for confirming evidence and produce a short confirmation memo with the strongest supporting points.',
        'rebuttal' => 'Review the documents critically and create a balanced rebuttal or counter-argument based only on what is in the material.',
    ];

    $taskLabel = $taskMap[$task] ?? $taskMap['summary'];
    $docText = "";
    foreach ($documents as $index => $doc) {
        $docText .= "\n--- Document " . ($index + 1) . " ---\n";
        $docText .= "Region: " . $doc['region_label'] . "\n";
        $docText .= "File: " . $doc['name'] . "\n";
        $docText .= "Uploaded: " . $doc['uploaded_at'] . "\n\n";
        $docText .= $doc['text'] . "\n";
    }

    $customQuestion = trim((string) $question);
    $questionText = $customQuestion !== '' ? "\n\nUser request: " . $customQuestion : "";

    return "You are a careful analyst and writer helping review regional documents for a Texas file vault. " .
        "Use only the provided documents as the basis for your answer. " .
        "Do not invent facts. If the documents do not provide enough evidence, say so clearly.\n\n" .
        $taskLabel . "\n\nDocuments:\n" . $docText . $questionText;
}

function ask_local_llm($prompt, $model = null)
{
    $config = get_llm_config();
    if (!$config['enabled']) {
        return ['ok' => false, 'message' => 'The local LLM is disabled in config.php. Enable Ollama and set the model to use the assistant.'];
    }

    $payload = [
        'model' => $model ?: $config['model'],
        'prompt' => (string) $prompt,
        'stream' => false,
        'options' => [
            'temperature' => (float) $config['temperature'],
        ],
    ];

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => json_encode($payload),
            'ignore_errors' => true,
            'timeout' => 90,
        ],
    ]);

    $raw = @file_get_contents($config['api_url'], false, $context);
    if ($raw === false) {
        return ['ok' => false, 'message' => 'The local Ollama server could not be reached at ' . htmlspecialchars($config['api_url'], ENT_QUOTES, 'UTF-8')];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'message' => 'The model returned an unexpected response format.'];
    }

    $answer = trim((string) ($decoded['response'] ?? ''));
    if ($answer === '') {
        return ['ok' => false, 'message' => 'The model returned an empty response.'];
    }

    return ['ok' => true, 'answer' => $answer];
}

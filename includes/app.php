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

function get_mysql_connection()
{
    $config = get_mysql_config();
    if (!$config['enabled'] || $config['host'] === '' || $config['username'] === '' || $config['database'] === '') {
        return null;
    }

    $connection = @mysqli_init();
    if ($connection === false) {
        return null;
    }

    $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);

    $connected = @mysqli_real_connect($connection, $config['host'], $config['username'], $config['password'], $config['database'], $config['port']);
    if ($connected) {
        ensure_mysql_schema($connection);
        return $connection;
    }

    $fallback = @mysqli_real_connect($connection, $config['host'], $config['username'], $config['password'], null, $config['port']);
    if (!$fallback) {
        @mysqli_close($connection);
        return null;
    }

    $databaseName = mysqli_real_escape_string($connection, $config['database']);
    $ddl = "CREATE DATABASE IF NOT EXISTS `$databaseName`;";
    $createResult = mysqli_query($connection, $ddl);
    if ($createResult === false) {
        @mysqli_close($connection);
        return null;
    }

    mysqli_select_db($connection, $config['database']);
    ensure_mysql_schema($connection);

    return $connection;
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
            provider VARCHAR(50) NOT NULL DEFAULT 'email'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $regionSql = "
        CREATE TABLE IF NOT EXISTS region_files (
            id VARCHAR(255) PRIMARY KEY,
            region VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            size BIGINT NOT NULL DEFAULT 0,
            uploaded_by VARCHAR(255) NOT NULL,
            uploaded_at DATETIME NOT NULL,
            path VARCHAR(500) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    mysqli_query($connection, $userSql);
    mysqli_query($connection, $regionSql);
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
        mkdir($directory, 0777, true);
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return file_put_contents($filePath, $json . PHP_EOL) !== false;
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

function region_options()
{
    return [
        'panhandle' => 'Panhandle',
        'north' => 'North Texas',
        'central' => 'Central Texas',
        'gulf' => 'Gulf Coast',
        'south' => 'South Texas',
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
        $query = mysqli_query($connection, "SELECT id, name, email, password, provider FROM users");
        $users = [];
        if ($query) {
            while ($row = mysqli_fetch_assoc($query)) {
                $users[] = [
                    'id' => (string) $row['id'],
                    'name' => (string) $row['name'],
                    'email' => (string) $row['email'],
                    'password' => $row['password'] ?? null,
                    'provider' => $row['provider'] ?? 'email',
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
    return read_json_file(app_path('data/users.json'), []);
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
            $sql = "
                INSERT INTO users (id, name, email, password, provider)
                VALUES ('$escapedId', '$escapedName', '$escapedEmail', $escapedPassword, '$provider')
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    email = VALUES(email),
                    password = VALUES(password),
                    provider = VALUES(provider)
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

function ensure_google_user($email)
{
    $email = strtolower(trim($email));
    $users = get_users();

    foreach ($users as $user) {
        if (strtolower($user['email'] ?? '') === $email) {
            return [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'provider' => $user['provider'] ?? 'google',
            ];
        }
    }

    $newUser = [
        'id' => 'google-' . uniqid(),
        'name' => explode('@', $email)[0],
        'email' => $email,
        'provider' => 'google',
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

function default_region_payload()
{
    return [
        'panhandle' => [
            [
                'id' => 'pan-01',
                'name' => 'roadmap.pdf',
                'size' => 1024 * 1024,
                'uploaded_by' => 'Demo User',
                'uploaded_at' => '2026-09-01T11:00:00Z',
            ],
            [
                'id' => 'pan-02',
                'name' => 'community-site-plan.docx',
                'size' => 560 * 1024,
                'uploaded_by' => 'Demo User',
                'uploaded_at' => '2026-09-01T12:30:00Z',
            ],
        ],
        'north' => [
            [
                'id' => 'north-01',
                'name' => 'construction-timeline.xlsx',
                'size' => 814 * 1024,
                'uploaded_by' => 'Demo User',
                'uploaded_at' => '2026-09-02T09:45:00Z',
            ],
        ],
        'central' => [
            [
                'id' => 'central-01',
                'name' => 'regional-budget.csv',
                'size' => 320 * 1024,
                'uploaded_by' => 'Demo User',
                'uploaded_at' => '2026-09-03T08:15:00Z',
            ],
        ],
        'gulf' => [
            [
                'id' => 'gulf-01',
                'name' => 'coastal-incident-report.pdf',
                'size' => 2 * 1024 * 1024,
                'uploaded_by' => 'Demo User',
                'uploaded_at' => '2026-09-03T14:00:00Z',
            ],
        ],
        'south' => [
            [
                'id' => 'south-01',
                'name' => 'border-logistics.xlsx',
                'size' => 671 * 1024,
                'uploaded_by' => 'Demo User',
                'uploaded_at' => '2026-09-02T16:30:00Z',
            ],
        ],
    ];
}

function ensure_region_files()
{
    $file = app_path('data/region-files.json');
    if (file_exists($file)) {
        return;
    }

    write_json_file($file, default_region_payload());
}

function get_region_files()
{
    $connection = get_mysql_connection();
    if ($connection) {
        $query = mysqli_query($connection, "SELECT region, id, name, size, uploaded_by, uploaded_at, path FROM region_files ORDER BY uploaded_at DESC");
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
                    'uploaded_at' => (string) $row['uploaded_at'],
                    'path' => (string) $row['path'],
                ];
            }
        }

        mysqli_close($connection);

        if (array_sum(array_map('count', $payload)) === 0) {
            $seedPayload = default_region_payload();
            save_region_files($seedPayload);
            return $seedPayload;
        }

        return $payload;
    }

    ensure_region_files();
    $default = [
        'panhandle' => [],
        'north' => [],
        'central' => [],
        'gulf' => [],
        'south' => [],
    ];

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
                $uploadedAt = mysqli_real_escape_string($connection, normalize_mysql_datetime((string) ($file['uploaded_at'] ?? gmdate('Y-m-d H:i:s'))));
                $path = mysqli_real_escape_string($connection, normalize_storage_relative_path((string) ($file['path'] ?? '')));
                $regionKey = mysqli_real_escape_string($connection, sanitize_region_key((string) $region));

                $sql = "
                    INSERT INTO region_files (id, region, name, size, uploaded_by, uploaded_at, path)
                    VALUES ('$id', '$regionKey', '$name', $size, '$uploadedBy', '$uploadedAt', '$path')
                    ON DUPLICATE KEY UPDATE
                        region = VALUES(region),
                        name = VALUES(name),
                        size = VALUES(size),
                        uploaded_by = VALUES(uploaded_by),
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
    $regions = array_keys(region_options());
    return in_array($value, $regions, true) ? $value : 'panhandle';
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

<?php
session_start();

const APP_ROOT = __DIR__ . '/..';

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

function ensure_initial_users()
{
    $file = app_path('data/users.json');
    if (file_exists($file)) {
        return;
    }

    $users = [
        [
            'id' => 'demo-user',
            'name' => 'Demo User',
            'email' => 'demo@texasdrive.app',
            'password' => password_hash('demo123', PASSWORD_DEFAULT),
            'provider' => 'email',
        ],
    ];

    write_json_file($file, $users);
}

function get_users()
{
    ensure_initial_users();
    return read_json_file(app_path('data/users.json'), []);
}

function save_users($users)
{
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

function ensure_region_files()
{
    $file = app_path('data/region-files.json');
    if (file_exists($file)) {
        return;
    }

    $payload = [
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

    write_json_file($file, $payload);
}

function get_region_files()
{
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
    return write_json_file(app_path('data/region-files.json'), $payload);
}

function sanitize_region_key($value)
{
    $regions = array_keys(region_options());
    return in_array($value, $regions, true) ? $value : 'panhandle';
}

function append_region_file($regionKey, $payload)
{
    $regionKey = sanitize_region_key($regionKey);
    $files = get_region_files();
    $files[$regionKey][] = $payload;
    return save_region_files($files);
}

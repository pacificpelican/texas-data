<?php
require_once __DIR__ . '/includes/app.php';
require_login();

$user = current_user();
$userSettings = get_user_settings($user);
$regionSettings = $userSettings['regions'];
$theme = $userSettings['display']['theme'];
$storageStatus = get_storage_mode_status();
$uploadsStatus = get_uploads_dir_status();
$errors = [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedRegions = $_POST['regions'] ?? [];
    $regionsInput = [];
    foreach (array_keys(default_region_settings()) as $key) {
        $entry = isset($postedRegions[$key]) && is_array($postedRegions[$key]) ? $postedRegions[$key] : [];
        $regionsInput[$key] = [
            'label' => (string) ($entry['label'] ?? ''),
            'default_query' => (string) ($entry['default_query'] ?? ''),
        ];
    }

    $input = [
        'regions' => $regionsInput,
        'display' => [
            'theme' => (string) ($_POST['theme'] ?? 'light'),
        ],
    ];

    if (save_user_settings($input, $user)) {
        $saved = true;
        $userSettings = get_user_settings($user);
        $regionSettings = $userSettings['regions'];
        $theme = $userSettings['display']['theme'];
    } else {
        $errors[] = 'Settings could not be saved. Check write permissions for the data folder.';
    }
}
?>
<!DOCTYPE html>
<html lang="en"<?= theme_attribute($user) ?>>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Texas Settings</title>
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
    <main class="profile-page">
        <header class="topbar">
            <div class="brand">
                <a href="vault.php" class="brand-link" aria-label="Open Texas vault overview">
                    <div class="brand-mark">TX</div>
                </a>
                <span>Texas Regional Vault</span>
            </div>

            <div class="topbar-right">
                <div class="user-chip">
                    <span>Signed in as <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <a href="profile.php">Profile</a>
                    <a href="dashboard.php">Back to map</a>
                    <a href="vault.php">Vault</a>
                    <a href="assistant.php">AI Assistant</a>
                    <a href="llm.php">LLM Chat</a>
                    <a href="shakespeare.php">Shake-speare</a>
                    <a href="logout.php">Log out</a>
                </div>

                <div class="debug-shell">
                    <button type="button" class="debug-toggle" aria-controls="settings-debug-panel" aria-expanded="false">🪲</button>
                    <div id="settings-debug-panel" class="debug-panel" aria-live="polite" hidden>
                        <div class="debug-row">
                            <span class="debug-label">Data:</span>
                            <span class="debug-pill <?= $storageStatus['status'] === 'active' ? 'good' : ($storageStatus['status'] === 'fallback' ? 'warn' : 'info') ?>"><?= htmlspecialchars($storageStatus['mode'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="debug-row">
                            <span class="debug-label">Uploads:</span>
                            <span class="debug-pill <?= $uploadsStatus['status'] === 'ready' ? 'good' : ($uploadsStatus['status'] === 'blocked' ? 'warn' : 'info') ?>"><?= htmlspecialchars($uploadsStatus['status'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <button type="button" class="hard-refresh" onclick="window.location.href = window.location.pathname + '?refresh=' + Date.now();">Hard refresh</button>
                    </div>
                </div>
            </div>
        </header>

        <section class="profile-panel">
            <div class="profile-header">
                <h1>Texas settings</h1>
            </div>

            <p class="muted-note">The Texas region names below are just examples — rename them to anything that fits your project (e.g. neighborhoods, clients, topics). The default query is pre-filled in the AI Assistant whenever that region is selected.</p>

            <?php foreach ($errors as $error): ?>
                <div class="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endforeach; ?>

            <?php if ($saved): ?>
                <div class="alert success">Settings saved.</div>
            <?php endif; ?>

            <form method="post" class="form-stack">
                <h2 style="margin: 0;">Display settings</h2>
                <div class="profile-card">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="radio" name="theme" value="light" <?= $theme === 'light' ? 'checked' : '' ?> />
                        Light mode (default)
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="radio" name="theme" value="dark" <?= $theme === 'dark' ? 'checked' : '' ?> />
                        Dark mode
                    </label>
                </div>

                <h2 style="margin: 10px 0 0;">Texas settings</h2>
                <?php foreach ($regionSettings as $key => $setting): ?>
                    <div class="profile-card">
                        <h3 style="margin-top: 0;"><?= htmlspecialchars($setting['label'], ENT_QUOTES, 'UTF-8') ?> <span class="file-tag"><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?></span></h3>

                        <label>
                            Region name
                            <input type="text" name="regions[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>][label]" value="<?= htmlspecialchars($setting['label'], ENT_QUOTES, 'UTF-8') ?>" required />
                        </label>

                        <label>
                            Default query
                            <textarea name="regions[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>][default_query]" placeholder="Example: Summarize the most important themes across these documents."><?= htmlspecialchars($setting['default_query'], ENT_QUOTES, 'UTF-8') ?></textarea>
                        </label>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="primary">Save settings</button>
            </form>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.debug-toggle').forEach(function (button) {
                var panel = document.getElementById(button.getAttribute('aria-controls'));
                if (!panel) {
                    return;
                }

                button.addEventListener('click', function () {
                    var isOpen = !panel.hidden;
                    panel.hidden = isOpen;
                    button.setAttribute('aria-expanded', String(!isOpen));
                    button.classList.toggle('is-open', !isOpen);
                });
            });
        });
    </script>
</body>
</html>

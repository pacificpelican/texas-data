<?php
require_once __DIR__ . '/includes/app.php';
require_login();

$user = current_user();
$entry = shakespeare_history_entry_for_user($_GET['id'] ?? '', $user);
if ($entry === null) {
    http_response_code(404);
    exit('Shake-speare prediction not found.');
}
$modelLabel = (string) (($entry['model'] ?? '') !== '' ? $entry['model'] : (get_llm_config()['model'] ?? 'Unknown model'));
$createdTimestamp = strtotime((string) ($entry['created_at'] ?? 'now'));
$documentTitle = 'Shakespeare_Prediction_' . gmdate('Ymd_His', $createdTimestamp === false ? time() : $createdTimestamp);
?>
<!DOCTYPE html>
<html lang="en"<?= theme_attribute() ?>>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
    <main class="profile-page">
        <header class="topbar">
            <div class="brand">
                <a href="profile.php" class="brand-link" aria-label="Open your profile"><div class="brand-mark">TX</div></a>
                <span>Texas Regional Vault</span>
            </div>
            <div class="user-chip">
                <a href="profile.php">Profile</a>
                <a href="shakespeare.php">Shake-speare</a>
                <a href="llm.php">LLM Chat</a>
                <a href="assistant.php">AI Assistant</a>
                <a href="settings.php">Settings</a>
                <a href="logout.php">Log out</a>
            </div>
        </header>

        <section class="profile-panel">
            <div class="profile-header">
                <h1>Shake-speare prediction</h1>
                <div class="profile-header-actions">
                    <button type="button" class="print-button" onclick="window.print();" title="Print or save as PDF" aria-label="Print or save as PDF">🖨️</button>
                    <span class="meta-badge"><?= htmlspecialchars($modelLabel, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
            <div class="profile-card">
                <div class="profile-meta">
                    <div class="profile-row"><span>Written by</span><strong><?= htmlspecialchars((string) ($entry['user_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div class="profile-row"><span>Created</span><strong><?= htmlspecialchars(date('M j, Y · g:i A', strtotime((string) ($entry['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div class="profile-row"><span>LLM</span><strong><?= htmlspecialchars($modelLabel, ENT_QUOTES, 'UTF-8') ?></strong></div>
                </div>
            </div>
            <div class="profile-history">
                <h2>Source passage</h2>
                <div class="ai-response"><?= htmlspecialchars((string) ($entry['excerpt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                <h2>Continuation</h2>
                <div class="ai-response"><?= htmlspecialchars((string) ($entry['response'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <footer class="print-credit">Generated using Texas Data by Daniel McKeown <a href="https://altaredwood.work">https://altaredwood.work</a></footer>
        </section>
    </main>
</body>
</html>

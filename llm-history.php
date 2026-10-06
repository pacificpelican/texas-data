<?php
require_once __DIR__ . '/includes/app.php';
require_login();

$user = current_user();
$entry = llm_history_entry_for_user($_GET['id'] ?? '', $user);
if ($entry === null) {
    require __DIR__ . '/404.php';
    exit;
}
$modelLabel = (string) (($entry['model'] ?? '') !== '' ? $entry['model'] : (get_llm_config()['model'] ?? 'Unknown model'));
$createdTimestamp = strtotime((string) ($entry['created_at'] ?? 'now'));
$documentTitle = 'LLM_Chat_History_' . gmdate('Ymd_His', $createdTimestamp === false ? time() : $createdTimestamp);
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
                <a href="profile.php" class="brand-link" aria-label="Open your profile"><div class="brand-mark"><svg viewBox="0 0 64 48" aria-hidden="true" focusable="false"><path d="M32 12 C25 12 20 10 15 7 C9 4 5 3 3 6 C1 9 5 13 11 15 C16 17 21 18 25 19 L25 23 C25 26 26 29 28 31 L26 44 L38 44 L36 31 C38 29 39 26 39 23 L39 19 C43 18 48 17 53 15 C59 13 63 9 61 6 C59 3 55 4 49 7 C44 10 39 12 32 12 Z"/></svg></div></a>
                <span>Texas Data</span>
            </div>
            <div class="user-chip">
                <a href="profile.php">Profile</a>
                <a href="llm.php">LLM Chat</a>
                <a href="assistant.php">AI Assistant</a>
                <a href="shakespeare.php">Shake-speare</a>
                <a href="settings.php">Settings</a>
                <a href="logout.php">Log out</a>
            </div>
        </header>

        <section class="profile-panel">
            <div class="profile-header">
                <h1>LLM chat</h1>
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
                    <?php if (trim((string) ($entry['temperature'] ?? '')) !== ''): ?>
                        <div class="profile-row"><span>Temperature</span><strong><?= htmlspecialchars((string) $entry['temperature'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="profile-history">
                <h2>Prompt</h2>
                <div class="ai-response"><?= htmlspecialchars((string) ($entry['prompt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                <h2>Response</h2>
                <div class="ai-response"><?= htmlspecialchars((string) ($entry['response'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <footer class="print-credit">Generated using Texas Data by Daniel McKeown <a href="https://altaredwood.work">https://altaredwood.work</a></footer>
        </section>
    </main>
</body>
</html>

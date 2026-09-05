<?php
require_once __DIR__ . '/includes/app.php';
require_login();

$user = current_user();
$entry = assistant_history_entry_for_user($_GET['id'] ?? '', $user);
if ($entry === null) {
    http_response_code(404);
    exit('Assistant history entry not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Assistant History</title>
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
                <a href="assistant.php">AI Assistant</a>
                <a href="logout.php">Log out</a>
            </div>
        </header>

        <section class="profile-panel">
            <div class="profile-header">
                <h1>Assistant request</h1>
                <span class="meta-badge"><?= htmlspecialchars(ucfirst((string) ($entry['task'] ?? 'summary')), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="profile-card">
                <div class="profile-meta">
                    <div class="profile-row"><span>Written by</span><strong><?= htmlspecialchars((string) ($entry['user_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div class="profile-row"><span>Created</span><strong><?= htmlspecialchars(date('M j, Y · g:i A', strtotime((string) ($entry['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div class="profile-row"><span>Region</span><strong><?= htmlspecialchars(($entry['region'] ?? '') === '' ? 'All regions' : region_label((string) $entry['region']), ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div class="profile-row"><span>LLM</span><strong><?= htmlspecialchars((string) (($entry['model'] ?? '') !== '' ? $entry['model'] : (get_llm_config()['model'] ?? 'Unknown model')), ENT_QUOTES, 'UTF-8') ?></strong></div>
                </div>
            </div>
            <div class="profile-history">
                <h2>Full prompt</h2>
                <div class="ai-response"><?= htmlspecialchars((string) ($entry['prompt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                <h2>Response</h2>
                <div class="ai-response"><?= htmlspecialchars((string) ($entry['response'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                <h2>Request</h2>
                <div class="ai-response"><?= htmlspecialchars((string) (($entry['question'] ?? '') !== '' ? $entry['question'] : 'No follow-up question was provided.'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </section>
    </main>
</body>
</html>
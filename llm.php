<?php
require_once __DIR__ . '/includes/app.php';
require_login();

$user = current_user();
$prompt = trim((string) ($_POST['prompt'] ?? ''));
$llmConfig = get_llm_config();
$storageStatus = get_storage_mode_status();
$uploadsStatus = get_uploads_dir_status();
$llmReply = '';
$llmError = '';
$recentChats = array_slice(llm_history_for_user($user), 0, 5);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($prompt === '') {
        $llmError = 'Please enter a prompt for the LLM.';
    } else {
        $result = ask_local_llm($prompt);
        if ($result['ok']) {
            $llmReply = (string) $result['answer'];
            create_llm_history($user, $prompt, $llmReply, $llmConfig['model']);
            $recentChats = array_slice(llm_history_for_user($user), 0, 5);
        } else {
            $llmError = (string) $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"<?= theme_attribute($user) ?>>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Texas LLM Chat</title>
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
    <main class="assistant-page">
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
                    <a href="settings.php">Settings</a>
                    <a href="dashboard.php">Back to map</a>
                    <a href="vault.php">Vault</a>
                    <a href="assistant.php">AI Assistant</a>
                    <a href="shakespeare.php">Shake-speare</a>
                    <a href="logout.php">Log out</a>
                </div>

                <div class="debug-shell">
                    <button type="button" class="debug-toggle" aria-controls="llm-debug-panel" aria-expanded="false">🪲</button>
                    <div id="llm-debug-panel" class="debug-panel" aria-live="polite" hidden>
                        <div class="debug-row">
                            <span class="debug-label">Data:</span>
                            <span class="debug-pill <?= $storageStatus['status'] === 'active' ? 'good' : ($storageStatus['status'] === 'fallback' ? 'warn' : 'info') ?>"><?= htmlspecialchars($storageStatus['mode'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="debug-row">
                            <span class="debug-label">Uploads:</span>
                            <span class="debug-pill <?= $uploadsStatus['status'] === 'ready' ? 'good' : ($uploadsStatus['status'] === 'blocked' ? 'warn' : 'info') ?>"><?= htmlspecialchars($uploadsStatus['status'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="debug-row">
                            <span class="debug-label">Model:</span>
                            <span class="debug-pill info"><?= htmlspecialchars((string) $llmConfig['model'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <button type="button" class="hard-refresh" onclick="window.location.href = window.location.pathname + '?refresh=' + Date.now();">Hard refresh</button>
                    </div>
                </div>
            </div>
        </header>

        <section class="assistant-shell">
            <aside class="assistant-form">
                <h2>General LLM Chat</h2>

                <form method="post">
                    <label>
                        Your prompt
                        <textarea name="prompt" placeholder="Ask the model anything — brainstorm, explain, draft, translate..."><?= htmlspecialchars($prompt, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>

                    <button type="submit" class="primary">Send to LLM</button>
                </form>

                <div class="assistant-file-list">
                    <h3>Recent chats</h3>
                    <?php if ($recentChats === []): ?>
                        <div class="ai-empty">No chats yet. Every prompt and answer is saved to your profile under LLM chat history.</div>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($recentChats as $entry): ?>
                                <li><a href="llm-history.php?id=<?= rawurlencode((string) $entry['id']) ?>" class="file-link"><?= htmlspecialchars(shorten_text((string) ($entry['prompt'] ?? ''), 48), ENT_QUOTES, 'UTF-8') ?></a> &middot; <?= htmlspecialchars(date('M j, g:i A', strtotime((string) ($entry['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </aside>

            <section class="assistant-output">
                <h2>LLM response</h2>

                <?php if ($llmError !== ''): ?>
                    <div class="alert"><?= htmlspecialchars($llmError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php elseif ($llmReply !== ''): ?>
                    <div class="ai-response"><?= htmlspecialchars($llmReply, ENT_QUOTES, 'UTF-8') ?></div>
                <?php else: ?>
                    <div class="ai-empty">Type any prompt and send it to the local model (<?= htmlspecialchars((string) $llmConfig['model'], ENT_QUOTES, 'UTF-8') ?>). Your exchanges are saved to your profile.</div>
                <?php endif; ?>
            </section>
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

<?php
require_once __DIR__ . '/includes/app.php';
require_login();

$user = current_user();
$storageStatus = get_storage_mode_status();
$uploadsStatus = get_uploads_dir_status();
$uploadedFiles = user_uploaded_files($user);
$assistantHistory = assistant_history_for_user($user);
$llmHistory = llm_history_for_user($user);
$shakespeareHistory = shakespeare_history_for_user($user);
$signupTimestamp = (string) ($user['created_at'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    delete_user_uploads_and_account($user);
    redirect('logout.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_uploaded_data'])) {
    clear_user_uploaded_data($user);
    redirect('profile.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_created_content'])) {
    delete_assistant_history_for_user($user);
    delete_llm_history_for_user($user);
    delete_shakespeare_history_for_user($user);
    redirect('profile.php');
}
?>
<!DOCTYPE html>
<html lang="en"<?= theme_attribute() ?>>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>User Profile</title>
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
    <main class="profile-page">
        <header class="topbar">
            <div class="brand">
                <a href="vault.php" class="brand-link" aria-label="Open Texas vault overview">
                    <div class="brand-mark"><svg viewBox="0 0 64 48" aria-hidden="true" focusable="false"><path d="M32 12 C25 12 20 10 15 7 C9 4 5 3 3 6 C1 9 5 13 11 15 C16 17 21 18 25 19 L25 23 C25 26 26 29 28 31 L26 44 L38 44 L36 31 C38 29 39 26 39 23 L39 19 C43 18 48 17 53 15 C59 13 63 9 61 6 C59 3 55 4 49 7 C44 10 39 12 32 12 Z"/></svg></div>
                </a>
                <span>Texas Data</span>
            </div>

            <div class="topbar-right">
                <div class="user-chip">
                    <span>Signed in as <?= htmlspecialchars((string) ($user['name'] ?? 'User'), ENT_QUOTES, 'UTF-8') ?></span>
                    <a href="dashboard.php">Back to map</a>
                    <a href="vault.php">Vault</a>
                    <a href="assistant.php">AI Assistant</a>
                    <a href="llm.php">LLM Chat</a>
                    <a href="shakespeare.php">Shake-speare</a>
                    <a href="settings.php">Settings</a>
                    <a href="logout.php">Log out</a>
                </div>

                <div class="debug-shell">
                    <button type="button" class="debug-toggle" aria-controls="profile-debug-panel" aria-expanded="false">🪲</button>
                    <div id="profile-debug-panel" class="debug-panel" aria-live="polite" hidden>
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
                <h1>Profile</h1>
                <span class="meta-badge"><?= count($uploadedFiles) ?> uploaded file<?= count($uploadedFiles) === 1 ? '' : 's' ?></span>
            </div>

            <div class="profile-grid">
                <div class="profile-card">
                    <h2>Account details</h2>
                    <div class="profile-meta">
                        <div class="profile-row">
                            <span>Name</span>
                            <strong><?= htmlspecialchars((string) ($user['name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                        <div class="profile-row">
                            <span>Email</span>
                            <strong><?= htmlspecialchars(strtolower((string) ($user['email'] ?? 'Unknown')), ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                        <div class="profile-row">
                            <span>Signed up</span>
                            <strong>
                                <?php if ($signupTimestamp !== ''): ?>
                                    <?= htmlspecialchars(date('M j, Y · g:i A', strtotime($signupTimestamp)), ENT_QUOTES, 'UTF-8') ?>
                                <?php else: ?>
                                    Unknown
                                <?php endif; ?>
                            </strong>
                        </div>
                        <div class="profile-row">
                            <span>Login type</span>
                            <strong><?= htmlspecialchars((string) (($user['provider'] ?? 'email')), ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                    </div>
                </div>

                <div class="profile-files">
                    <h2>Your uploaded files</h2>
                    <?php if (empty($uploadedFiles)): ?>
                        <div class="empty-state">You have not uploaded any files yet.</div>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($uploadedFiles as $file): ?>
                                <li>
                                    <div class="profile-file-main">
                                        <?php if (!empty($file['path'])): ?>
                                            <strong><a href="file.php?path=<?= rawurlencode((string) $file['path']) ?>" class="file-link"><?= htmlspecialchars((string) $file['name'], ENT_QUOTES, 'UTF-8') ?></a></strong>
                                        <?php else: ?>
                                            <strong><?= htmlspecialchars((string) $file['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?php endif; ?>
                                        <small><?= htmlspecialchars((string) $file['region_label'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(date('M j, Y · g:i A', strtotime((string) $file['uploaded_at'])), ENT_QUOTES, 'UTF-8') ?></small>
                                    </div>
                                    <span class="file-tag"><?= human_filesize((int) $file['size']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <section class="profile-history">
                <h2>AI assistant history</h2>
                <?php if ($assistantHistory === []): ?>
                    <div class="empty-state">You have not saved any AI assistant requests yet.</div>
                <?php else: ?>
                    <ul>
                        <?php foreach ($assistantHistory as $entry): ?>
                            <?php
                            $prompt = (string) ($entry['prompt'] ?? '');
                            $documentStart = strpos($prompt, '--- Document 1 ---');
                            $documentText = $documentStart === false ? '' : substr($prompt, $documentStart + strlen('--- Document 1 ---'));
                            $words = trim($documentText) === '' ? [] : preg_split('/\s+/', trim($documentText));
                            $summary = $words === [] ? 'Document prompt unavailable' : implode(' ', array_slice($words, 0, 5));
                            ?>
                            <li>
                                <div class="profile-file-main">
                                    <strong><a href="assistant-history.php?id=<?= rawurlencode((string) $entry['id']) ?>" class="file-link"><?= htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') ?></a></strong>
                                    <small><?= htmlspecialchars((string) ($entry['user_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?> &middot; <?= htmlspecialchars(date('M j, Y · g:i A', strtotime((string) ($entry['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></small>
                                </div>
                                <span class="file-tag"><?= htmlspecialchars(ucfirst((string) ($entry['task'] ?? 'summary')), ENT_QUOTES, 'UTF-8') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="profile-history">
                <h2>LLM chat history</h2>
                <?php if ($llmHistory === []): ?>
                    <div class="empty-state">You have not saved any LLM chats yet. Visit the LLM Chat page to ask the model anything.</div>
                <?php else: ?>
                    <ul>
                        <?php foreach ($llmHistory as $entry): ?>
                            <li>
                                <div class="profile-file-main">
                                    <strong><a href="llm-history.php?id=<?= rawurlencode((string) $entry['id']) ?>" class="file-link"><?= htmlspecialchars(shorten_text((string) ($entry['prompt'] ?? ''), 72), ENT_QUOTES, 'UTF-8') ?></a></strong>
                                    <small><?= htmlspecialchars((string) ($entry['user_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?> &middot; <?= htmlspecialchars(date('M j, Y · g:i A', strtotime((string) ($entry['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></small>
                                </div>
                                <span class="file-tag"><?= htmlspecialchars((string) (($entry['model'] ?? '') !== '' ? $entry['model'] : 'LLM'), ENT_QUOTES, 'UTF-8') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="profile-history">
                <h2>Shake-speare predictions</h2>
                <?php if ($shakespeareHistory === []): ?>
                    <div class="empty-state">No predictions yet. Visit the Shake-speare Prediction Machine to continue the text of Hamlet.</div>
                <?php else: ?>
                    <ul>
                        <?php foreach ($shakespeareHistory as $entry): ?>
                            <li>
                                <div class="profile-file-main">
                                    <strong><a href="shakespeare-history.php?id=<?= rawurlencode((string) $entry['id']) ?>" class="file-link"><?= htmlspecialchars(shorten_text((string) ($entry['excerpt'] ?? ''), 72), ENT_QUOTES, 'UTF-8') ?></a></strong>
                                    <small><?= htmlspecialchars((string) ($entry['user_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?> &middot; <?= htmlspecialchars(date('M j, Y · g:i A', strtotime((string) ($entry['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></small>
                                </div>
                                <span class="file-tag"><?= htmlspecialchars((string) (($entry['model'] ?? '') !== '' ? $entry['model'] : 'LLM'), ENT_QUOTES, 'UTF-8') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <div class="delete-account">
                <h3>Clear your uploaded files</h3>
                <form method="post" onsubmit="return confirm('This removes every file you have uploaded, but keeps your account and chats. Continue?');">
                    <button type="submit" name="clear_uploaded_data" value="1">Clear my uploaded files</button>
                </form>
            </div>

            <div class="delete-account" style="margin-top: 12px; border-color: rgba(189, 61, 61, 0.3);">
                <h3>Clear your created content</h3>
                <form method="post" onsubmit="return confirm('This permanently deletes all your AI assistant chats and LLM chats. Continue?');">
                    <button type="submit" name="clear_created_content" value="1">Clear my AI and LLM chats</button>
                </form>
            </div>

            <div class="delete-account" style="margin-top: 12px; border-color: rgba(189, 61, 61, 0.3);">
                <h3>Delete this account and all uploaded files</h3>
                <form method="post" onsubmit="return confirm('This will permanently delete your account and every file you uploaded. Continue?');">
                    <button type="submit" name="delete_account" value="1">Delete my account and uploads</button>
                </form>
            </div>
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

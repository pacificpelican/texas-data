<?php
require_once __DIR__ . '/includes/app.php';
require_login();

$user = current_user();
$storageStatus = get_storage_mode_status();
$uploadsStatus = get_uploads_dir_status();
$uploadedFiles = user_uploaded_files($user);
$signupTimestamp = (string) ($user['created_at'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    delete_user_uploads_and_account($user);
    redirect('logout.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_uploaded_data'])) {
    clear_all_uploaded_data();
    redirect('profile.php');
}
?>
<!DOCTYPE html>
<html lang="en">
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
                    <div class="brand-mark">TX</div>
                </a>
                <span>Texas Regional Vault</span>
            </div>

            <div class="topbar-right">
                <div class="user-chip">
                    <span>Signed in as <?= htmlspecialchars((string) ($user['name'] ?? 'User'), ENT_QUOTES, 'UTF-8') ?></span>
                    <a href="dashboard.php">Back to map</a>
                    <a href="vault.php">Vault</a>
                    <a href="assistant.php">AI Assistant</a>
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

            <div class="delete-account">
                <h3>Clear uploaded data only</h3>
                <form method="post" onsubmit="return confirm('This removes all uploaded files and their metadata, but keeps every user account. Continue?');">
                    <button type="submit" name="clear_uploaded_data" value="1">Clear all uploaded data</button>
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

<?php
require_once __DIR__ . '/includes/app.php';
require_login();

$perPage = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$currentRegion = sanitize_region_key((string) ($_GET['region'] ?? 'panhandle'));
$allFiles = all_region_files_for_vault();
$totalFiles = count($allFiles);
$totalPages = max(1, (int) ceil($totalFiles / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;
$currentPageFiles = array_slice($allFiles, $offset, $perPage);
$user = current_user();
$storageStatus = get_storage_mode_status();
$uploadsStatus = get_uploads_dir_status();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Texas Regional Vault</title>
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
    <main class="vault-page">
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
                    <a href="dashboard.php?region=<?= rawurlencode($currentRegion) ?>">Back to map</a>
                    <a href="assistant.php?region=<?= rawurlencode($currentRegion) ?>">AI Assistant</a>
                    <a href="llm.php">LLM Chat</a>
                    <a href="logout.php">Log out</a>
                </div>

                <div class="debug-shell">
                    <button type="button" class="debug-toggle" aria-controls="vault-debug-panel" aria-expanded="false">🪲</button>
                    <div id="vault-debug-panel" class="debug-panel" aria-live="polite" hidden>
                        <div class="debug-row">
                            <span class="debug-label">Data:</span>
                            <span class="debug-pill <?= $storageStatus['status'] === 'active' ? 'good' : ($storageStatus['status'] === 'fallback' ? 'warn' : 'info') ?>"><?= htmlspecialchars($storageStatus['mode'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="debug-row">
                            <span class="debug-label">Uploads:</span>
                            <span class="debug-pill <?= $uploadsStatus['status'] === 'ready' ? 'good' : ($uploadsStatus['status'] === 'blocked' ? 'warn' : 'info') ?>"><?= htmlspecialchars($uploadsStatus['status'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <button type="button" class="hard-refresh" onclick="window.location.href = window.location.pathname + '?region=<?= rawurlencode($currentRegion) ?>&refresh=' + Date.now();">Hard refresh</button>
                    </div>
                </div>
            </div>
        </header>

        <section class="vault-panel">
            <div class="vault-header" style="padding: 22px 20px 10px;">
                <h1>All files in the vault</h1>
                <div class="meta-badge">
                    <?= htmlspecialchars((string) $totalFiles, ENT_QUOTES, 'UTF-8') ?> files
                </div>
            </div>

            <?php if ($totalFiles === 0): ?>
                <div class="empty-state" style="margin: 0 20px 20px;">No files have been uploaded yet.</div>
            <?php else: ?>
                <div class="vault-table-wrap">
                    <table class="vault-table">
                        <thead>
                            <tr>
                                <th>Uploaded</th>
                                <th>Type</th>
                                <th>File</th>
                                <th>Region</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($currentPageFiles as $file): ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('M j, Y · H:i', strtotime((string) $file['uploaded_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) $file['type'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if ($file['path'] !== ''): ?>
                                            <a class="file-link" href="file.php?path=<?= rawurlencode($file['path']) ?>"><?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') ?></a>
                                        <?php else: ?>
                                            <span><?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a class="region-link" href="dashboard.php?region=<?= rawurlencode((string) $file['region']) ?>"><?= htmlspecialchars((string) $file['region_label'], ENT_QUOTES, 'UTF-8') ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="vault-pagination">
                    <div>
                        Showing <?= htmlspecialchars((string) (($offset + 1) > $totalFiles ? $totalFiles : ($offset + 1)), ENT_QUOTES, 'UTF-8') ?>–<?= htmlspecialchars((string) min($offset + $perPage, $totalFiles), ENT_QUOTES, 'UTF-8') ?> of <?= htmlspecialchars((string) $totalFiles, ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <div class="pagination-actions">
                        <?php if ($page > 1): ?>
                            <a href="vault.php?page=<?= (int) ($page - 1) ?>">Prev</a>
                        <?php endif; ?>

                        <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                            <?php if ($pageNumber === $page): ?>
                                <span class="current"><?= (int) $pageNumber ?></span>
                            <?php else: ?>
                                <a href="vault.php?page=<?= (int) $pageNumber ?>"><?= (int) $pageNumber ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="vault.php?page=<?= (int) ($page + 1) ?>">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
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

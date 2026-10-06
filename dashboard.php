<?php
require_once __DIR__ . '/includes/app.php';
require_login();

$user = current_user();
$selectedRegion = sanitize_region_key($_GET['region'] ?? 'panhandle');
$regionMap = region_options();
$filesByRegion = get_region_files();
$errors = [];
$storageStatus = get_storage_mode_status();
$uploadsStatus = get_uploads_dir_status();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    $targetRegion = sanitize_region_key((string) ($_POST['region'] ?? 'panhandle'));
    $file = $_FILES['file'] ?? null;

    if ($file && isset($file['tmp_name']) && $file['tmp_name'] !== '' && $file['error'] === UPLOAD_ERR_OK) {
        $originalName = basename((string) $file['name']);
        $safeName = preg_replace('/[^A-Za-z0-9_.-]/', '-', $originalName);
        $safeName = trim($safeName, '-_');
        $destinationDirectory = app_path('storage/uploads/' . $targetRegion);
        if (!is_dir($destinationDirectory)) {
            mkdir($destinationDirectory, 0777, true);
        }

        $destination = $destinationDirectory . DIRECTORY_SEPARATOR . uniqid('file-', true) . '-' . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $errors[] = 'Upload failed. Check that the app can write to the project storage folder.';
        } else {
            $relativePath = normalize_storage_relative_path($destination);
            $filesByRegion[$targetRegion][] = [
                'id' => uniqid('upload-', true),
                'name' => $safeName,
                'size' => filesize($destination),
                'uploaded_by' => $user['name'],
                'uploaded_by_id' => (string) ($user['id'] ?? ''),
                'uploaded_by_email' => strtolower((string) ($user['email'] ?? '')),
                'uploaded_at' => normalize_mysql_datetime(gmdate('c')),
                'path' => $relativePath,
            ];

            if (!save_region_files($filesByRegion)) {
                $errors[] = 'File uploaded, but metadata could not be saved. Check write permissions for the data folder.';
            }

            $selectedRegion = $targetRegion;
        }
    }
}

$currentFiles = $filesByRegion[$selectedRegion] ?? [];
?>
<!DOCTYPE html>
<html lang="en"<?= theme_attribute($user) ?>>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Texas Data</title>
    <link rel="stylesheet" href="assets/style.css" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Rye&display=swap" />
</head>
<body>
    <main class="tx-page">
        <header class="topbar">
            <div class="brand">
                <a href="profile.php" class="brand-link" aria-label="Open your profile">
                    <div class="brand-mark"><svg viewBox="0 0 64 48" aria-hidden="true" focusable="false"><path d="M32 12 C25 12 20 10 15 7 C9 4 5 3 3 6 C1 9 5 13 11 15 C16 17 21 18 25 19 L25 23 C25 26 26 29 28 31 L26 44 L38 44 L36 31 C38 29 39 26 39 23 L39 19 C43 18 48 17 53 15 C59 13 63 9 61 6 C59 3 55 4 49 7 C44 10 39 12 32 12 Z"/></svg></div>
                </a>
                <span>Texas Data</span>
            </div>

            <div class="topbar-right">
                <div class="user-chip">
                    <span>Signed in as <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <a href="profile.php">Profile</a>
                    <a href="settings.php">Settings</a>
                    <a href="assistant.php">AI Assistant</a>
                    <a href="llm.php">LLM Chat</a>
                    <a href="shakespeare.php">Shake-speare</a>
                    <a href="logout.php">Log out</a>
                </div>

                <div class="debug-shell">
                    <button type="button" class="debug-toggle" aria-controls="dashboard-debug-panel" aria-expanded="false">🪲</button>
                    <div id="dashboard-debug-panel" class="debug-panel" aria-live="polite" hidden>
                        <div class="debug-row">
                            <span class="debug-label">Data:</span>
                            <span class="debug-pill <?= $storageStatus['status'] === 'active' ? 'good' : ($storageStatus['status'] === 'fallback' ? 'warn' : 'info') ?>"><?= htmlspecialchars($storageStatus['mode'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="debug-row">
                            <span class="debug-label">Uploads:</span>
                            <span class="debug-pill <?= $uploadsStatus['status'] === 'ready' ? 'good' : ($uploadsStatus['status'] === 'blocked' ? 'warn' : 'info') ?>"><?= htmlspecialchars($uploadsStatus['status'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <button type="button" class="hard-refresh" onclick="window.location.href = window.location.pathname + '?region=<?= rawurlencode($selectedRegion) ?>&refresh=' + Date.now();">Hard refresh</button>
                    </div>
                </div>
            </div>
        </header>

        <section class="dashboard-layout">
            <div class="panel map-panel">
                <div class="map-header">
                    <h2>Texas Regions</h2>
                    <span><?= htmlspecialchars($regionMap[$selectedRegion] ?? 'Panhandle', ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <svg class="map-svg" viewBox="0 0 500 620" role="img" aria-label="Texas map divided into five regions">
                    <a class="map-region region-1 <?= $selectedRegion === 'panhandle' ? 'active' : '' ?>" href="dashboard.php?region=panhandle">
                        <polygon points="130,40 330,40 330,240 130,240" />
                    </a>
                    <a class="map-region region-2 <?= $selectedRegion === 'north' ? 'active' : '' ?>" href="dashboard.php?region=north">
                        <polygon points="350,95 490,95 490,185 350,185" />
                    </a>
                    <a class="map-region region-3 <?= $selectedRegion === 'central' ? 'active' : '' ?>" href="dashboard.php?region=central">
                        <polygon points="120,260 220,260 220,340 120,340" />
                    </a>
                    <a class="map-region region-4 <?= $selectedRegion === 'gulf' ? 'active' : '' ?>" href="dashboard.php?region=gulf">
                        <polygon points="432,442 352,465 285,441 257,394 383,215 447,252 481,307 480,382" />
                    </a>
                    <a class="map-region region-5 <?= $selectedRegion === 'south' ? 'active' : '' ?>" href="dashboard.php?region=south">
                        <polygon points="20,250 100,250 100,330" />
                    </a>

                    <g font-size="16" font-weight="700" class="map-labels">
                        <?php foreach (['panhandle' => [170, 120], 'north' => [400, 145], 'central' => [136, 305], 'gulf' => [390, 350], 'south' => [38, 278]] as $labelKey => [$labelX, $labelY]): ?>
                            <a class="map-region <?= $selectedRegion === $labelKey ? 'active' : '' ?>" href="dashboard.php?region=<?= rawurlencode($labelKey) ?>">
                                <text x="<?= $labelX ?>" y="<?= $labelY ?>"><?= htmlspecialchars(region_label($labelKey), ENT_QUOTES, 'UTF-8') ?></text>
                            </a>
                        <?php endforeach; ?>
                    </g>
                </svg>
            </div>

            <aside class="panel file-panel">
                <div class="map-header">
                    <h2><?= htmlspecialchars($regionMap[$selectedRegion] ?? 'Panhandle', ENT_QUOTES, 'UTF-8') ?> files</h2>
                </div>

                <?php if (!empty($currentFiles)): ?>
                    <ul class="file-list">
                        <?php foreach ($currentFiles as $file): ?>
                            <?php $safeFilePath = normalize_storage_relative_path((string) ($file['path'] ?? '')); ?>
                            <li class="file-item">
                                <div>
                                    <?php if ($safeFilePath !== ''): ?>
                                        <strong><a href="file.php?path=<?= rawurlencode($safeFilePath) ?>" style="color: var(--primary); text-decoration: none;"><?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') ?></a></strong>
                                    <?php else: ?>
                                        <strong><?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php endif; ?>
                                    <div class="file-meta">Added by <?= htmlspecialchars($file['uploaded_by'], ENT_QUOTES, 'UTF-8') ?> · <?= date('M j, Y', strtotime((string) $file['uploaded_at'])) ?></div>
                                </div>
                                <span class="file-tag"><?= human_filesize((int) $file['size']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">This region is empty. Add the first file for this team.</div>
                <?php endif; ?>

                <div class="upload-card">
                    <h3>Add a file</h3>
                    <form method="post" enctype="multipart/form-data" class="inline-form">
                        <input type="hidden" name="region" value="<?= htmlspecialchars($selectedRegion, ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="file" name="file" required />
                        <button type="submit" name="upload_file" value="1" class="primary">Upload</button>
                    </form>
                </div>
            </aside>
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

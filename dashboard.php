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
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Texas Regional Vault</title>
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
    <main>
        <header class="topbar">
            <div class="brand">
                <div class="brand-mark">TX</div>
                <span>Texas Regional Vault</span>
            </div>

            <div class="topbar-right">
                <div class="user-chip">
                    <span>Signed in as <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></span>
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
                        <polygon points="118,40 332,32 390,70 418,126 404,188 332,226 246,216 166,204 108,168 92,104" />
                    </a>
                    <a class="map-region region-2 <?= $selectedRegion === 'north' ? 'active' : '' ?>" href="dashboard.php?region=north">
                        <polygon points="170,178 282,166 344,210 396,290 382,360 330,410 268,432 196,402 130,346 116,272" />
                    </a>
                    <a class="map-region region-3 <?= $selectedRegion === 'central' ? 'active' : '' ?>" href="dashboard.php?region=central">
                        <polygon points="160,338 250,328 332,392 358,474 344,560 242,610 178,594 118,528 100,448 114,382" />
                    </a>
                    <a class="map-region region-4 <?= $selectedRegion === 'gulf' ? 'active' : '' ?>" href="dashboard.php?region=gulf">
                        <polygon points="64,438 122,378 192,366 240,392 220,610 146,610 90,580 52,514" />
                    </a>
                    <a class="map-region region-5 <?= $selectedRegion === 'south' ? 'active' : '' ?>" href="dashboard.php?region=south">
                        <polygon points="82,242 150,182 220,198 214,298 162,426 78,396 30,320 34,276" />
                    </a>

                    <g font-size="16" font-weight="700" fill="#163d68">
                        <text x="170" y="120">Panhandle</text>
                        <text x="155" y="270">North</text>
                        <text x="155" y="490">Central</text>
                        <text x="95" y="535">Gulf</text>
                        <text x="58" y="295">South</text>
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

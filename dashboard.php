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

                <div class="debug-panel" aria-live="polite">
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
        </header>

        <section class="dashboard-layout">
            <div class="panel map-panel">
                <div class="map-header">
                    <h2>Texas Regions</h2>
                    <span><?= htmlspecialchars($regionMap[$selectedRegion] ?? 'Panhandle', ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <svg class="map-svg" viewBox="0 0 500 620" role="img" aria-label="Texas map divided into five regions">
                    <a class="map-region region-1 <?= $selectedRegion === 'panhandle' ? 'active' : '' ?>" href="dashboard.php?region=panhandle">
                        <polygon points="130,48 335,42 390,78 420,145 405,198 327,226 250,214 170,202 120,165 108,110" />
                    </a>
                    <a class="map-region region-2 <?= $selectedRegion === 'north' ? 'active' : '' ?>" href="dashboard.php?region=north">
                        <polygon points="145,180 272,170 344,202 392,286 383,356 322,408 258,430 195,398 128,346 116,258" />
                    </a>
                    <a class="map-region region-3 <?= $selectedRegion === 'central' ? 'active' : '' ?>" href="dashboard.php?region=central">
                        <polygon points="140,335 250,324 332,385 362,472 352,558 244,610 180,592 118,525 100,438 116,372" />
                    </a>
                    <a class="map-region region-4 <?= $selectedRegion === 'gulf' ? 'active' : '' ?>" href="dashboard.php?region=gulf">
                        <polygon points="78,430 140,368 197,360 248,392 230,610 142,610 92,576 60,514" />
                    </a>
                    <a class="map-region region-5 <?= $selectedRegion === 'south' ? 'active' : '' ?>" href="dashboard.php?region=south">
                        <polygon points="95,242 160,180 220,200 212,300 158,418 80,394 36,316 42,272" />
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
</body>
</html>

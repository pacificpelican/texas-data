<?php
require_once __DIR__ . '/includes/app.php';
require_login();

$user = current_user();
$currentRegion = isset($_GET['region']) ? sanitize_region_key((string) $_GET['region']) : 'panhandle';
if ($currentRegion === '') {
    $currentRegion = '';
}
$selectedRegion = isset($_POST['region']) ? sanitize_region_key((string) $_POST['region']) : $currentRegion;
if ($selectedRegion === '') {
    $selectedRegion = '';
}
$task = 'summary';
if (isset($_POST['task']) && in_array((string) $_POST['task'], ['summary', 'story', 'confirmation', 'rebuttal'], true)) {
    $task = (string) $_POST['task'];
}
$question = trim((string) ($_POST['question'] ?? ''));

$allSourceDocuments = collect_vault_documents();
$sourceDocuments = collect_vault_documents($selectedRegion === '' ? null : $selectedRegion);
$sourceDocumentsByRegion = [];
$sourceFilesByRegion = [];
foreach (array_keys(region_options()) as $regionKey) {
    $documents = collect_vault_documents($regionKey);
    $sourceDocumentsByRegion[$regionKey] = $documents;
    $sourceFilesByRegion[$regionKey] = array_map(static fn($document) => [
        'name' => (string) ($document['name'] ?? 'Untitled file'),
        'region_label' => (string) ($document['region_label'] ?? 'Unknown region'),
    ], $documents);
}
$allSourceFiles = array_map(static fn($document) => [
    'name' => (string) ($document['name'] ?? 'Untitled file'),
    'region_label' => (string) ($document['region_label'] ?? 'Unknown region'),
], $allSourceDocuments);
$storageStatus = get_storage_mode_status();
$uploadsStatus = get_uploads_dir_status();
$assistantReply = '';
$assistantError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($sourceDocuments === []) {
        $assistantError = 'There are no documents in this region to analyze yet.';
    } else {
        $prompt = build_vault_assistant_prompt($task, $question, $sourceDocuments);
        $result = ask_local_llm($prompt);
        if ($result['ok']) {
            $assistantReply = (string) $result['answer'];
            create_assistant_history($user, $selectedRegion, $task, $question, $prompt, $assistantReply, get_llm_config()['model']);
        } else {
            $assistantError = (string) $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Texas AI Assistant</title>
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
    <main class="assistant-page">
        <header class="topbar">
            <div class="brand">
                <a href="vault.php?region=<?= rawurlencode($selectedRegion) ?>" class="brand-link" aria-label="Open Texas vault overview">
                    <div class="brand-mark">TX</div>
                </a>
                <span>Texas Regional Vault</span>
            </div>

            <div class="topbar-right">
                <div class="user-chip">
                    <span>Signed in as <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <a href="profile.php">Profile</a>
                    <a href="dashboard.php?region=<?= rawurlencode($selectedRegion) ?>">Back to map</a>
                    <a href="vault.php?region=<?= rawurlencode($selectedRegion) ?>">Vault</a>
                    <a href="llm.php">LLM Chat</a>
                    <a href="logout.php">Log out</a>
                </div>

                <div class="debug-shell">
                    <button type="button" class="debug-toggle" aria-controls="assistant-debug-panel" aria-expanded="false">🪲</button>
                    <div id="assistant-debug-panel" class="debug-panel" aria-live="polite" hidden>
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

        <section class="assistant-shell">
            <aside class="assistant-form">
                <h2>AI Vault Assistant</h2>

                <form method="post">
                    <label>
                        Region
                        <select name="region">
                            <option value="">All regions</option>
                            <?php foreach (region_options() as $key => $label): ?>
                                <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedRegion === $key ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        Task
                        <select name="task">
                            <option value="summary" <?= $task === 'summary' ? 'selected' : '' ?>>Summary</option>
                            <option value="story" <?= $task === 'story' ? 'selected' : '' ?>>Narrative story</option>
                            <option value="confirmation" <?= $task === 'confirmation' ? 'selected' : '' ?>>Confirmation</option>
                            <option value="rebuttal" <?= $task === 'rebuttal' ? 'selected' : '' ?>>Rebuttal</option>
                        </select>
                    </label>

                    <label>
                        Ask a follow-up
                        <textarea name="question" placeholder="Example: What are the biggest risks or themes across these documents?"><?= htmlspecialchars($question, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>

                    <button type="submit" class="primary">Ask the vault</button>
                </form>

                <div class="assistant-file-list">
                    <h3>Current source files</h3>
                    <div id="source-files-container">
                        <?php if ($sourceDocuments === []): ?>
                            <div class="ai-empty">No files are available in this region yet.</div>
                        <?php else: ?>
                            <ul>
                                <?php foreach (array_slice($sourceDocuments, 0, 8) as $document): ?>
                                    <li><?= htmlspecialchars((string) ($document['name'] ?? 'Untitled file'), ENT_QUOTES, 'UTF-8') ?> &middot; <?= htmlspecialchars((string) ($document['region_label'] ?? 'Unknown region'), ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>

            <section class="assistant-output">
                <h2>AI response</h2>

                <?php if ($assistantError !== ''): ?>
                    <div class="alert"><?= htmlspecialchars($assistantError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php elseif ($assistantReply !== ''): ?>
                    <div class="ai-response"><?= htmlspecialchars($assistantReply, ENT_QUOTES, 'UTF-8') ?></div>
                <?php else: ?>
                    <div class="ai-empty">Choose a region, a task, and ask the vault to generate a summary, narrative, confirmation, or rebuttal from the current documents.</div>
                <?php endif; ?>
            </section>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var regionSelect = document.querySelector('select[name="region"]');
            var sourceContainer = document.getElementById('source-files-container');
            var sourceFilesByRegion = <?php echo json_encode($sourceFilesByRegion, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            var allSourceFiles = <?php echo json_encode($allSourceFiles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

            function renderSourceFiles(regionKey) {
                var files = regionKey ? (sourceFilesByRegion[regionKey] || []) : allSourceFiles;
                if (!files || files.length === 0) {
                    sourceContainer.innerHTML = '<div class="ai-empty">No files are available in this region yet.</div>';
                    return;
                }

                var limited = files.slice(0, 8);
                var html = '<ul>' + limited.map(function (doc) {
                    return '<li>' + (doc.name || 'Untitled file') + ' · ' + (doc.region_label || 'Unknown region') + '</li>';
                }).join('') + '</ul>';
                sourceContainer.innerHTML = html;
            }

            if (regionSelect) {
                renderSourceFiles(regionSelect.value);
                regionSelect.addEventListener('change', function () {
                    renderSourceFiles(this.value);
                });
            }

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

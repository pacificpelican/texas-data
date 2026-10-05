<?php
require_once __DIR__ . '/includes/app.php';
require_login();

$user = current_user();
$llmConfig = get_llm_config();
$storageStatus = get_storage_mode_status();
$uploadsStatus = get_uploads_dir_status();
$recentRuns = array_slice(shakespeare_history_for_user($user), 0, 5);

const SHX_EXCERPT_WORD_LIMIT = 1000;
const SHX_EXCERPT_MIN_WORDS = 600;

if (isset($_GET['excerpt'])) {
    header('Content-Type: application/json');
    echo json_encode(shakespeare_excerpt((int) ($_GET['start'] ?? 0), SHX_EXCERPT_WORD_LIMIT, SHX_EXCERPT_MIN_WORDS));
    exit;
}

$excerpt = trim((string) ($_POST['excerpt'] ?? ''));
$lengthChoice = (string) ($_POST['length'] ?? 'same');
if (!in_array($lengthChoice, ['same', 'onehalf', 'double'], true)) {
    $lengthChoice = 'same';
}

$shxReply = '';
$shxError = '';
$sourceWordCount = shakespeare_source_word_count();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($excerpt === '') {
        $shxError = 'Paste a chunk of Shake-speare text to continue from.';
    } elseif ($sourceWordCount === 0) {
        $shxError = 'The source text (assets/Hamlet.md) could not be found.';
    } else {
        $words = preg_split('/\s+/u', $excerpt) ?: [];
        if (count($words) > SHX_EXCERPT_WORD_LIMIT) {
            $excerpt = implode(' ', array_slice($words, 0, SHX_EXCERPT_WORD_LIMIT));
        }

        $targetWords = count(preg_split('/\s+/u', $excerpt) ?: []);
        $targetMap = [
            'same' => [1, 1],
            'onehalf' => [1.5, 1.5],
            'double' => [2, 2],
        ];
        $multiplier = $targetMap[$lengthChoice][0];
        $targetWords = max(50, (int) round($targetWords * $multiplier));

        $prompt = "You are the Shake-speare Prediction Machine. Continue the following passage of Shake-speare " .
            "in the same voice, style, and verse form, picking up exactly where it leaves off. " .
            "Do not summarize or comment on the text; write the continuation only. " .
            "Aim for roughly {$targetWords} words.\n\n---\n\n" . $excerpt;

        $result = ask_local_llm($prompt);
        if ($result['ok']) {
            $shxReply = (string) $result['answer'];
            create_shakespeare_history($user, $excerpt, $prompt, $shxReply, $llmConfig['model']);
            $recentRuns = array_slice(shakespeare_history_for_user($user), 0, 5);
        } else {
            $shxError = (string) $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"<?= theme_attribute($user) ?>>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Shake-speare Prediction Machine</title>
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
    <main class="assistant-page">
        <header class="topbar">
            <div class="brand">
                <a href="vault.php" class="brand-link" aria-label="Open Texas vault overview">
                    <div class="brand-mark"><svg viewBox="0 0 64 48" aria-hidden="true" focusable="false"><path d="M32 12 C25 12 20 10 15 7 C9 4 5 3 3 6 C1 9 5 13 11 15 C16 17 21 18 25 19 L25 23 C25 26 26 29 28 31 L26 44 L38 44 L36 31 C38 29 39 26 39 23 L39 19 C43 18 48 17 53 15 C59 13 63 9 61 6 C59 3 55 4 49 7 C44 10 39 12 32 12 Z"/></svg></div>
                </a>
                <span>Texas Data</span>
            </div>

            <div class="topbar-right">
                <div class="user-chip">
                    <span>Signed in as <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <a href="profile.php">Profile</a>
                    <a href="settings.php">Settings</a>
                    <a href="dashboard.php">Back to map</a>
                    <a href="vault.php">Vault</a>
                    <a href="assistant.php">AI Assistant</a>
                    <a href="llm.php">LLM Chat</a>
                    <a href="logout.php">Log out</a>
                </div>

                <div class="debug-shell">
                    <button type="button" class="debug-toggle" aria-controls="shx-debug-panel" aria-expanded="false">🪲</button>
                    <div id="shx-debug-panel" class="debug-panel" aria-live="polite" hidden>
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
                <h2>Shake-speare Prediction Machine</h2>

                <p class="muted-note" style="margin-top: 0;">Paste a continuous passage of Shake-speare (up to <?= SHX_EXCERPT_WORD_LIMIT ?> words) and the model will continue the story from there. The full text of <em>Hamlet</em> (<?= number_format($sourceWordCount) ?> words) is available in <code>assets/Hamlet.md</code> if you need source material.</p>

                <form method="post">
                    <label>
                        Start at word <span id="shx-start-label">1</span> of <?= number_format($sourceWordCount) ?>
                        <input type="range" id="shx-start" min="0" max="<?= max(0, $sourceWordCount - 1) ?>" step="25" value="0" />
                    </label>
                    <div id="shx-status" class="muted-note" style="margin: 0; font-size: 0.85rem;">Move the dial to pull a passage straight out of Hamlet (up to <?= SHX_EXCERPT_WORD_LIMIT ?> words, ending at a scene break when one is nearby).</div>

                    <label>
                        Source passage
                        <textarea name="excerpt" placeholder="To be, or not to be, that is the question..."><?= htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>

                    <label>
                        Continuation length
                        <select name="length">
                            <option value="same" <?= $lengthChoice === 'same' ? 'selected' : '' ?>>About the same length as the passage</option>
                            <option value="onehalf" <?= $lengthChoice === 'onehalf' ? 'selected' : '' ?>>About 1.5× the passage</option>
                            <option value="double" <?= $lengthChoice === 'double' ? 'selected' : '' ?>>About 2× the passage</option>
                        </select>
                    </label>

                    <button type="submit" class="primary">Continue the text</button>
                </form>

                <div class="assistant-file-list">
                    <h3>Recent predictions</h3>
                    <?php if ($recentRuns === []): ?>
                        <div class="ai-empty">No predictions yet. Every passage and continuation is saved to your profile under Shake-speare predictions.</div>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($recentRuns as $entry): ?>
                                <li><a href="shakespeare-history.php?id=<?= rawurlencode((string) $entry['id']) ?>" class="file-link"><?= htmlspecialchars(shorten_text((string) ($entry['excerpt'] ?? ''), 48), ENT_QUOTES, 'UTF-8') ?></a> &middot; <?= htmlspecialchars(date('M j, g:i A', strtotime((string) ($entry['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </aside>

            <section class="assistant-output">
                <h2>Continuation</h2>

                <?php if ($shxError !== ''): ?>
                    <div class="alert"><?= htmlspecialchars($shxError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php elseif ($shxReply !== ''): ?>
                    <div class="ai-response"><?= htmlspecialchars($shxReply, ENT_QUOTES, 'UTF-8') ?></div>
                <?php else: ?>
                    <div class="ai-empty">Feed the machine a passage and it will continue the text in the same style (<?= htmlspecialchars((string) $llmConfig['model'], ENT_QUOTES, 'UTF-8') ?>). Your runs are saved to your profile.</div>
                <?php endif; ?>
            </section>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var shxSlider = document.getElementById('shx-start');
            var shxStatus = document.getElementById('shx-status');
            var shxLabel = document.getElementById('shx-start-label');
            var excerptField = document.querySelector('textarea[name="excerpt"]');
            var shxTimer = null;

            function loadShxExcerpt(start) {
                shxStatus.textContent = 'Loading passage...';
                fetch('shakespeare.php?excerpt=1&start=' + encodeURIComponent(start))
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (!data || !data.excerpt) {
                            shxStatus.textContent = 'Could not load a passage.';
                            return;
                        }
                        excerptField.value = data.excerpt;
                        var note = data.word_count.toLocaleString() + ' words loaded (words ' + (data.start + 1).toLocaleString() + '\u2013' + data.end.toLocaleString() + ')';
                        if (data.cut_at_boundary) {
                            note += ' \u00b7 ends at a scene break';
                        }
                        shxStatus.textContent = note;
                    })
                    .catch(function () {
                        shxStatus.textContent = 'Could not load a passage.';
                    });
            }

            if (shxSlider && excerptField) {
                shxSlider.addEventListener('input', function () {
                    shxLabel.textContent = (parseInt(this.value, 10) + 1).toLocaleString();
                    clearTimeout(shxTimer);
                    var start = this.value;
                    shxTimer = setTimeout(function () { loadShxExcerpt(start); }, 250);
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

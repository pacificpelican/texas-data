<?php
require_once __DIR__ . '/includes/app.php';

if (current_user()) {
    redirect('dashboard.php');
}

$errors = [];
$googleOAuthEnabled = google_oauth_enabled();
$storageStatus = get_storage_mode_status();
$uploadsStatus = get_uploads_dir_status();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['email_login'])) {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $user = sign_in_with_email($email, $password);
        if ($user) {
            $_SESSION['user'] = $user;
            redirect('dashboard.php');
        }

        $errors[] = 'That email/password combination is not valid.';
    }

    if (isset($_POST['google_login'])) {
        $email = strtolower(trim((string) ($_POST['google_email'] ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['user'] = ensure_google_user($email);
            redirect('dashboard.php');
        }

        $errors[] = 'Enter a valid Google account email to continue.';
    }
}

if (isset($_GET['error'])) {
    $errors[] = trim((string) $_GET['error']);
}
?>
<!DOCTYPE html>
<html lang="en"<?= theme_attribute() ?>>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Texas Data</title>
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
    <main class="auth-shell">
        <section class="auth-grid">
            <div class="hero-panel">
                <div class="brand" style="margin-bottom: 18px;">
                    <div class="brand-mark hero-brand-mark"><svg viewBox="0 0 64 48" aria-hidden="true" focusable="false"><path d="M32 12 C25 12 20 10 15 7 C9 4 5 3 3 6 C1 9 5 13 11 15 C16 17 21 18 25 19 L25 23 C25 26 26 29 28 31 L26 44 L38 44 L36 31 C38 29 39 26 39 23 L39 19 C43 18 48 17 53 15 C59 13 63 9 61 6 C59 3 55 4 49 7 C44 10 39 12 32 12 Z"/></svg></div>
                </div>
                <h1>Texas Data</h1>
                <p>Organize files by Texas region and keep local project materials, maps, and records in one place.</p>
                <ul class="hero-list">
                    <li>Five regional workspaces across Texas</li>
                    <li>Upload, browse, and share files by region</li>
                    <li>Secure email sign-in with optional Google access</li>
                </ul>
            </div>

            <div class="auth-card">
                <h2>Welcome back</h2>
                <p>Sign in to access your Texas region files.</p>

                <?php foreach ($errors as $error): ?>
                    <div class="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endforeach; ?>

                <form method="post" class="form-stack">
                    <label>
                        Email address
                        <input type="email" name="email" placeholder="name@company.com" required />
                    </label>

                    <label>
                        Password
                        <input type="password" name="password" placeholder="Your password" required />
                    </label>

                    <button type="submit" name="email_login" value="1" class="primary">Sign in with email</button>
                </form>

                <p style="margin: 16px 0 0; text-align: center; font-size: 0.95rem;">
                    Need an account? <a href="signup.php">Create one</a>
                </p>

                <div class="debug-shell auth-debug-shell">
                    <button type="button" class="debug-toggle" aria-controls="login-debug-panel" aria-expanded="false">🪲</button>
                    <div id="login-debug-panel" class="debug-panel auth-debug-panel" aria-live="polite" hidden>
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

                <div class="divider">or</div>

                <?php if ($googleOAuthEnabled): ?>
                    <div class="form-stack">
                        <a href="google-login.php" class="google" style="display: inline-block; width: 100%; text-align: center; text-decoration: none;">Continue with Google</a>
                    </div>
                <?php else: ?>
                    <div class="muted-note" style="margin-top: 8px; font-size: 0.9rem; color: var(--muted);">Google sign-in is currently unavailable.</div>
                <?php endif; ?>

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

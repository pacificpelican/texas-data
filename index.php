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
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Texas Regional Vault</title>
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
    <main class="auth-shell">
        <section class="auth-grid">
            <div class="hero-panel">
                <h1>Texas Regional Vault</h1>
                <p>Share files across the state by region. Keep local project plans, maps, and documents organized in one simple place.</p>
                <ul class="hero-list">
                    <li>Five clickable regions across Texas</li>
                    <li>Simple file drive experience for teams</li>
                    <li>Email login and Google account sign in</li>
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

                <div class="debug-panel auth-debug-panel" aria-live="polite">
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

                <div class="divider">or</div>

                <?php if ($googleOAuthEnabled): ?>
                    <div class="form-stack">
                        <a href="google-login.php" class="google" style="display: inline-block; width: 100%; text-align: center; text-decoration: none;">Continue with Google</a>
                    </div>
                <?php else: ?>
                    <div class="muted-note" style="margin-top: 8px; font-size: 0.9rem; color: var(--muted);">Google sign-in is currently unavailable.</div>
                <?php endif; ?>

                <p style="margin-top: 18px; font-size: 0.9rem; color: var(--muted);">Demo account: demo@texasdrive.app / demo123</p>
            </div>
        </section>
    </main>
</body>
</html>

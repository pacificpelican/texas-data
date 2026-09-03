<?php
require_once __DIR__ . '/includes/app.php';

if (current_user()) {
    redirect('dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup_submit'])) {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    } else {
        $result = create_email_account($name, $email, $password);
        if ($result['ok'] ?? false) {
            $_SESSION['user'] = $result['user'];
            redirect('dashboard.php');
        }

        $errors[] = $result['message'] ?? 'Unable to create your account right now.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Create an account</title>
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
    <main class="auth-shell">
        <section class="auth-grid">
            <div class="hero-panel">
                <h1>Join Texas Regional Vault</h1>
                <p>Create your personal Texas file repository and add documents to the region that matches your project.</p>
                <ul class="hero-list">
                    <li>Personal email-password account</li>
                    <li>Access your region files instantly</li>
                    <li>Upload and organize project documents</li>
                </ul>
            </div>

            <div class="auth-card">
                <h2>Create account</h2>
                <p>Sign up with an email and password.</p>

                <?php foreach ($errors as $error): ?>
                    <div class="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endforeach; ?>

                <form method="post" class="form-stack">
                    <label>
                        Full name
                        <input type="text" name="name" placeholder="Your name" required />
                    </label>

                    <label>
                        Email address
                        <input type="email" name="email" placeholder="name@company.com" required />
                    </label>

                    <label>
                        Password
                        <input type="password" name="password" placeholder="At least 6 characters" required />
                    </label>

                    <label>
                        Confirm password
                        <input type="password" name="confirm_password" placeholder="Type it again" required />
                    </label>

                    <button type="submit" name="signup_submit" value="1" class="primary">Create account</button>
                </form>

                <p style="margin-top: 16px; text-align: center; font-size: 0.95rem;">
                    Already have an account? <a href="index.php">Sign in</a>
                </p>
            </div>
        </section>
    </main>
</body>
</html>

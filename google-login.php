<?php
require_once __DIR__ . '/includes/app.php';

if (current_user()) {
    redirect('dashboard.php');
}

if (!google_oauth_enabled()) {
    redirect('index.php?error=' . urlencode('Google OAuth is not configured yet. Add a Google client ID and secret in config.php.'));
}

redirect(build_google_auth_url());

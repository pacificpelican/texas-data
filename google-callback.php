<?php
require_once __DIR__ . '/includes/app.php';

if (current_user()) {
    redirect('dashboard.php');
}

if (!google_oauth_enabled()) {
    redirect('index.php?error=' . urlencode('Google OAuth is not configured yet. Add a Google client ID and secret in config.php.'));
}

$code = trim((string) ($_GET['code'] ?? ''));
if ($code === '') {
    redirect('index.php?error=' . urlencode('Google sign-in was cancelled or returned without a code.'));
}

$state = trim((string) ($_GET['state'] ?? ''));
$expectedState = trim((string) ($_SESSION['google_oauth_state'] ?? ''));
if ($expectedState !== '' && $state !== $expectedState) {
    redirect('index.php?error=' . urlencode('Google sign-in request was rejected. Please try again.'));
}

unset($_SESSION['google_oauth_state']);

$config = get_google_config();
$tokenData = exchange_google_code_for_tokens($code, $config['redirect_uri']);
if (!is_array($tokenData) || empty($tokenData['access_token'])) {
    redirect('index.php?error=' . urlencode('Google sign-in failed while exchanging the authorization code.'));
}

$userInfo = fetch_google_userinfo((string) $tokenData['access_token']);
if (!is_array($userInfo) || empty($userInfo['email'])) {
    redirect('index.php?error=' . urlencode('Google did not return a valid user profile.'));
}

$email = strtolower(trim((string) $userInfo['email']));
if (!$config['allowed_domains'] || !google_email_allowed($email, $config['allowed_domains'])) {
    redirect('index.php?error=' . urlencode('Only Google accounts from the allowed domains can sign in.'));
}

$name = trim((string) ($userInfo['name'] ?? explode('@', $email)[0]));
$_SESSION['user'] = ensure_google_user($email, $name);
redirect('dashboard.php');

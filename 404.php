<?php
require_once __DIR__ . '/includes/app.php';

http_response_code(404);
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en"<?= theme_attribute() ?>>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>404 Not Found</title>
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
    <main class="notfound-page">
        <h1 class="notfound-title">404 Not Found</h1>

        <svg class="notfound-art" viewBox="0 0 640 300" role="img" aria-label="Rustic drawing of the Rio Grande winding through a desert valley">
            <!-- sky sun -->
            <circle cx="512" cy="62" r="34" fill="#d97e3f" opacity="0.9"/>
            <!-- distant mesas -->
            <path d="M0 132 L60 132 L76 96 L150 96 L166 132 L240 132 L260 108 L330 108 L346 132 L640 132 L640 300 L0 300 Z" fill="#b5633c"/>
            <path d="M0 156 L110 156 L130 128 L205 128 L222 156 L330 156 L352 136 L440 136 L458 156 L640 156 L640 300 L0 300 Z" fill="#9c4f30"/>
            <!-- river -->
            <path d="M300 132 C 270 170 340 196 306 226 C 276 252 330 276 302 300 L 380 300 C 400 272 350 250 382 224 C 414 198 348 172 376 132 Z" fill="#3e79a8"/>
            <path d="M318 150 C 302 172 340 190 322 212 C 306 232 344 252 328 276" stroke="#8fc3e0" stroke-width="5" fill="none" stroke-linecap="round" opacity="0.8"/>
            <!-- near banks -->
            <path d="M0 132 L300 132 C 270 170 340 196 306 226 C 276 252 330 276 302 300 L0 300 Z" fill="#c98a52"/>
            <path d="M376 132 L640 132 L640 300 L380 300 C 400 272 350 250 382 224 C 414 198 348 172 376 132 Z" fill="#c98a52"/>
            <!-- scrub: yucca-like tufts -->
            <g stroke="#5d6b3a" stroke-width="4" stroke-linecap="round">
                <path d="M120 220 L120 196 M120 208 L106 192 M120 208 L134 192"/>
                <path d="M500 240 L500 214 M500 226 L486 210 M500 226 L514 210"/>
                <path d="M70 260 L70 240 M70 250 L58 236 M70 250 L82 236"/>
                <path d="M560 190 L560 170 M560 180 L548 166 M560 180 L572 166"/>
            </g>
            <!-- birds -->
            <g stroke="#4a2f22" stroke-width="3" fill="none" stroke-linecap="round">
                <path d="M150 60 Q158 52 166 60 Q174 52 182 60"/>
                <path d="M200 84 Q207 77 214 84 Q221 77 228 84"/>
            </g>
        </svg>

        <p class="notfound-copy">This page has wandered off downriver.</p>

        <p class="notfound-links">
            <?php if ($user): ?>
                <a href="dashboard.php">Back to map</a> &middot; <a href="vault.php">Vault</a> &middot; <a href="profile.php">Profile</a>
            <?php else: ?>
                <a href="index.php">Back to sign in</a>
            <?php endif; ?>
        </p>
    </main>
</body>
</html>

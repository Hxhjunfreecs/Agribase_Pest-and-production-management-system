<?php

require 'includes/config.php';
require 'includes/functions.php';
require 'includes/notifications.php';

if (user()) {
    redirect('dashboard.php');
}

$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $notice = 'If an account uses that email address, a password reset link will be sent shortly.';

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare('SELECT id, full_name, email FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $account = $stmt->fetch();

            if ($account) {
                $recentRequest = $pdo->prepare("
                    SELECT created_at
                    FROM password_reset_tokens
                    WHERE user_id = ?
                      AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                    LIMIT 1
                ");
                $recentRequest->execute([$account['id']]);

                if (!$recentRequest->fetch()) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);
                    $expiresAt = gmdate('Y-m-d H:i:s', time() + 1800);
                    $baseUrl = rtrim(agriBaseNotificationSetting('AGRIBASE_APP_URL'), '/');

                    if ($baseUrl !== '') {
                        $urlParts = parse_url($baseUrl);
                        if (
                            !is_array($urlParts) ||
                            !in_array(strtolower((string) ($urlParts['scheme'] ?? '')), ['http', 'https'], true) ||
                            empty($urlParts['host']) ||
                            isset($urlParts['user']) ||
                            isset($urlParts['pass']) ||
                            isset($urlParts['query']) ||
                            isset($urlParts['fragment']) ||
                            (isset($urlParts['port']) && ($urlParts['port'] < 1 || $urlParts['port'] > 65535))
                        ) {
                            error_log('AgriBase password reset link skipped: AGRIBASE_APP_URL is invalid.');
                            $baseUrl = '';
                        }
                    } else {
                        $host = (string) ($_SERVER['SERVER_NAME'] ?? '');
                        $port = (int) ($_SERVER['SERVER_PORT'] ?? 0);

                        if (
                            preg_match('/\A(?:[a-z0-9.-]+|\[[0-9a-f:]+\])\z/i', $host) &&
                            $port >= 1 &&
                            $port <= 65535
                        ) {
                            $scheme = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off'
                                ? 'https'
                                : 'http';
                            if ($port !== ($scheme === 'https' ? 443 : 80)) {
                                $host .= ':' . $port;
                            }
                            $directory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
                            $basePath = rtrim($directory, '/.');
                            $baseUrl = $scheme . '://' . $host . $basePath;
                        }
                    }

                    if ($baseUrl !== '') {
                        $resetUrl = $baseUrl . '/reset_password.php?token=' . urlencode($token);

                        $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$account['id']]);
                        $insert = $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
                        $insert->execute([$account['id'], $tokenHash, $expiresAt]);

                        sendAgriBaseEmail(
                            $account['email'],
                            $account['full_name'],
                            'Reset your AgriBase password',
                            "We received a request to reset your AgriBase password. Use this link within 30 minutes:\n\n" .
                                $resetUrl .
                                "\n\nIf you did not request this, you can ignore this email."
                        );

                        // Keep the request timestamp so failed deliveries are rate limited too.
                    } else {
                        error_log('AgriBase password reset link skipped: app URL is unavailable.');
                    }
                }
            }
        }
    }
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password · AgriBase</title>
    <script src="assets/theme.js?v=20261001-theme-contrast"></script>
    <link rel="stylesheet" href="assets/style.css?v=20261001-button-motion-4">
</head>
<body class="signin-page">
    <main class="signin-layout">
        <a class="signin-home-link" href="index.php">← Back to AgriBase</a>
        <section class="signin-panel">
            <a class="public-brand signin-brand" href="index.php">
                <span class="brand-mark">A</span>
                <span>AgriBase</span>
            </a>
            <p class="public-kicker">ACCOUNT RECOVERY</p>
            <h1>Reset your password</h1>
            <p class="signin-intro">Enter the email address linked to your account and we’ll send you a reset link.</p>

            <?php if ($notice): ?>
                <div class="flash success"><?= e($notice) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="flash error"><?= e($error) ?></div>
            <?php endif; ?>

            <form class="login-form" method="post">
                <?= csrfField() ?>
                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" required autocomplete="email" placeholder="Enter your email">
                </div>
                <button class="btn signin-submit" type="submit">Send reset link</button>
            </form>

            <p class="signin-register"><a href="login.php">Back to sign in</a></p>
        </section>
        <p class="signin-footnote">Rice and coconut production monitoring · AgriBase</p>
    </main>
    <script src="assets/i18n.js?v=20260929-5"></script>
</body>
</html>
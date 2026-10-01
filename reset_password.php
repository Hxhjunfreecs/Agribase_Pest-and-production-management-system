<?php

require 'includes/config.php';
require 'includes/functions.php';

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$tokenHash = preg_match('/\A[a-f0-9]{64}\z/i', $token) ? hash('sha256', $token) : '';
$error = '';
$success = false;

$findToken = $pdo->prepare("
    SELECT user_id
    FROM password_reset_tokens
    WHERE token_hash = ?
    AND expires_at > UTC_TIMESTAMP()
    LIMIT 1
        FOR UPDATE
");
$findToken->execute([$tokenHash]);
$reset = $tokenHash !== '' ? $findToken->fetch() : false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } elseif (!$reset) {
        $error = 'This reset link is invalid or has expired. Request a new one.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirmation = $_POST['password_confirmation'] ?? '';

        if (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirmation) {
            $error = 'The passwords do not match.';
        } else {
            try {
                $pdo->beginTransaction();
                $findToken->execute([$tokenHash]);
                $reset = $findToken->fetch();

                if (!$reset) {
                    $pdo->rollBack();
                    $error = 'This reset link is invalid or has expired. Request a new one.';
                } else {
                    $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                    $update->execute([password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
                    $delete = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
                    $delete->execute([$reset['user_id']]);
                    $pdo->commit();
                    $success = true;
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('AgriBase password reset failed: ' . $exception->getMessage());
                $error = 'The password could not be updated. Please request a new reset link.';
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
    <title>Choose a New Password · AgriBase</title>
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
            <h1>Choose a new password</h1>

            <?php if ($success): ?>
                <div class="flash success">Your password has been updated. You can now sign in.</div>
                <p class="signin-register"><a href="login.php">Go to sign in</a></p>
            <?php elseif (!$reset): ?>
                <div class="flash error"><?= e($error ?: 'This reset link is invalid or has expired. Request a new one.') ?></div>
                <p class="signin-register"><a href="forgot_password.php">Request another reset link</a></p>
            <?php else: ?>
                <p class="signin-intro">Choose a password with at least 6 characters.</p>
                <?php if ($error): ?>
                    <div class="flash error"><?= e($error) ?></div>
                <?php endif; ?>
                <form class="login-form" method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <div class="field">
                        <label for="password">New password</label>
                        <div class="password-input">
                            <input id="password" name="password" type="password" required minlength="6" autocomplete="new-password">
                            <button class="password-visibility-toggle" type="button" data-password-toggle="password" data-i18n-aria="show_password" data-i18n-title="show_password" aria-label="Show password" title="Show password" aria-pressed="false">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle><path class="password-eye-slash" d="m4 4 16 16"></path></svg>
                            </button>
                        </div>
                    </div>
                    <div class="field">
                        <label for="password_confirmation">Confirm new password</label>
                        <div class="password-input">
                            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="6" autocomplete="new-password">
                            <button class="password-visibility-toggle" type="button" data-password-toggle="password_confirmation" data-i18n-aria="show_password" data-i18n-title="show_password" aria-label="Show password" title="Show password" aria-pressed="false">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle><path class="password-eye-slash" d="m4 4 16 16"></path></svg>
                            </button>
                        </div>
                    </div>
                    <button class="btn signin-submit" type="submit">Update password</button>
                </form>
            <?php endif; ?>
        </section>
        <p class="signin-footnote">Rice and coconut production monitoring · AgriBase</p>
    </main>
    <script src="assets/i18n.js?v=20260929-5"></script>
    <script src="assets/form-controls.js?v=20260929-2"></script>
</body>
</html>
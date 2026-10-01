<?php

require 'includes/config.php';
require 'includes/functions.php';

if (user()) {
    redirect('dashboard.php');
}

$error = '';
$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lockoutUntil = $_SESSION['login_lockout_until'] ?? 0;

    if ($lockoutUntil > time()) {
        $error = 'Too many failed sign-in attempts. Please wait 15 minutes and try again.';
    } elseif (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } else {
        $identity = trim(
            $_POST['identity'] ?? ''
        );

        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE username = ?
               OR email = ?
            LIMIT 1
        ");

        $stmt->execute([
            $identity,
            $identity
        ]);

        $account = $stmt->fetch();

        $validPassword = false;

        if ($account) {
            $validPassword = password_verify($password, $account['password_hash']);

            if (
                !$validPassword &&
                hash('sha256', $password) === $account['password_hash']
            ) {
                $validPassword = true;

                $newHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $update = $pdo->prepare("
                    UPDATE users
                    SET password_hash = ?
                    WHERE id = ?
                ");

                $update->execute([
                    $newHash,
                    $account['id']
                ]);
            }
        }

        if ($validPassword) {
            $_SESSION['login_attempts'] = 0;
            unset($_SESSION['login_lockout_until']);
            session_regenerate_id(true);

            $_SESSION['user'] = [
                'id' => $account['id'],
                'full_name' => $account['full_name'],
                'role' => $account['role'],
                'username' => $account['username']
            ];

            redirect('dashboard.php');
        }

        $attempts = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['login_attempts'] = $attempts;

        if ($attempts >= 5) {
            $_SESSION['login_lockout_until'] = time() + 900;
            $_SESSION['login_attempts'] = 0;
            $error = 'Too many failed sign-in attempts. Please wait 15 minutes and try again.';
        } else {
            $error = 'Invalid username/email or password.';
        }
    }
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In · AgriBase</title>
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

            <p class="public-kicker">AGRICULTURAL MONITORING</p>
            <h1>Welcome back</h1>
            <p class="signin-intro">Sign in to monitor farms, review crop conditions, and access agricultural advisories.</p>

            <?php if ($flash): ?>
                <div class="flash <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="flash error"><?= e($error) ?></div>
            <?php endif; ?>

            <form class="login-form" method="post">
                <?= csrfField() ?>
                <div class="field">
                    <label for="identity">Username or email</label>
                    <input id="identity" name="identity" required autocomplete="username" placeholder="Enter username or email">
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="password-input">
                        <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password">
                        <button class="password-visibility-toggle" type="button" data-password-toggle="password" data-i18n-aria="show_password" data-i18n-title="show_password" aria-label="Show password" title="Show password" aria-pressed="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle><path class="password-eye-slash" d="m4 4 16 16"></path></svg>
                        </button>
                    </div>
                </div>

                <button class="btn signin-submit" type="submit">Sign in</button>
            </form>

            <p class="signin-register"><a href="forgot_password.php">Forgot your password?</a></p>
            <p class="signin-register">New farmer? <a href="register.php">Create an account</a></p>

            <details class="demo-access">
                <summary>Demo access</summary>
                <p>Administrator: <strong>admin / admin123</strong><br>Farmer: <strong>juan / farmer123</strong></p>
            </details>
        </section>

        <p class="signin-footnote">Rice and coconut production monitoring · AgriBase</p>
    </main>
    <script src="assets/i18n.js?v=20260929-5"></script>
    <script src="assets/form-controls.js?v=20260929-2"></script>
</body>
</html>

<?php

require 'includes/config.php';
require 'includes/functions.php';
require 'includes/notifications.php';

if (user()) {
    redirect('dashboard.php');
}

$error = '';
$pendingKey = 'pending_farmer_registration';
$pendingRegistration = $_SESSION[$pendingKey] ?? null;
$noticeType = 'error';

if (is_array($pendingRegistration) && ($pendingRegistration['verification_method'] ?? 'email') !== 'email') {
    $pendingRegistration['verification_method'] = 'email';
    $pendingRegistration['delivery_pending'] = true;
    $_SESSION[$pendingKey] = $pendingRegistration;
}

if (
    is_array($pendingRegistration) &&
    empty($pendingRegistration['delivery_pending']) &&
    ($pendingRegistration['expires_at'] ?? 0) < time()
) {
    $pendingRegistration['delivery_pending'] = true;
    unset($pendingRegistration['verification_hash']);
    $_SESSION[$pendingKey] = $pendingRegistration;
}

$allowVerificationSend = static function (): bool {
    $sentAt = array_values(array_filter(
        $_SESSION['registration_code_sends'] ?? [],
        static fn($timestamp) => $timestamp > time() - 3600
    ));

    if (
        count($sentAt) >= 5 ||
        ($sentAt && time() - end($sentAt) < 60)
    ) {
        return false;
    }

    $sentAt[] = time();
    $_SESSION['registration_code_sends'] = $sentAt;
    return true;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? 'begin';

        if ($action === 'edit_details' || $action === 'cancel_verification') {
            $pendingRegistration = $_SESSION[$pendingKey] ?? null;

            if (is_array($pendingRegistration)) {
                $pendingRegistration['editing'] = true;
                $_SESSION[$pendingKey] = $pendingRegistration;
            }

            redirect('register.php');
        } elseif ($action === 'cancel_edit') {
            $pendingRegistration = $_SESSION[$pendingKey] ?? null;

            if (is_array($pendingRegistration)) {
                unset($pendingRegistration['editing']);
                $_SESSION[$pendingKey] = $pendingRegistration;
            }

            redirect('register.php');
        } elseif ($action === 'verify_code') {
            $pendingRegistration = $_SESSION[$pendingKey] ?? null;
            $submittedCode = trim($_POST['verification_code'] ?? '');

            if (!is_array($pendingRegistration)) {
                $error = 'Your verification session expired. Please register again.';
            } elseif (!empty($pendingRegistration['delivery_pending'])) {
                $error = 'A code has not been sent yet. Check the provider configuration and resend the code.';
            } elseif (($pendingRegistration['expires_at'] ?? 0) < time()) {
                $pendingRegistration['delivery_pending'] = true;
                unset($pendingRegistration['verification_hash']);
                $_SESSION[$pendingKey] = $pendingRegistration;
                $error = 'Your verification code expired. Request a new code to continue.';
            } elseif (($pendingRegistration['attempts'] ?? 0) >= 5) {
                $pendingRegistration['delivery_pending'] = true;
                unset($pendingRegistration['verification_hash']);
                $_SESSION[$pendingKey] = $pendingRegistration;
                $error = 'Too many incorrect codes. Request a new code to continue.';
            } elseif (password_verify($submittedCode, $pendingRegistration['verification_hash'])) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO users (
                            full_name,
                            username,
                            email,
                            password_hash,
                            role,
                            phone,
                            address
                        )
                        VALUES (?, ?, ?, ?, 'farmer', ?, ?)
                    ");

                    $stmt->execute([
                        $pendingRegistration['full_name'],
                        $pendingRegistration['username'],
                        $pendingRegistration['email'],
                        $pendingRegistration['password_hash'],
                        $pendingRegistration['phone'],
                        $pendingRegistration['address']
                    ]);

                    unset($_SESSION[$pendingKey]);
                    session_regenerate_id(true);
                    flash(
                        'success',
                        'Farmer account verified and created. You may now sign in.'
                    );
                    redirect('login.php');
                } catch (PDOException $exception) {
                    $pendingRegistration['editing'] = true;
                    $_SESSION[$pendingKey] = $pendingRegistration;
                    $error = 'Username or email is already registered. Edit your details to continue.';
                }
            } else {
                $_SESSION[$pendingKey]['attempts']++;
                $pendingRegistration['attempts'] = $_SESSION[$pendingKey]['attempts'];
                $error = 'That verification code is not correct.';
            }
        } elseif ($action === 'resend_code') {
            $pendingRegistration = $_SESSION[$pendingKey] ?? null;

            if (is_array($pendingRegistration) && ($pendingRegistration['verification_method'] ?? 'email') !== 'email') {
                $pendingRegistration['verification_method'] = 'email';
                $pendingRegistration['delivery_pending'] = true;
                $_SESSION[$pendingKey] = $pendingRegistration;
            }

            if (!is_array($pendingRegistration)) {
                $error = 'Your verification session expired. Please register again.';
            } elseif (time() - ($pendingRegistration['last_sent_at'] ?? 0) < 60) {
                $error = 'Please wait one minute before requesting another code.';
            } elseif (!$allowVerificationSend()) {
                $error = 'The verification-code send limit was reached. Try again later.';
            } else {
                $code = (string) random_int(100000, 999999);
                $pendingRegistration['verification_hash'] = password_hash($code, PASSWORD_DEFAULT);
                $pendingRegistration['expires_at'] = time() + 600;
                $pendingRegistration['last_sent_at'] = time();
                $pendingRegistration['attempts'] = 0;
                $_SESSION[$pendingKey] = $pendingRegistration;

                if (sendAgriBaseVerificationCode($pendingRegistration, $code)) {
                    $pendingRegistration['delivery_pending'] = false;
                    $_SESSION[$pendingKey] = $pendingRegistration;
                    $noticeType = 'success';
                    $error = 'A new verification code was sent.';
                } else {
                    $pendingRegistration['delivery_pending'] = true;
                    $_SESSION[$pendingKey] = $pendingRegistration;
                    $pendingRegistration = $_SESSION[$pendingKey];
                    $error = 'The verification code could not be sent. Check the notification provider configuration.';
                }
            }
        } elseif ($action === 'update_pending_details') {
            $pendingRegistration = $_SESSION[$pendingKey] ?? null;
            $name = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if (!is_array($pendingRegistration)) {
                $error = 'Your verification session expired. Please register again.';
            } elseif ($name === '' || $username === '' || $email === '') {
                $error = 'Please complete all required fields.';
            } elseif ($password !== '' && strlen($password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                $existing = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE username = ? OR email = ?
                    LIMIT 1
                ");
                $existing->execute([$username, $email]);

                if ($existing->fetchColumn()) {
                    $error = 'Username or email is already registered.';
                } elseif (!$allowVerificationSend()) {
                    $error = 'The verification-code send limit was reached. Try again later.';
                } else {
                    $pendingRegistration['full_name'] = $name;
                    $pendingRegistration['username'] = $username;
                    $pendingRegistration['email'] = $email;
                    $pendingRegistration['phone'] = $phone;
                    $pendingRegistration['address'] = $address;

                    if ($password !== '') {
                        $pendingRegistration['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                    }

                    $code = (string) random_int(100000, 999999);
                    $pendingRegistration['verification_hash'] = password_hash($code, PASSWORD_DEFAULT);
                    $pendingRegistration['expires_at'] = time() + 600;
                    $pendingRegistration['last_sent_at'] = time();
                    $pendingRegistration['attempts'] = 0;
                    $pendingRegistration['delivery_pending'] = true;
                    unset($pendingRegistration['editing']);
                    $_SESSION[$pendingKey] = $pendingRegistration;

                    if (sendAgriBaseVerificationCode($pendingRegistration, $code)) {
                        $pendingRegistration['delivery_pending'] = false;
                        $_SESSION[$pendingKey] = $pendingRegistration;
                        $noticeType = 'success';
                        $error = 'Details updated. A verification code was sent to your email.';
                    } else {
                        $error = 'Your details were updated temporarily, but no code was sent. Check the provider setup and resend the code.';
                    }
                }
            }
        } else {
            $name = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $verificationMethod = 'email';

            if (
                $name === '' ||
                $username === '' ||
                $email === '' ||
                $password === ''
            ) {
                $error = 'Please complete all required fields.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                $existing = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE username = ? OR email = ?
                    LIMIT 1
                ");
                $existing->execute([$username, $email]);

                if ($existing->fetchColumn()) {
                    $error = 'Username or email is already registered.';
                } elseif (!$allowVerificationSend()) {
                    $error = 'The verification-code send limit was reached. Try again later.';
                } else {
                    $code = (string) random_int(100000, 999999);
                    $pendingRegistration = [
                        'full_name' => $name,
                        'username' => $username,
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'phone' => $phone,
                        'address' => $address,
                        'verification_method' => $verificationMethod,
                        'verification_hash' => password_hash($code, PASSWORD_DEFAULT),
                        'expires_at' => time() + 600,
                        'last_sent_at' => time(),
                        'attempts' => 0,
                        'delivery_pending' => false
                    ];

                    if (sendAgriBaseVerificationCode($pendingRegistration, $code)) {
                        $_SESSION[$pendingKey] = $pendingRegistration;
                        $noticeType = 'success';
                        $error = 'A verification code was sent. Enter it below to create your account.';
                    } else {
                        $pendingRegistration['delivery_pending'] = true;
                        $_SESSION[$pendingKey] = $pendingRegistration;
                        $error = 'Your signup details are saved temporarily, but no code was sent. Check the provider setup and use Resend code.';
                    }
                }
            }
        }
    }
}

$pendingRegistration = $_SESSION[$pendingKey] ?? null;
$editingPendingRegistration = is_array($pendingRegistration)
    && !empty($pendingRegistration['editing']);
$registrationValues = $editingPendingRegistration
    ? $pendingRegistration
    : $_POST;

if (
    $editingPendingRegistration &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'update_pending_details'
) {
    $registrationValues = array_merge($pendingRegistration, $_POST);
}

?>
<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Farmer Registration · AgriBase
    </title>

    <script src="assets/theme.js?v=20261001-theme-contrast"></script>
    <link
        rel="stylesheet"
        href="assets/style.css?v=20261001-button-motion-4"
    >

</head>

<body>

    <div class="login-page">

        <section class="login-card">

            <div class="brand">

                <span class="brand-mark">
                    A
                </span>

                <span>
                    AgriBase
                </span>

            </div>

            <h1>
                Farmer Registration
            </h1>

            <p class="subtitle">
                Create your farmer account before registering your farms. A verification code will be sent to your email.
            </p>


            <?php if ($error): ?>

                <div class="flash <?= e($noticeType) ?>">
                    <?= e($error) ?>
                </div>

            <?php endif; ?>


            <?php if ($pendingRegistration && !$editingPendingRegistration): ?>

                <div class="registration-verification">
                    <h2>Verify your contact</h2>
                    <?php if (!empty($pendingRegistration['delivery_pending'])): ?>
                        <p>Your signup details are saved temporarily. A code has not been sent yet; check the notification setup, then retry below.</p>
                    <?php else: ?>
                    <p>
                        Enter the six-digit code sent to <?= e($pendingRegistration['email']) ?>.
                        The code expires in 10 minutes.
                    </p>

                    <?php endif; ?>

                    <form class="login-form" method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="verify_code">
                        <div class="field">
                            <label for="verification_code">Verification code</label>
                            <input
                                id="verification_code"
                                name="verification_code"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                required
                                <?= !empty($pendingRegistration['delivery_pending']) ? 'disabled' : 'autofocus' ?>
                            >
                        </div>
                        <button class="btn" type="submit" <?= !empty($pendingRegistration['delivery_pending']) ? 'disabled' : '' ?>>Verify and create account</button>
                    </form>

                    <div class="verification-actions">
                        <form method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="resend_code">
                            <button class="btn secondary small" type="submit">Resend code</button>
                        </form>
                        <form method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="edit_details">
                            <button class="btn secondary small" type="submit">Change details</button>
                        </form>
                    </div>
                </div>

            <?php else: ?>

            <form
                class="login-form"
                method="post"
            >

                <?= csrfField() ?>
                <input type="hidden" name="action" value="<?= $editingPendingRegistration ? 'update_pending_details' : 'begin' ?>">

                <div class="field">

                    <label for="full_name">
                        Full Name
                    </label>

                    <input
                        id="full_name"
                        name="full_name"
                        required
                        value="<?= e($registrationValues['full_name'] ?? '') ?>"
                    >

                </div>


                <div class="field">

                    <label for="username">
                        Username
                    </label>

                    <input
                        id="username"
                        name="username"
                        required
                        value="<?= e($registrationValues['username'] ?? '') ?>"
                    >

                </div>


                <div class="field">

                    <label for="email">
                        Email
                    </label>

                    <input
                        id="email"
                        name="email"
                        type="email"
                        required
                        value="<?= e($registrationValues['email'] ?? '') ?>"
                    >

                </div>


                <div class="field">

                    <label for="password">
                        <?= $editingPendingRegistration ? 'New Password (leave blank to keep current password)' : 'Password' ?>
                    </label>

                    <div class="password-input">
                        <input
                            id="password"
                            name="password"
                            type="password"
                            minlength="6"
                            autocomplete="new-password"
                            <?= $editingPendingRegistration ? '' : 'required' ?>
                        >
                        <button class="password-visibility-toggle" type="button" data-password-toggle="password" data-i18n-aria="show_password" data-i18n-title="show_password" aria-label="Show password" title="Show password" aria-pressed="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle><path class="password-eye-slash" d="m4 4 16 16"></path></svg>
                        </button>
                    </div>

                </div>


                <div class="field">

                    <label for="phone">
                        Phone
                    </label>

                    <input
                        id="phone"
                        name="phone"
                        value="<?= e($registrationValues['phone'] ?? '') ?>"
                    >

                </div>


                <div class="field">

                    <label for="address">
                        Barangay / Address
                    </label>

                    <input
                        id="address"
                        name="address"
                        value="<?= e($registrationValues['address'] ?? '') ?>"
                    >

                </div>

                <button
                    class="btn"
                    type="submit"
                >
                    <?= $editingPendingRegistration ? 'Update Details and Send a New Code' : 'Create Farmer Account' ?>
                </button>

            </form>

            <?php if ($editingPendingRegistration): ?>
                <form method="post" class="verification-actions">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="cancel_edit">
                    <button class="btn secondary small" type="submit">Back to verification</button>
                </form>
            <?php endif; ?>

            <?php endif; ?>


            <div class="login-footer">

                <a href="login.php">
                    Back to Login
                </a>

            </div>

        </section>

    </div>

    <script src="assets/i18n.js?v=20260929-5"></script>
    <script src="assets/form-controls.js?v=20260929-2"></script>
</body>

</html>

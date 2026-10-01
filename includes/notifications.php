<?php

require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

$localNotificationConfigPath = __DIR__ . '/notifications.local.php';
$localNotificationConfig = is_file($localNotificationConfigPath)
    ? require $localNotificationConfigPath
    : [];
if (!is_array($localNotificationConfig)) {
    $localNotificationConfig = [];
}

function agriBaseNotificationSetting(string $name): string
{
    global $localNotificationConfig;

    $environmentValue = getenv($name);
    if ($environmentValue !== false && $environmentValue !== '') {
        return (string) $environmentValue;
    }

    return trim((string) ($localNotificationConfig[$name] ?? ''));
}

function sendAgriBaseEmail(string $email, string $name, string $subject, string $message): bool
{
    if (
        $email === '' ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        !agriBaseNotificationSetting('AGRIBASE_SMTP_HOST')
    ) {
        error_log('AgriBase email notification skipped: recipient or SMTP host is missing.');
        return false;
    }

    try {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = agriBaseNotificationSetting('AGRIBASE_SMTP_HOST');
        $mailer->Port = (int) (agriBaseNotificationSetting('AGRIBASE_SMTP_PORT') ?: 587);
        $mailer->SMTPAuth = agriBaseNotificationSetting('AGRIBASE_SMTP_USERNAME') !== '';
        $mailer->Username = agriBaseNotificationSetting('AGRIBASE_SMTP_USERNAME');
        $mailer->Password = agriBaseNotificationSetting('AGRIBASE_SMTP_PASSWORD');
        $mailer->Timeout = 10;
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;

        $encryption = strtolower(agriBaseNotificationSetting('AGRIBASE_SMTP_ENCRYPTION') ?: 'tls');
        if ($encryption === 'ssl') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $caInfo = agriBaseNotificationSetting('AGRIBASE_CAINFO');
        if ($caInfo !== '') {
            $mailer->SMTPOptions = [
                'ssl' => [
                    'cafile' => $caInfo,
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false
                ]
            ];
        }

        $fromEmail = agriBaseNotificationSetting('AGRIBASE_SMTP_FROM_EMAIL') ?: $mailer->Username;
        $fromName = agriBaseNotificationSetting('AGRIBASE_SMTP_FROM_NAME') ?: 'AgriBase';
        $mailer->setFrom($fromEmail, $fromName);
        $mailer->addAddress($email, $name);
        $mailer->Subject = $subject;
        $mailer->Body = $message;
        return $mailer->send();
    } catch (Throwable $exception) {
        error_log('AgriBase email notification failed: ' . $exception->getMessage());
        return false;
    }
}

function sendAgriBaseNotification(
    array $recipient,
    string $subject,
    string $message
): void
{
    sendAgriBaseEmail(
        trim((string) ($recipient['email'] ?? '')),
        (string) ($recipient['full_name'] ?? ''),
        $subject,
        $message
    );
}

function sendAgriBaseVerificationCode(array $recipient, string $code): bool
{
    $message = 'Your AgriBase verification code is ' . $code . '. It expires in 10 minutes.';

    return sendAgriBaseEmail(
        trim((string) ($recipient['email'] ?? '')),
        (string) ($recipient['full_name'] ?? ''),
        'Confirm your AgriBase registration',
        $message
    );
}

function notifyAgriBaseAdmins(PDO $pdo, string $subject, string $message): void
{
    $stmt = $pdo->query("
        SELECT full_name, email, phone
        FROM users
        WHERE role = 'admin'
    ");

    foreach ($stmt as $recipient) {
        sendAgriBaseNotification($recipient, $subject, $message);
    }
}

function notifyAgriBaseFarmer(PDO $pdo, int $farmerId, string $subject, string $message): void
{
    $stmt = $pdo->prepare("
        SELECT full_name, email, phone
        FROM users
        WHERE id = ?
          AND role = 'farmer'
        LIMIT 1
    ");
    $stmt->execute([$farmerId]);
    $recipient = $stmt->fetch();

    if ($recipient) {
        sendAgriBaseNotification($recipient, $subject, $message);
    }
}

function notifyAgriBaseAll(
    PDO $pdo,
    string $subject,
    string $message
): void
{
    $stmt = $pdo->query("
        SELECT full_name, email, phone
        FROM users
    ");

    foreach ($stmt as $recipient) {
        sendAgriBaseNotification($recipient, $subject, $message);
    }
}

function notifyAgriBaseHighPestActivity(PDO $pdo, int $incidentId): void
{
    try {
        $incidentQuery = $pdo->prepare("
            SELECT f.barangay, p.crop
            FROM incidents i
            JOIN farms f ON f.id = i.farm_id
            JOIN pest_assessments p ON p.id = i.assessment_id
            WHERE i.id = ?
            LIMIT 1
        ");
        $incidentQuery->execute([$incidentId]);
        $incident = $incidentQuery->fetch();

        if (!$incident || trim((string) $incident['barangay']) === '') {
            return;
        }

        $countQuery = $pdo->prepare("
            SELECT COUNT(*)
            FROM incidents i
            JOIN farms f ON f.id = i.farm_id
            WHERE f.barangay = ?
              AND i.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $countQuery->execute([$incident['barangay']]);
        $caseCount = (int) $countQuery->fetchColumn();

        if ($caseCount !== 5) {
            return;
        }

        notifyAgriBaseAll(
            $pdo,
            'High pest activity reported in ' . $incident['barangay'],
            'AgriBase recorded five pest cases in ' . $incident['barangay'] .
                ' during the last seven days. The most recent report concerns ' .
                $incident['crop'] . '. Please review the area and follow up as appropriate.'
        );
    } catch (Throwable $exception) {
        error_log('AgriBase pest activity alert failed: ' . $exception->getMessage());
    }
}

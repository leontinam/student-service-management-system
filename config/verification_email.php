<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

function verification_base_url(): ?string
{
    // Production verification links require HTTPS; plain HTTP is allowed only locally.
    $configuredUrl = trim((string) ($_ENV['APP_URL'] ?? ''));
    $isLocalRequest = !empty($GLOBALS['isLocalRequest']);

    if ($configuredUrl === '') {
        return $isLocalRequest ? 'http://localhost/student-service-system' : null;
    }

    $parts = parse_url($configuredUrl);
    $hasForbiddenParts = array_intersect(['user', 'pass', 'query', 'fragment'], array_keys($parts ?: [])) !== [];
    $valid = filter_var($configuredUrl, FILTER_VALIDATE_URL) !== false
        && is_array($parts)
        && isset($parts['scheme'], $parts['host'])
        && !$hasForbiddenParts
        && ($parts['scheme'] === 'https' || ($isLocalRequest && $parts['scheme'] === 'http'));

    return $valid ? rtrim($configuredUrl, '/') : null;
}

function verification_smtp_is_configured(): bool
{
    return trim((string) ($_ENV['SMTP_HOST'] ?? '')) !== ''
        && (int) ($_ENV['SMTP_PORT'] ?? 0) > 0
        && trim((string) ($_ENV['SMTP_USER'] ?? '')) !== ''
        && (string) ($_ENV['SMTP_PASS'] ?? '') !== ''
        && trim((string) ($_ENV['SENDER_EMAIL'] ?? '')) !== '';
}

function send_verification_email(string $email, string $fullName, string $role, string $token, string $baseUrl): void
{
    if (!verification_smtp_is_configured()) {
        throw new RuntimeException('Verification email is not configured.');
    }

    // Configure PHPMailer to deliver the one-time account activation link over SMTP.
    $mailer = new PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $_ENV['SMTP_HOST'];
    $mailer->SMTPAuth = true;
    $mailer->Username = $_ENV['SMTP_USER'];
    $mailer->Password = $_ENV['SMTP_PASS'];
    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->Port = (int) $_ENV['SMTP_PORT'];
    $mailer->CharSet = 'UTF-8';
    $mailer->setFrom($_ENV['SENDER_EMAIL'], 'Student Services');
    $mailer->addAddress($email, $fullName);
    $mailer->isHTML(true);

    $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
    $safeRole = htmlspecialchars($role === 'student' ? 'student' : 'service provider', ENT_QUOTES, 'UTF-8');
    $verificationUrl = $baseUrl . '/verify.php?token=' . rawurlencode($token);
    $safeVerificationUrl = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');

    $mailer->Subject = 'Verify your SSMS email address';
    $mailer->Body = '<!doctype html><html><body style="margin:0;padding:0;background-color:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:#f4f7fb;"><tr><td align="center" style="padding:32px 16px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background-color:#ffffff;border-radius:16px;overflow:hidden;">'
        . '<tr><td align="center" style="padding:32px 32px 12px;"><table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr>'
        . '<td style="width:48px;height:48px;border-radius:50%;background-color:#d4a017;color:#1f2937;text-align:center;font-size:23px;font-weight:700;line-height:48px;">S</td>'
        . '<td style="padding-left:12px;color:#075536;font-size:22px;font-weight:700;letter-spacing:0.5px;">SSMS</td>'
        . '</tr></table></td></tr><tr><td style="padding:12px 32px 32px;text-align:center;">'
        . '<h1 style="margin:0 0 16px;font-size:24px;line-height:32px;color:#111827;">Hi ' . $safeName . '!</h1>'
        . '<p style="margin:0 0 10px;font-size:16px;line-height:24px;color:#4b5563;">Thank you for signing up with SSMS!</p>'
        . '<p style="margin:0 0 24px;font-size:16px;line-height:24px;color:#4b5563;">Your ' . $safeRole . ' account has been created. Please confirm your email address by clicking the button below.</p>'
        . '<table role="presentation" align="center" cellspacing="0" cellpadding="0" border="0"><tr><td align="center" bgcolor="#075536" style="border-radius:8px;">'
        . '<a href="' . $safeVerificationUrl . '" style="display:inline-block;padding:14px 24px;color:#ffffff;font-size:16px;font-weight:700;line-height:20px;text-decoration:none;border-radius:8px;">Confirm email address</a>'
        . '</td></tr></table><p style="margin:28px 0 0;font-size:16px;line-height:24px;color:#4b5563;">Thanks,<br>The SSMS Team</p>'
        . '</td></tr><tr><td style="padding:20px 32px;background-color:#f9fafb;text-align:center;border-top:1px solid #e5e7eb;">'
        . '<p style="margin:0 0 10px;font-size:12px;line-height:18px;color:#6b7280;">If you didn&#39;t create this account, please ignore this email.</p>'
        . '<p style="margin:0;font-size:12px;line-height:18px;color:#9ca3af;">© 2026 SSMS. All rights reserved.</p>'
        . '</td></tr></table></td></tr></table></body></html>';
    $mailer->AltBody = 'Hi ' . $fullName . '! Thank you for signing up with SSMS. Confirm your email address: '
        . $verificationUrl . '. If you did not create this account, please ignore this email.';
    $mailer->send();
}

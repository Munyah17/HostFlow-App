<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Services\Settings;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/** SMTP email delivery using PHPMailer. Settings are configured by the Super Admin. */
final class EmailChannel implements NotificationChannelInterface
{
    public function id(): string
    {
        return 'email';
    }

    public function label(): string
    {
        return 'Email (SMTP)';
    }

    public function isEnabled(): bool
    {
        return Settings::bool('mail_enabled') && Settings::get('smtp_host') !== '' && Settings::get('mail_from_address') !== '';
    }

    public function recipientFor(array $customer): ?string
    {
        return filter_var($customer['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $customer['email'] : null;
    }

    public function send(array $notification): void
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Email delivery is not configured (Settings → Email/SMTP).');
        }
        $mail = self::mailer();
        $mail->addAddress((string) $notification['recipient']);
        $mail->Subject = (string) $notification['subject'];
        $mail->isHTML(true);
        $mail->Body = (string) $notification['body'];
        $mail->AltBody = (string) ($notification['text'] ?? trim(html_entity_decode(strip_tags((string) $notification['body']))));
        try {
            $mail->send();
        } catch (MailException $e) {
            throw new \RuntimeException('SMTP error: ' . $mail->ErrorInfo, 0, $e);
        }
    }

    public static function mailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) Settings::get('smtp_host');
        $mail->Port = (int) Settings::get('smtp_port', 587);
        $enc = (string) Settings::get('smtp_encryption', 'tls');
        $mail->SMTPSecure = match ($enc) {
            'ssl' => PHPMailer::ENCRYPTION_SMTPS,
            'tls' => PHPMailer::ENCRYPTION_STARTTLS,
            default => '',
        };
        $mail->SMTPAutoTLS = $enc !== 'none';
        $user = (string) Settings::get('smtp_username');
        if ($user !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = (string) Settings::get('smtp_password');
        }
        $mail->Timeout = 20;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom((string) Settings::get('mail_from_address'), (string) Settings::get('mail_from_name', Settings::get('business_name')));
        $reply = (string) Settings::get('business_email');
        if (filter_var($reply, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($reply);
        }
        return $mail;
    }
}

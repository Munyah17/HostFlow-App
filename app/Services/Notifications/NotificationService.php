<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Core\Logger;
use App\Core\View;
use App\Services\Settings;

/**
 * Builds notifications from templates and queues them per channel.
 * A dedupe key guarantees the same reminder is never queued twice.
 */
final class NotificationService
{
    /** @var NotificationChannelInterface[] */
    private static array $channels = [];
    private const MAX_ATTEMPTS = 5;

    public const TEMPLATES = [
        'welcome' => 'Welcome to {business}',
        'password_reset' => 'Reset your {business} password',
        'invoice_created' => 'Invoice {invoice_number} from {business}',
        'payment_received' => 'Payment received — invoice {invoice_number}',
        'service_activated' => 'Your hosting for {domain} is active',
        'renewal_reminder' => 'Renewal reminder: {domain} is due on {due_date}',
        'invoice_due' => 'Invoice {invoice_number} is due today',
        'overdue_reminder' => 'Overdue: invoice {invoice_number}',
        'service_suspended' => 'Service suspended: {domain}',
        'service_reactivated' => 'Service reactivated: {domain}',
        'domain_registered' => 'Your domain {domain} is now active',
        'domain_renewed' => 'Domain renewed: {domain} until {expiry_date}',
        'domain_renewal_reminder' => 'Domain renewal: {domain} expires on {expiry_date}',
        'domain_expired' => 'Domain expired: {domain}',
        'ticket_reply' => '[{ticket_number}] New reply: {ticket_subject}',
        'staff_new_ticket' => '[{ticket_number}] New ticket: {ticket_subject}',
        'staff_alert' => 'HostFlow alert: {alert_title}',
    ];

    public static function channels(): array
    {
        if (!isset(self::$channels['email'])) {
            self::$channels = ['email' => new EmailChannel()] + self::$channels;
        }
        return self::$channels;
    }

    public static function registerChannel(NotificationChannelInterface $channel): void
    {
        if ($channel->id() === 'email') {
            throw new \InvalidArgumentException('The email channel is provided by HostFlow core.');
        }
        self::$channels[$channel->id()] = $channel;
    }

    public static function resetChannels(): void
    {
        self::$channels = [];
    }

    /**
     * Queue a notification for a customer on all enabled channels.
     * Returns number of queued messages (0 if duplicate).
     */
    public static function notifyCustomer(int $customerId, string $template, array $data = [], ?string $dedupeKey = null, bool $sendNow = false): int
    {
        $customer = db()->fetch('SELECT * FROM customers WHERE id = ?', [$customerId]);
        if (!$customer) {
            return 0;
        }
        $data += ['customer_name' => $customer['full_name'], 'customer' => $customer];
        $queued = 0;
        foreach (self::channels() as $channel) {
            if ($channel->id() !== 'email' && !$channel->isEnabled()) {
                continue;
            }
            $recipient = $channel->recipientFor($customer);
            if (!$recipient) {
                continue;
            }
            $id = self::queue($channel->id(), $recipient, $template, $data, $dedupeKey ? $dedupeKey . ':' . $channel->id() : null, $customerId);
            if ($id) {
                $queued++;
                if ($sendNow) {
                    self::sendOne($id);
                }
            }
        }
        return $queued;
    }

    /** Queue an email to a specific address (e.g. staff alerts, password resets). */
    public static function notifyEmail(string $email, string $template, array $data = [], ?string $dedupeKey = null, bool $sendNow = false, ?int $customerId = null): ?int
    {
        $id = self::queue('email', $email, $template, $data, $dedupeKey ? $dedupeKey . ':email' : null, $customerId);
        if ($id && $sendNow) {
            self::sendOne($id);
        }
        return $id;
    }

    /** Alert the business (staff) email address. */
    public static function alertStaff(string $title, string $message, ?string $dedupeKey = null): void
    {
        $to = (string) Settings::get('business_email');
        if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::notifyEmail($to, 'staff_alert', ['alert_title' => $title, 'alert_message' => $message], $dedupeKey);
        }
    }

    public static function queue(string $channel, string $recipient, string $template, array $data, ?string $dedupeKey, ?int $customerId): ?int
    {
        if (!isset(self::TEMPLATES[$template])) {
            throw new \InvalidArgumentException("Unknown notification template: {$template}");
        }
        if ($dedupeKey !== null && db()->fetchColumn('SELECT id FROM notifications WHERE dedupe_key = ?', [$dedupeKey])) {
            return null;
        }
        [$subject, $body] = self::render($template, $data);
        try {
            return db()->insert('notifications', [
                'customer_id' => $customerId,
                'channel' => $channel,
                'recipient' => $recipient,
                'template' => $template,
                'subject' => $subject,
                'body' => $body,
                'status' => 'queued',
                'attempts' => 0,
                'dedupe_key' => $dedupeKey,
                'send_after' => now(),
                'created_at' => now(),
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return null; // concurrent duplicate
            }
            throw $e;
        }
    }

    public static function render(string $template, array $data): array
    {
        $data += ['business' => Settings::get('business_name'), 'portal_url' => absolute_url('/login')];
        $replace = [];
        foreach ($data as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $replace['{' . $k . '}'] = (string) $v;
            }
        }
        $subject = strtr(self::TEMPLATES[$template], $replace);
        $inner = View::partial('emails.' . $template, $data);
        $body = View::partial('emails.layout', ['content' => $inner, 'subject' => $subject] + $data);
        return [$subject, $body];
    }

    /** Send queued notifications (called from cron). Returns [sent, failed]. */
    public static function processQueue(int $limit = 50): array
    {
        $rows = db()->fetchAll(
            "SELECT id FROM notifications WHERE status = 'queued' AND send_after <= NOW() ORDER BY id LIMIT " . (int) $limit
        );
        $sent = $failed = 0;
        foreach ($rows as $row) {
            self::sendOne((int) $row['id']) ? $sent++ : $failed++;
        }
        return [$sent, $failed];
    }

    public static function sendOne(int $id): bool
    {
        $n = db()->fetch('SELECT * FROM notifications WHERE id = ?', [$id]);
        if (!$n || $n['status'] === 'sent') {
            return true;
        }
        // Claim the row so parallel processes don't double-send.
        $claimed = db()->query("UPDATE notifications SET attempts = attempts + 1 WHERE id = ? AND status = 'queued' AND attempts = ?", [$id, (int) $n['attempts']])->rowCount();
        if (!$claimed) {
            return false;
        }
        $channel = self::channels()[$n['channel']] ?? null;
        try {
            if (!$channel) {
                throw new \RuntimeException("Notification channel '{$n['channel']}' is not available.");
            }
            $channel->send($n + ['text' => self::plainText((string) $n['body'])]);
            db()->update('notifications', ['status' => 'sent', 'sent_at' => now(), 'last_error' => null], 'id = :id', ['id' => $id]);
            return true;
        } catch (\Throwable $e) {
            $attempts = (int) $n['attempts'] + 1;
            $final = $attempts >= self::MAX_ATTEMPTS || !$channel || ($channel && !$channel->isEnabled());
            db()->update('notifications', [
                'status' => $final ? 'failed' : 'queued',
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
                'send_after' => date('Y-m-d H:i:s', time() + 300 * $attempts),
            ], 'id = :id', ['id' => $id]);
            Logger::error('Notification delivery failed', ['id' => $id, 'error' => $e->getMessage()], 'notifications');
            return false;
        }
    }

    public static function retry(int $id): void
    {
        db()->update('notifications', ['status' => 'queued', 'attempts' => 0, 'send_after' => now()], "id = :id AND status = 'failed'", ['id' => $id]);
    }

    private static function plainText(string $html): string
    {
        $text = preg_replace(['#<br\s*/?>#i', '#</p>|</tr>|</h\d>#i'], ["\n", "\n\n"], $html);
        return trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode(strip_tags((string) $text), ENT_QUOTES)));
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Services\Notifications\EmailChannel;
use App\Services\Settings;

final class SettingsController extends Controller
{
    /** Sections → fields. type: text|email|url|number|password|checkbox|select|textarea */
    private const SECTIONS = [
        'general' => ['label' => 'Business', 'fields' => [
            ['business_name', 'Business name', 'text', 'required|max:150'],
            ['business_email', 'Business email', 'email', 'required|email|max:190'],
            ['business_phone', 'Phone', 'text', 'max:60'],
            ['business_website', 'Website', 'url', 'url|max:190'],
            ['business_address', 'Address (shown on invoices)', 'textarea', 'max:500'],
            ['default_country', 'Default customer country', 'text', 'max:100'],
        ]],
        'billing' => ['label' => 'Billing & invoices', 'fields' => [
            ['currency', 'Currency code', 'text', 'required|regex:/^[A-Z]{3}$/'],
            ['invoice_prefix', 'Invoice number prefix', 'text', 'required|max:10'],
            ['invoice_next_number', 'Next invoice number', 'number', 'required|integer|min:1|max:9999999'],
            ['invoice_due_days', 'Days until invoices are due', 'number', 'required|integer|min:0|max:90'],
            ['renewal_invoice_days', 'Generate renewal invoices this many days ahead', 'number', 'required|integer|min:0|max:90'],
            ['invoice_footer', 'Invoice footer note', 'textarea', 'max:500'],
            ['bank_instructions', 'Bank / manual payment instructions', 'textarea', 'max:1000'],
        ]],
        'automation' => ['label' => 'Automation', 'fields' => [
            ['auto_invoice_enabled', 'Generate renewal invoices automatically', 'checkbox', ''],
            ['reminder_days_before', 'Renewal reminder days before due (comma-separated)', 'text', 'regex:/^[\d,\s]*$/|max:60'],
            ['reminder_on_due_date', 'Send a reminder on the due date', 'checkbox', ''],
            ['overdue_reminder_days', 'Overdue reminders N days after due (comma-separated)', 'text', 'regex:/^[\d,\s]*$/|max:60'],
            ['auto_suspend_enabled', 'Suspend services after the grace period', 'checkbox', ''],
            ['suspend_grace_days', 'Days overdue before suspension', 'number', 'required|integer|min:0|max:90'],
            ['auto_unsuspend_enabled', 'Reactivate automatically when the invoice is paid', 'checkbox', ''],
            ['auto_provision_enabled', 'Provision accounts automatically when paid', 'checkbox', ''],
            ['provision_max_attempts', 'Max automatic provisioning attempts', 'number', 'required|integer|min:1|max:25'],
            ['termination_flag_days', 'Flag suspended services for termination after N days', 'number', 'required|integer|min:1|max:365'],
            ['sync_interval_minutes', 'Sync hosting accounts every N minutes', 'number', 'required|integer|min:5|max:1440'],
        ]],
        'email' => ['label' => 'Email (SMTP)', 'fields' => [
            ['mail_enabled', 'Send transactional email', 'checkbox', ''],
            ['mail_from_name', 'From name', 'text', 'max:150'],
            ['mail_from_address', 'From address', 'email', 'email|max:190'],
            ['smtp_host', 'SMTP host', 'text', 'max:190'],
            ['smtp_port', 'SMTP port', 'number', 'integer|min:1|max:65535'],
            ['smtp_encryption', 'Encryption', 'select:tls=>TLS,ssl=>SSL,none=>None', 'in:tls,ssl,none'],
            ['smtp_username', 'SMTP username', 'text', 'max:190'],
            ['smtp_password', 'SMTP password', 'password', ''],
        ]],
        'payments' => ['label' => 'Payments', 'fields' => [
            ['paynow_enabled', 'Accept Paynow payments', 'checkbox', ''],
            ['paynow_integration_id', 'Paynow integration ID', 'text', 'max:60'],
            ['paynow_integration_key', 'Paynow integration key', 'password', ''],
            ['paynow_auth_email', 'Paynow account email (for reference)', 'email', 'email|max:190'],
            ['paynow_mobile_enabled', 'Offer mobile money (EcoCash etc.) on Paynow', 'checkbox', ''],
        ]],
        'portal' => ['label' => 'Client portal', 'fields' => [
            ['allow_registration', 'Allow customers to register themselves', 'checkbox', ''],
            ['min_password_length', 'Minimum password length', 'number', 'required|integer|min:8|max:64'],
            ['ticket_categories', 'Support ticket categories (comma-separated)', 'text', 'required|max:255'],
        ]],
    ];

    public function index(?string $section = null): string
    {
        $section = $section && isset(self::SECTIONS[$section]) ? $section : 'general';
        return $this->view('admin.settings.index', [
            'title' => 'Settings · ' . self::SECTIONS[$section]['label'],
            'section' => $section,
            'sections' => array_map(fn ($s) => $s['label'], self::SECTIONS),
            'fields' => self::SECTIONS[$section]['fields'],
        ]);
    }

    public function save(string $section): never
    {
        if (!isset(self::SECTIONS[$section])) {
            abort(404);
        }
        $rules = [];
        foreach (self::SECTIONS[$section]['fields'] as [$key, , $type, $rule]) {
            if ($rule !== '' && $type !== 'checkbox' && $type !== 'password') {
                $rules[$key] = $rule;
            }
        }
        $data = $this->validate($rules);
        $values = [];
        foreach (self::SECTIONS[$section]['fields'] as [$key, , $type]) {
            if ($type === 'checkbox') {
                $values[$key] = !empty($_POST[$key]) ? '1' : '0';
            } elseif ($type === 'password') {
                $v = trim((string) ($_POST[$key] ?? ''));
                if ($v !== '') {
                    $values[$key] = $v;
                }
            } elseif (array_key_exists($key, $data)) {
                $values[$key] = $data[$key];
            }
        }
        Settings::setMany($values);
        audit('settings.updated', 'system', null, ['section' => $section, 'keys' => array_keys($values)]);
        flash('success', 'Settings saved.');
        redirect('/settings/' . $section);
    }

    /** Send a test email using the saved SMTP settings. */
    public function testEmail(): never
    {
        $to = trim((string) Request::input('email', ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            back_with_errors(['email' => 'Enter a valid email address.'], '/settings/email');
        }
        try {
            $mailer = EmailChannel::mailer();
            $mailer->addAddress($to);
            $mailer->Subject = 'HostFlow test email';
            $mailer->Body = '<p>This is a test message from HostFlow at ' . e(setting('business_name')) . '. SMTP is working.</p>';
            $mailer->AltBody = 'This is a test message from HostFlow. SMTP is working.';
            $mailer->send();
            audit('settings.email_tested', 'system', null, ['to' => $to]);
            flash('success', 'Test email sent to ' . $to . '.');
        } catch (\Throwable $e) {
            flash('error', 'Could not send: ' . $e->getMessage());
        }
        redirect('/settings/email');
    }
}

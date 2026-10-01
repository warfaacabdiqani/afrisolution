<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;

class PlatformMailConfigurator
{
    public function apply(): void
    {
        $email = app(SystemSettingsService::class)->section('email', false);
        if (!$email) return;
        $mailer = $email['mailer'] ?? config('mail.default');
        config([
            'mail.default' => $mailer,
            'mail.mailers.smtp.host' => $email['smtp_host'] ?? config('mail.mailers.smtp.host'),
            'mail.mailers.smtp.port' => $email['smtp_port'] ?? config('mail.mailers.smtp.port'),
            'mail.mailers.smtp.username' => $email['smtp_username'] ?? config('mail.mailers.smtp.username'),
            'mail.mailers.smtp.password' => $email['smtp_password'] ?? config('mail.mailers.smtp.password'),
            'mail.mailers.smtp.scheme' => match ($email['encryption'] ?? null) {
                'tls' => 'smtp', // SMTP negotiates STARTTLS.
                'ssl' => 'smtps', // TLS from the start of the connection.
                default => config('mail.mailers.smtp.scheme'),
            },
            'mail.from.address' => $email['from_email'] ?? config('mail.from.address'),
            'mail.from.name' => $email['from_name'] ?? config('mail.from.name'),
        ]);
        Mail::purge($mailer);
    }
}

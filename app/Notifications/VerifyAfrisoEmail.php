<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class VerifyAfrisoEmail extends VerifyEmail
{
    protected function verificationUrl($notifiable): string
    {
        $path = URL::temporarySignedRoute('verification.verify', now()->addMinutes(config('auth.verification.expire', 60)), [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
        ], absolute: false);
        $base = app(\App\Services\SystemSettingsService::class)->get('general.platform_url') ?: config('app.url');
        return rtrim($base, '/').$path;
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your Afriso email address')
            ->greeting('AFRI SOLUTION')
            ->line('Thank you for creating your Afriso account.')
            ->line('Please verify your email address to activate access to your business workspace.')
            ->action('Verify Email Address', $this->verificationUrl($notifiable))
            ->line('If you did not create this account, you can ignore this email.')
            ->salutation('AFRI SOLUTION · Business Management');
    }
}

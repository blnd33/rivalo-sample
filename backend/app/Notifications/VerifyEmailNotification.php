<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sends a signed link to /email/verify/{id}/{hash}. The link works in any browser without the
 * user being signed in there (the signature proves it came from our email).
 */
class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your RIVALO email address')
            ->greeting('Welcome to RIVALO')
            ->line('Confirm your email address to register for tournaments and join teams.')
            ->action('Verify email address', $url)
            ->line('This link expires in 60 minutes. If you did not create an account, you can ignore this email.');
    }
}

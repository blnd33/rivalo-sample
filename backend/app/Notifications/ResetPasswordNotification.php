<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your RIVALO password')
            ->line('We received a request to reset the password for your RIVALO account.')
            ->action('Choose a new password', $url)
            ->line('This link expires in '.config('auth.passwords.users.expire', 60).' minutes.')
            ->line('If you did not request a reset, no action is needed and your password stays the same.');
    }
}

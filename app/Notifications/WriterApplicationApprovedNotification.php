<?php

namespace App\Notifications;

use App\Models\WriterApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WriterApplicationApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public WriterApplication $application,
        public string $token
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your Life Platform application has been approved')
            ->greeting('Hello '.$this->application->fullName().',')
            ->line('Your application has been approved and your platform access is ready.')
            ->line('Assigned role: '.ucfirst((string) $this->application->assigned_role))
            ->line('Use the button below to set your password and access your account.')
            ->action('Set Your Password', route('password.reset', [
                'token' => $this->token,
                'email' => $notifiable->email,
            ]))
            ->line('After signing in, open My Article Submissions to draft your first story, submit it for review, and watch for editor feedback.')
            ->line('Writer earnings only appear after an article is approved, published, and added to the word ledger. Banking or payout details are handled later through the payout workflow.')
            ->line('If you already have a password, you can still use this link to reset it safely.');
    }
}

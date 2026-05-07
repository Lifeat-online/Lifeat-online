<?php

namespace App\Notifications;

use App\Models\CivicFaultReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CivicFaultReportedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CivicFaultReport $report)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $category = CivicFaultReport::categories()[$this->report->category] ?? $this->report->category;

        return (new MailMessage)
            ->subject('New civic fault report: '.$category)
            ->greeting('Hello')
            ->line('A new civic infrastructure fault has been reported in one of your areas.')
            ->line('Category: '.$category)
            ->line('Severity: '.(CivicFaultReport::severities()[$this->report->severity] ?? $this->report->severity))
            ->line('Location: '.$this->report->latitude.', '.$this->report->longitude)
            ->line('Description: '.$this->report->description)
            ->action('Open assigned reports', route('councillor.faults.index'))
            ->line('This report may be pending moderation before it is visible to the public map.');
    }
}


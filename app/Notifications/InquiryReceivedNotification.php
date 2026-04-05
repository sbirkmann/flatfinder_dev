<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InquiryReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Inquiry $inquiry)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Neue Anfrage: ' . $this->inquiry->name)
            ->greeting('Neue Anfrage erhalten!')
            ->line("**{$this->inquiry->name}** hat eine Anfrage für das Projekt **{$this->inquiry->project?->name}** gesendet.")
            ->line('E-Mail: ' . ($this->inquiry->email ?? '–'))
            ->line('Telefon: ' . ($this->inquiry->phone ?? '–'))
            ->action('Anfrage ansehen', url('/inquiries'))
            ->salutation('3D-Wohnungsfinder');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'inquiry_received',
            'inquiry_id' => $this->inquiry->id,
            'title' => 'Neue Anfrage von ' . $this->inquiry->name,
            'body' => $this->inquiry->project?->name . ' – ' . ($this->inquiry->email ?? 'Keine E-Mail'),
            'project_id' => $this->inquiry->project_id,
        ];
    }
}

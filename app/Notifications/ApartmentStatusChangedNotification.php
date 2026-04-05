<?php

namespace App\Notifications;

use App\Models\Apartment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApartmentStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Apartment $apartment,
        public string $oldStatus,
        public string $newStatus
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'apartment_status_changed',
            'apartment_id' => $this->apartment->id,
            'apartment_name' => $this->apartment->name,
            'project_id' => $this->apartment->project_id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'title' => "Wohnung \"{$this->apartment->name}\" – Status: {$this->newStatus}",
            'body' => "Status geändert von {$this->oldStatus} → {$this->newStatus}",
        ];
    }
}

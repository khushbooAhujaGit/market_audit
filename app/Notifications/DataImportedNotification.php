<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class DataImportedNotification extends Notification
{
    public function via($notifiable)
    {
        return ['database']; // Only database notification (in-app)
    }

    public function toArray($notifiable)
    {
        return [
            'message' => 'Your data import job has been successfully completed.',
        ];
    }
}

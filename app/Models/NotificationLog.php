<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'notification_id', 'type', 'notifiable_type', 'notifiable_id',
        'recipient_email', 'channel', 'status', 'error',
    ];
}

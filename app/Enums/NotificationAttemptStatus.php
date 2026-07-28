<?php

namespace App\Enums;

enum NotificationAttemptStatus: string
{
    case Delivered = 'delivered';
    case Failed = 'failed';
}

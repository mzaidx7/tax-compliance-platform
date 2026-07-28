<?php

namespace App\Enums;

enum NotificationFinalStatus: string
{
    case Delivered = 'delivered';
    case Failed = 'failed';
}

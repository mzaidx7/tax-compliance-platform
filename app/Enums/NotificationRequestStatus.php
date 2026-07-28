<?php

namespace App\Enums;

enum NotificationRequestStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case RetryPending = 'retry_pending';
    case Delivered = 'delivered';
    case Failed = 'failed';
}

<?php

namespace App\Enums;

enum FirmStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';
}

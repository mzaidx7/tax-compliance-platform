<?php

namespace App\Jobs;

interface FirmAwareJob
{
    public function firmId(): string;
}

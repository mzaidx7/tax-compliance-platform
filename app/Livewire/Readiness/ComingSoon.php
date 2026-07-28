<?php

declare(strict_types=1);

namespace App\Livewire\Readiness;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('E-invoicing readiness')]
final class ComingSoon extends Component
{
    public function render(): View
    {
        return view('readiness.coming-soon');
    }
}

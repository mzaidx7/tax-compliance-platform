<?php

use App\Http\Controllers\ExportDownloadController;
use App\Http\Controllers\FirmSelectionController;
use App\Http\Controllers\FirmSwitchController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Livewire\Audit\Index as AuditIndex;
use App\Livewire\Clients\Index as ClientsIndex;
use App\Livewire\Members\Index as MembersIndex;
use App\Livewire\Obligations\Index as ObligationsIndex;
use App\Livewire\Settings\FeatureFlags as SettingsFeatureFlags;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('throttle:10,1')->group(function () {
    Route::get('invitations/{token}', [InvitationAcceptanceController::class, 'show'])
        ->name('invitations.show');
    Route::post('invitations/{token}', [InvitationAcceptanceController::class, 'accept'])
        ->name('invitations.accept');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('firms', FirmSelectionController::class)->name('firms.select');
    Route::post('firms/{firm}/switch', FirmSwitchController::class)->name('firms.switch');
});

Route::middleware(['auth', 'verified', 'firm.context'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('clients', ClientsIndex::class)->name('clients.index');
    Route::livewire('members', MembersIndex::class)->name('members.index');
    Route::livewire('obligations', ObligationsIndex::class)->name('obligations.index');
    Route::livewire('audit', AuditIndex::class)->name('audit.index');
    Route::get('exports/{exportAuditLog}/download', ExportDownloadController::class)
        ->name('exports.download');
    Route::livewire('settings/features', SettingsFeatureFlags::class)->name('settings.features');
});

require __DIR__.'/settings.php';

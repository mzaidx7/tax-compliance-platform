<?php

use App\Http\Controllers\DocumentEvidenceDownloadController;
use App\Http\Controllers\ExportDownloadController;
use App\Http\Controllers\FirmSelectionController;
use App\Http\Controllers\FirmSwitchController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Livewire\Audit\Index as AuditIndex;
use App\Livewire\Clients\Index as ClientsIndex;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Documents\Index as DocumentsIndex;
use App\Livewire\Generation\Index as GenerationIndex;
use App\Livewire\Members\Index as MembersIndex;
use App\Livewire\Obligations\Index as ObligationsIndex;
use App\Livewire\Readiness\Invoices\Index as ReadinessInvoicesIndex;
use App\Livewire\Readiness\Parties\Index as ReadinessPartiesIndex;
use App\Livewire\Readiness\Rules\Index as ReadinessRulesIndex;
use App\Livewire\Rules\Index as RulesIndex;
use App\Livewire\Settings\FeatureFlags as SettingsFeatureFlags;
use App\Livewire\WorkItems\Index as WorkItemsIndex;
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
    Route::livewire('dashboard', DashboardIndex::class)->name('dashboard');
    Route::livewire('clients', ClientsIndex::class)->name('clients.index');
    Route::livewire('document-expiry', DocumentsIndex::class)->name('documents.index');
    Route::livewire('members', MembersIndex::class)->name('members.index');
    Route::livewire('obligations', ObligationsIndex::class)->name('obligations.index');
    Route::livewire('rules', RulesIndex::class)->name('rules.index');
    Route::livewire('generation', GenerationIndex::class)->name('generation.index');
    Route::livewire('readiness/rules', ReadinessRulesIndex::class)->name('readiness.rules.index');
    Route::livewire('readiness/parties', ReadinessPartiesIndex::class)->name('readiness.parties.index');
    Route::livewire('readiness/invoices', ReadinessInvoicesIndex::class)->name('readiness.invoices.index');
    Route::livewire('work', WorkItemsIndex::class)->name('work-items.index');
    Route::livewire('audit', AuditIndex::class)->name('audit.index');
    Route::get('exports/{exportAuditLog}/download', ExportDownloadController::class)
        ->name('exports.download');
    Route::get('documents/{documentEvidence}/download', DocumentEvidenceDownloadController::class)
        ->name('documents.download');
    Route::livewire('settings/features', SettingsFeatureFlags::class)->name('settings.features');
});

require __DIR__.'/settings.php';

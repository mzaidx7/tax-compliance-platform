<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentPurpose;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $work_item_id
 * @property DocumentPurpose $purpose
 * @property string $original_name
 * @property string $extension
 * @property string $detected_mime_type
 * @property string $logical_path
 * @property string $sha256
 * @property int $bytes
 * @property int $uploaded_by
 * @property Carbon $uploaded_at
 */
#[Fillable([
    'work_item_id',
    'purpose',
    'original_name',
    'extension',
    'detected_mime_type',
    'logical_path',
    'sha256',
    'bytes',
    'uploaded_by',
    'uploaded_at',
])]
final class DocumentEvidence extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Document evidence is append-only.'));
        self::deleting(fn (): never => throw new LogicException('Document evidence is append-only.'));
    }

    /** @return BelongsTo<WorkItem, $this> */
    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return HasMany<DocumentScanEvent, $this> */
    public function scanEvents(): HasMany
    {
        return $this->hasMany(DocumentScanEvent::class);
    }

    public function latestScan(): ?DocumentScanEvent
    {
        return $this->scanEvents->sortByDesc('id')->first();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purpose' => DocumentPurpose::class,
            'uploaded_at' => 'datetime',
            'bytes' => 'integer',
        ];
    }
}

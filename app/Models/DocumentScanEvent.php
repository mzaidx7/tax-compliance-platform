<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MalwareScanVerdict;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $document_evidence_id
 * @property MalwareScanVerdict $verdict
 * @property string $scanner
 * @property Carbon $scanned_at
 */
#[Fillable(['document_evidence_id', 'verdict', 'scanner', 'scanned_at'])]
final class DocumentScanEvent extends Model
{
    use BelongsToFirm, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Document scan history is append-only.'));
        self::deleting(fn (): never => throw new LogicException('Document scan history is append-only.'));
    }

    /** @return BelongsTo<DocumentEvidence, $this> */
    public function evidence(): BelongsTo
    {
        return $this->belongsTo(DocumentEvidence::class, 'document_evidence_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'verdict' => MalwareScanVerdict::class,
            'scanned_at' => 'datetime',
        ];
    }
}

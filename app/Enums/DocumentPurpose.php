<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentPurpose: string
{
    case SourceDocument = 'source_document';
    case ReviewEvidence = 'review_evidence';
    case FilingEvidence = 'filing_evidence';
    case PaymentEvidence = 'payment_evidence';

    public function label(): string
    {
        return match ($this) {
            self::SourceDocument => 'Source document',
            self::ReviewEvidence => 'Review evidence',
            self::FilingEvidence => 'Filing evidence',
            self::PaymentEvidence => 'Payment evidence',
        };
    }
}

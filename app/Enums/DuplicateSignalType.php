<?php

declare(strict_types=1);

namespace App\Enums;

enum DuplicateSignalType: string
{
    case ExactNormalizedTrn = 'exact_normalized_trn';
    case ExactNormalizedEmail = 'exact_normalized_email';
    case ExactNormalizedTelephone = 'exact_normalized_telephone';
    case ExactNormalizedLegalName = 'exact_normalized_legal_name';
    case SimilarNormalizedName = 'similar_normalized_name';
    case SharedAddress = 'shared_address';
    case SharedSourceIdentifier = 'shared_source_identifier';

    public function label(): string
    {
        return match ($this) {
            self::ExactNormalizedTrn => 'Exact normalized TRN',
            self::ExactNormalizedEmail => 'Exact normalized email',
            self::ExactNormalizedTelephone => 'Exact normalized telephone',
            self::ExactNormalizedLegalName => 'Exact normalized legal name',
            self::SimilarNormalizedName => 'Similar normalized name',
            self::SharedAddress => 'Shared address',
            self::SharedSourceIdentifier => 'Shared source identifier',
        };
    }
}

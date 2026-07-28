<?php

declare(strict_types=1);

namespace App\Enums;

enum ClientService: string
{
    case VatCompliance = 'vat_compliance';
    case CorporateTaxCompliance = 'corporate_tax_compliance';
    case ExciseTaxCompliance = 'excise_tax_compliance';
    case Bookkeeping = 'bookkeeping';
    case EInvoicingReadiness = 'e_invoicing_readiness';

    public function label(): string
    {
        return match ($this) {
            self::VatCompliance => 'VAT compliance',
            self::CorporateTaxCompliance => 'Corporate tax compliance',
            self::ExciseTaxCompliance => 'Excise tax compliance',
            self::Bookkeeping => 'Bookkeeping',
            self::EInvoicingReadiness => 'E-invoicing readiness',
        };
    }
}

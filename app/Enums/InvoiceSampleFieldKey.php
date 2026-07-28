<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceSampleFieldKey: string
{
    case InvoiceNumber = 'invoice_number';
    case IssueDate = 'issue_date';
    case InvoiceType = 'invoice_type';
    case Currency = 'currency';
    case SellerIdentifier = 'seller_identifier';
    case BuyerIdentifier = 'buyer_identifier';
    case Description = 'description';
    case Quantity = 'quantity';
    case Unit = 'unit';
    case UnitPrice = 'unit_price';
    case Discount = 'discount';
    case Charges = 'charges';
    case TaxCategory = 'tax_category';
    case VatRate = 'vat_rate';
    case LineVat = 'line_vat';
    case Totals = 'totals';
    case ExchangeRate = 'exchange_rate';
    case CreditNoteReference = 'credit_note_reference';
    case OtherConditionalField = 'other_conditional_field';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}

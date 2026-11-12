<?php

declare(strict_types=1);

namespace App\Enums;

enum MonthlyInvoiceGenerationStatus: string
{
    case GENERATED = 'generated';
    case ALREADY_EXISTS = 'already_exists';
    case NOTHING_TO_INVOICE = 'nothing_to_invoice';
}

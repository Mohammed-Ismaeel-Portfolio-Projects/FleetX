<?php

declare(strict_types=1);

namespace App\Services\Invoice\Documents;

/**
 * Formatted monetary display for invoice documents (Phase 6d).
 * Formatting only — does not recalculate amounts.
 */
final class InvoiceMoneyFormatter
{
    public static function format(string $amount, string $currency): string
    {
        return number_format((float) $amount, 2, '.', ',').' '.$currency;
    }
}

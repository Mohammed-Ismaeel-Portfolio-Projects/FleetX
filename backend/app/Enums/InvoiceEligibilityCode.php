<?php

namespace App\Enums;

/**
 * Invoice eligibility outcomes (Phase 6a).
 */
enum InvoiceEligibilityCode: string
{
    case ELIGIBLE = 'eligible';
    case NOT_FINALIZED = 'not_finalized';
    case MISSING_FINAL_PRICE = 'missing_final_price';
    case MISSING_FINAL_TOTAL = 'missing_final_total';
    case MISSING_CURRENCY = 'missing_currency';
    case MISSING_BILLING_COMPANY = 'missing_billing_company';
    case ALREADY_INVOICED = 'already_invoiced';
    case INVALID_FINAL_PRICE_STATE = 'invalid_final_price_state';
}

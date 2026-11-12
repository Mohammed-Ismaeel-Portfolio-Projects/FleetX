<?php

namespace App\Enums;

enum MonthlyInvoiceStatus: string
{
    case DRAFT = 'draft';
    case FINALIZED = 'finalized';
    case VOID = 'void';
    case PAID = 'paid';
}

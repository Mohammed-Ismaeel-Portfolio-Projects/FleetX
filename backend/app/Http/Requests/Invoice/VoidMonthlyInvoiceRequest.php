<?php

namespace App\Http\Requests\Invoice;

/**
 * Ops invoice void — billing fields are server-controlled (Phase 6e).
 */
class VoidMonthlyInvoiceRequest extends FinalizeMonthlyInvoiceRequest
{
}

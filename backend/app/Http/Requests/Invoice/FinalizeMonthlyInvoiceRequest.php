<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ops invoice finalization — billing fields are server-controlled (Phase 6e).
 */
class FinalizeMonthlyInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->prohibitedBillingFieldRules();
    }

    /**
     * @return array<string, list<string>>
     */
    protected function prohibitedBillingFieldRules(): array
    {
        return [
            'status' => ['prohibited'],
            'issued_at' => ['prohibited'],
            'subtotal' => ['prohibited'],
            'total' => ['prohibited'],
            'currency' => ['prohibited'],
            'company_id' => ['prohibited'],
            'billing_period_start' => ['prohibited'],
            'billing_period_end' => ['prohibited'],
            'line_items' => ['prohibited'],
            'lineItems' => ['prohibited'],
            'shipment_id' => ['prohibited'],
            'shipment_final_price_breakdown_id' => ['prohibited'],
            'unit_price' => ['prohibited'],
            'line_total' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.prohibited' => 'Invoice status is server-controlled.',
            'issued_at.prohibited' => 'Issued date is server-controlled.',
            'subtotal.prohibited' => 'Invoice subtotal is server-controlled.',
            'total.prohibited' => 'Invoice total is server-controlled.',
            'currency.prohibited' => 'Invoice currency is server-controlled.',
            'company_id.prohibited' => 'Invoice company is server-controlled.',
            'billing_period_start.prohibited' => 'Billing period start is server-controlled.',
            'billing_period_end.prohibited' => 'Billing period end is server-controlled.',
            'line_items.prohibited' => 'Line items cannot be supplied in this request.',
            'lineItems.prohibited' => 'Line items cannot be supplied in this request.',
            'shipment_id.prohibited' => 'Shipment association is server-controlled.',
            'shipment_final_price_breakdown_id.prohibited' => 'Final price association is server-controlled.',
            'unit_price.prohibited' => 'Line item unit price is server-controlled.',
            'line_total.prohibited' => 'Line item total is server-controlled.',
        ];
    }
}

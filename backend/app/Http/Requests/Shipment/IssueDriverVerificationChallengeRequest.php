<?php

namespace App\Http\Requests\Shipment;

use App\Support\DriverVerificationEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ops/system issue of Driver verification challenge (D5 presentation contract).
 */
class IssueDriverVerificationChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event' => ['required', 'string', Rule::in(DriverVerificationEvent::all())],
            'assignment_id' => ['nullable', 'integer'],
            'driver_id' => ['prohibited'],
            'challenge' => ['prohibited'],
        ];
    }
}

<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/v1/driver/assignments/history — ownership never accepted from client.
 */
class QueryDriverAssignmentHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'status' => ['sometimes', 'string', 'in:completed,cancelled'],
            'driver_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'assignment_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'driver_id.prohibited' => 'Driver scope cannot be supplied by the client.',
            'user_id.prohibited' => 'User scope cannot be supplied by the client.',
            'company_id.prohibited' => 'Company scope cannot be supplied by the client.',
            'assignment_id.prohibited' => 'Assignment scope cannot be supplied by the client.',
        ];
    }
}

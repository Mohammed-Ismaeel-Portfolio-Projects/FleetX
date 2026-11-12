<?php

namespace App\Http\Requests\Assignment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/assignments/{assignment}/engine-enroll (C2.9-C2.8 Step 3).
 */
class EnrollAssignmentInEngineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}

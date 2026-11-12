<?php

namespace App\Http\Requests\Shipment;

use App\Support\ShipmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeShipmentStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'status_id' => [
                'required',
                'integer',
                Rule::exists('statuses', 'id')->where(function ($query) {
                    $query->where('type', ShipmentStatus::TYPE)
                        ->whereIn('name', ShipmentStatus::all());
                }),
            ],
            'actual_pickup_date' => 'sometimes|nullable|date',
            'actual_delivery_date' => 'sometimes|nullable|date|after:actual_pickup_date',
        ];
    }
}

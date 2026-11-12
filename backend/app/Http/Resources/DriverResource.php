<?php

namespace App\Http\Resources;

use App\Support\DriverOperationalAvailability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hasActiveAssignment = $this->relationLoaded('activeAssignment')
            && $this->activeAssignment !== null;

        $statusName = $this->relationLoaded('status') && $this->status
            ? $this->status->name
            : null;

        return [
            'id' => $this->id,
            /** Linked login User for messaging (M1 identity); null = messaging unavailable. */
            'user_id' => $this->user_id,
            'employee_number' => $this->employee_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'nationality' => $this->nationality,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'employment_date' => $this->employment_date?->toDateString(),
            'license_number' => $this->license_number,
            'license_class' => $this->license_class,
            'license_expiry_date' => $this->license_expiry_date?->toDateString(),
            'medical_certificate_expiry' => $this->medical_certificate_expiry?->toDateString(),
            'years_of_experience' => $this->years_of_experience,
            'status_id' => $this->status_id,
            'is_active' => $this->is_active,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'status' => $this->whenLoaded('status', function () {
                if (!$this->status) {
                    return null;
                }

                return [
                    'id' => $this->status->id,
                    'name' => $this->status->name,
                    'type' => $this->status->type,
                ];
            }),
            'has_active_assignment' => $this->when(
                $this->relationLoaded('activeAssignment'),
                $hasActiveAssignment
            ),
            // Dispatch availability SSOT (matches GetDriverAvailabilityAction rules)
            'dispatch_availability' => $this->when(
                $this->relationLoaded('activeAssignment') && $this->relationLoaded('status'),
                DriverOperationalAvailability::classify($statusName, $hasActiveAssignment)
            ),
            'active_assignment' => $this->whenLoaded('activeAssignment', function () {
                if (!$this->activeAssignment) {
                    return null;
                }

                $assignment = $this->activeAssignment;

                return [
                    'id' => $assignment->id,
                    'assignment_code' => $assignment->assignment_code,
                    'is_active' => $assignment->is_active,
                    'status_id' => $assignment->status_id,
                    'vehicle' => $assignment->relationLoaded('vehicle') && $assignment->vehicle
                        ? [
                            'id' => $assignment->vehicle->id,
                            'operational_code' => $assignment->vehicle->operational_code,
                            'plate_number' => $assignment->vehicle->plate_number,
                        ]
                        : null,
                    'shipment' => $assignment->relationLoaded('shipment') && $assignment->shipment
                        ? [
                            'id' => $assignment->shipment->id,
                            'shipment_number' => $assignment->shipment->shipment_number,
                            'pickup_city' => $assignment->shipment->pickup_city,
                            'delivery_city' => $assignment->shipment->delivery_city,
                        ]
                        : null,
                ];
            }),
            'tracking_state' => $this->whenLoaded('trackingState', function () {
                if (!$this->trackingState) {
                    return null;
                }

                $state = $this->trackingState;

                return [
                    'latitude' => $state->latitude !== null ? (float) $state->latitude : null,
                    'longitude' => $state->longitude !== null ? (float) $state->longitude : null,
                    'speed' => $state->speed !== null ? (float) $state->speed : null,
                    'heading' => $state->heading !== null ? (float) $state->heading : null,
                    'movement_status' => $state->movement_status,
                    'online_status' => $state->online_status,
                    'signal_quality' => $state->signal_quality !== null ? (float) $state->signal_quality : null,
                    'last_update_at' => $state->last_update_at?->format('Y-m-d H:i:s'),
                ];
            }),
            'driver_score' => $this->whenLoaded('driverScore', function () {
                if (!$this->driverScore) {
                    return null;
                }

                $score = $this->driverScore;

                $overall = $score->score !== null ? (float) $score->score : null;

                return [
                    // Shared overall contract aliases (DRIVER-EVAL-08A).
                    'overall_score' => $overall,
                    // Legacy scaffold fields (Dispatcher consumers).
                    'score' => $overall,
                    'total_deliveries' => (int) $score->total_deliveries,
                    'late_deliveries' => (int) $score->late_deliveries,
                    // Accurate aliases (EVAL-05 semantics: evaluated assignments / late evaluations).
                    'evaluated_assignments_count' => (int) $score->evaluated_assignments_count,
                    'incomplete_assignments_count' => (int) $score->incomplete_assignments_count,
                    'late_evaluations_count' => (int) $score->late_deliveries,
                    // DRIVER-EVAL-05 authoritative projection fields.
                    'score_label' => $score->score_label,
                    'confidence' => $score->confidence?->value,
                    'maturity' => $score->maturity?->value,
                    'trend' => $score->trend?->value,
                    'recent_score' => $score->recent_score !== null ? (float) $score->recent_score : null,
                    'previous_score' => $score->previous_score !== null ? (float) $score->previous_score : null,
                    'trend_delta' => $score->trend_delta !== null ? (float) $score->trend_delta : null,
                    'category_scores' => $score->category_scores,
                    'aggregation_version' => $score->aggregation_version,
                    'last_evaluated_at' => $score->last_evaluated_at?->format('Y-m-d H:i:s'),
                    'aggregated_at' => $score->aggregated_at?->format('Y-m-d H:i:s'),
                    'score_available' => $score->isScoreAvailable(),
                ];
            }),
            'assignments_count' => $this->whenCounted('assignments'),
        ];
    }
}

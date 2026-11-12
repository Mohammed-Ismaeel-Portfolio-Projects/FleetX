<?php

namespace App\Http\Resources\Driver;

use App\Support\DriverAssignmentStatusKey;
use App\Support\DriverExecutionPhase;
use App\Support\DriverHistoryOutcome;
use App\Support\DriverIssueType;
use App\Support\DriverVerificationEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Driver-safe historical trip summary (D11). Whitelist only — no POD binaries, no Ops internals.
 */
class DriverAssignmentSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Assignment $assignment */
        $assignment = $this->resource['assignment'];
        /** @var Collection $challenges */
        $challenges = $this->resource['challenges'] ?? collect();
        $issues = $this->resource['issues'] ?? collect();
        $proof = $this->resource['proof'] ?? null;

        $statusName = $assignment->status?->name;
        $phase = $assignment->execution_phase;
        $shipment = $assignment->shipment;
        $outcome = DriverHistoryOutcome::fromAssignment($statusName, $phase);

        $pickupVerified = $challenges->firstWhere('event', DriverVerificationEvent::PICKUP_VERIFY);
        $deliveryVerified = $challenges->firstWhere('event', DriverVerificationEvent::DELIVERY_VERIFY);

        $issueRows = [];
        foreach ($issues as $issue) {
            $issueRows[] = [
                'type' => $issue->type,
                'label' => $this->issueLabel((string) $issue->type),
                'description' => $issue->description,
                'status' => $issue->status?->name,
                'created_at' => $issue->created_at?->format('Y-m-d H:i:s'),
            ];
        }

        $proofTypes = [];
        if ($proof !== null) {
            if (! empty($proof->image_url)) {
                $proofTypes[] = 'photo';
            }
            if (! empty($proof->signature)) {
                $proofTypes[] = 'signature';
            }
        }

        return [
            'id' => $assignment->id,
            'assignment_code' => $assignment->assignment_code,
            'status' => $assignment->status ? [
                'name' => $assignment->status->name,
                'key' => DriverAssignmentStatusKey::fromStatusName($statusName),
            ] : null,
            'outcome' => $outcome,
            'execution' => [
                'phase' => $phase,
                'stage' => DriverExecutionPhase::stage($phase),
            ],
            'assigned_at' => $assignment->assigned_at?->format('Y-m-d H:i:s'),
            'ended_at' => $assignment->ended_at?->format('Y-m-d H:i:s'),
            'shipment' => $shipment ? [
                'id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'reference_number' => $shipment->reference_number,
                'status' => $shipment->status?->name,
                'pickup' => [
                    'company' => $shipment->pickup_company,
                    'address' => $shipment->pickup_address,
                    'city' => $shipment->pickup_city,
                    'country' => $shipment->pickup_country,
                ],
                'delivery' => [
                    'company' => $shipment->delivery_company,
                    'address' => $shipment->delivery_address,
                    'city' => $shipment->delivery_city,
                    'country' => $shipment->delivery_country,
                ],
                'planned_pickup_date' => $shipment->planned_pickup_date?->format('Y-m-d'),
                'planned_delivery_date' => $shipment->planned_delivery_date?->format('Y-m-d'),
            ] : null,
            'vehicle' => $assignment->vehicle ? [
                'plate_number' => $assignment->vehicle->plate_number,
                'fleet_number' => $assignment->vehicle->fleet_number,
            ] : null,
            'trailer' => $assignment->trailer ? [
                'trailer_code' => $assignment->trailer->trailer_code,
                'plate_number' => $assignment->trailer->plate_number,
            ] : null,
            'verification' => [
                'pickup' => [
                    'verified' => $pickupVerified !== null,
                    'verified_at' => $pickupVerified?->consumed_at?->format('Y-m-d H:i:s'),
                ],
                'delivery' => [
                    'verified' => $deliveryVerified !== null,
                    'verified_at' => $deliveryVerified?->consumed_at?->format('Y-m-d H:i:s'),
                ],
            ],
            'proof' => [
                'exists' => $proof !== null,
                'confirmed_at' => $proof?->confirmed_at?->format('Y-m-d H:i:s'),
                'types' => $proofTypes,
            ],
            'exceptions' => $issueRows,
            'safety' => [
                'speed_alert_count' => (int) ($this->resource['speed_alert_count'] ?? 0),
                'harsh_braking_count' => (int) ($this->resource['harsh_braking_count'] ?? 0),
                'sudden_stop_count' => (int) ($this->resource['sudden_stop_count'] ?? 0),
                'motion_alert_count' => (int) ($this->resource['harsh_braking_count'] ?? 0)
                    + (int) ($this->resource['sudden_stop_count'] ?? 0),
            ],
        ];
    }

    private function issueLabel(string $type): string
    {
        return match ($type) {
            DriverIssueType::TRAFFIC => 'Traffic',
            DriverIssueType::TRAFFIC_LIGHT => 'Traffic light',
            DriverIssueType::VEHICLE_PROBLEM => 'Vehicle problem',
            DriverIssueType::ACCIDENT => 'Accident',
            DriverIssueType::LOADING_PROBLEM => 'Loading problem',
            DriverIssueType::DELIVERY_PROBLEM => 'Delivery problem',
            DriverIssueType::OTHER => 'Other',
            default => $type,
        };
    }
}

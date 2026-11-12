<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\Issue;
use App\Models\Status;
use App\Models\User;
use App\Support\DriverAssignmentTransitionMap;
use App\Support\DriverIssueType;
use Illuminate\Support\Facades\DB;

/**
 * Driver-scoped issue create (D7). Always Open. Server binds shipment + driver.
 */
class CreateDriverIssueAction
{
    public function execute(User $user, int|string $assignmentId, array $payload): ApiResult
    {
        $user->loadMissing(['role', 'driver']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403);
        }

        $type = (string) ($payload['type'] ?? '');
        if (! DriverIssueType::isValid($type)) {
            return ApiResult::error('Invalid issue type.', 422, [
                'code' => 'INVALID_ISSUE_TYPE',
            ]);
        }

        $description = isset($payload['description']) ? trim((string) $payload['description']) : '';
        if (DriverIssueType::requiresDescription($type) && $description === '') {
            return ApiResult::error('Description is required for Other.', 422, [
                'code' => 'DESCRIPTION_REQUIRED',
            ]);
        }

        if ($description === '') {
            $description = $type;
        }

        return DB::transaction(function () use ($user, $driver, $assignmentId, $type, $description) {
            /** @var Assignment|null $assignment */
            $assignment = Assignment::query()
                ->forDriver($driver->id)
                ->lockForUpdate()
                ->find($assignmentId);

            if ($assignment === null) {
                return ApiResult::notFound('Assignment not found');
            }

            $assignment->loadMissing('status');
            $statusName = $assignment->status?->name;

            if (! $assignment->is_active || $statusName !== DriverAssignmentTransitionMap::IN_OPERATION) {
                return ApiResult::error('Issues can only be reported on an active in-operation assignment.', 409, [
                    'code' => 'ASSIGNMENT_NOT_IN_OPERATION',
                ]);
            }

            if ($assignment->shipment_id === null) {
                return ApiResult::error('Assignment has no shipment.', 422);
            }

            $openStatus = Status::query()
                ->where('name', 'Open')
                ->where('type', 'issue')
                ->first();

            if ($openStatus === null) {
                return ApiResult::error('Open issue status is not configured.', 500);
            }

            // Idempotency check for offline queue retries
            $clientEventId = $payload['client_event_id'] ?? $payload['idempotency_key'] ?? null;
            $existingIssue = Issue::query()
                ->where('shipment_id', $assignment->shipment_id)
                ->where('driver_id', $driver->id)
                ->where('type', $type)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->latest()
                ->first();

            if ($existingIssue !== null) {
                return ApiResult::success([
                    'issue' => [
                        'id' => $existingIssue->id,
                        'type' => $existingIssue->type,
                        'description' => $existingIssue->description,
                        'status' => [
                            'id' => $existingIssue->status?->id,
                            'name' => $existingIssue->status?->name,
                        ],
                        'created_at' => $existingIssue->created_at?->format('Y-m-d H:i:s'),
                    ],
                    'deduped' => true,
                ], 'Issue reported');
            }

            $issue = Issue::query()->create([
                'shipment_id' => $assignment->shipment_id,
                'driver_id' => $driver->id,
                'type' => $type,
                'description' => $description,
                'status_id' => $openStatus->id,
            ]);

            $issue->load('status');

            return ApiResult::success([
                'issue' => [
                    'id' => $issue->id,
                    'type' => $issue->type,
                    'description' => $issue->description,
                    'status' => [
                        'id' => $issue->status?->id,
                        'name' => $issue->status?->name,
                    ],
                    'created_at' => $issue->created_at?->format('Y-m-d H:i:s'),
                ],
            ], 'Issue reported');
        });
    }
}

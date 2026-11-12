<?php

namespace App\Actions\Notification;

use App\Core\ApiResult;
use App\Models\User;

/**
 * Fan-out one Client notification to every Client user on a company (C4.2-A2.1).
 *
 * Company id must come from trusted server-side domain context.
 * Recipients are resolved as: company_id match AND role.name = client.
 * Dispatcher, admin, and driver users are never written to this inbox.
 */
class NotifyCompanyClientUsersAction
{
    public function __construct(
        private readonly WriteClientNotificationAction $writeAction
    ) {}

    /**
     * @param  array{
     *     title?: mixed,
     *     message?: mixed,
     *     type?: mixed,
     *     action_kind?: mixed,
     *     action_id?: mixed
     * }  $notification
     */
    public function execute(string $customerCompanyId, array $notification): ApiResult
    {
        $companyId = trim($customerCompanyId);
        if ($companyId === '') {
            return ApiResult::validation(
                ['customer_company_id' => ['Customer company is required.']],
                'Invalid Client notification'
            );
        }

        $clients = User::query()
            ->with('role')
            ->where('company_id', $companyId)
            ->whereHas('role', fn ($q) => $q->where('name', 'client'))
            ->orderBy('id')
            ->get();

        $written = [];

        foreach ($clients as $client) {
            $result = $this->writeAction->execute([
                'user_id' => $client->id,
                'title' => $notification['title'] ?? null,
                'message' => $notification['message'] ?? null,
                'type' => $notification['type'] ?? null,
                'action_kind' => $notification['action_kind'] ?? null,
                'action_id' => $notification['action_id'] ?? null,
            ]);

            if (! $result->success) {
                if ($result->statusCode === 422) {
                    return $result;
                }

                continue;
            }

            $written[] = $result->data;
        }

        return ApiResult::success([
            'written_count' => count($written),
            'notifications' => $written,
        ], 'Company Client notifications processed');
    }
}

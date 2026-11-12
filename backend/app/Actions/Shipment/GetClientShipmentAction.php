<?php

namespace App\Actions\Shipment;

use App\Core\ApiResult;
use App\Models\Shipment;
use App\Models\User;

/**
 * Company-scoped shipment show for authenticated client users (C2.6-C5-D3).
 * Company id must come from the authenticated user — never from request input.
 * Does not reuse ops GetShipmentAction (driver/tracking eager loads).
 */
class GetClientShipmentAction
{
    public function execute(User $user, string $shipmentId): ApiResult
    {
        $companyId = $user->company_id;
        if ($companyId === null) {
            return ApiResult::error('Forbidden', 403);
        }

        // SoftDeletes default + forCustomer scope: foreign / deleted / missing → null → 404.
        $shipment = Shipment::query()
            ->forCustomer($companyId)
            ->with([
                'status',
                'customerCompany',
                'finalPriceBreakdown.variances',
                'finalPriceBreakdown.submissionPriceBreakdown',
            ])
            ->find($shipmentId);

        if ($shipment === null) {
            return ApiResult::notFound('Shipment not found');
        }

        return ApiResult::success($shipment, 'Shipment retrieved successfully');
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Invoice\DownloadOpsMonthlyInvoicePdfAction;
use App\Actions\Invoice\FinalizeMonthlyInvoiceAction;
use App\Actions\Invoice\GetMonthlyInvoiceAction;
use App\Actions\Invoice\QueryMonthlyInvoicesAction;
use App\Actions\Invoice\VoidMonthlyInvoiceAction;
use App\Core\ApiResponse;
use App\Http\Requests\Invoice\FinalizeMonthlyInvoiceRequest;
use App\Http\Requests\Invoice\QueryMonthlyInvoicesRequest;
use App\Http\Requests\Invoice\VoidMonthlyInvoiceRequest;
use App\Http\Resources\Invoice\OpsMonthlyInvoiceCollection;
use App\Http\Resources\Invoice\OpsMonthlyInvoiceResource;
use App\Models\MonthlyInvoice;
use App\Support\InvoicePdfResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ops monthly invoice read API (Phase 6c).
 */
class MonthlyInvoiceController extends Controller
{
    public function index(
        QueryMonthlyInvoicesRequest $request,
        QueryMonthlyInvoicesAction $action,
    ): JsonResponse {
        $this->authorize('viewAny', MonthlyInvoice::class);

        $result = $action->execute($request->validated());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new OpsMonthlyInvoiceCollection($result->data),
            $result->message,
        );
    }

    public function show(
        MonthlyInvoice $monthlyInvoice,
        GetMonthlyInvoiceAction $action,
    ): JsonResponse {
        $this->authorize('view', $monthlyInvoice);

        $result = $action->execute($monthlyInvoice->id);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new OpsMonthlyInvoiceResource($result->data),
            $result->message,
        );
    }

    public function pdf(
        MonthlyInvoice $monthlyInvoice,
        DownloadOpsMonthlyInvoicePdfAction $action,
    ): JsonResponse|Response {
        $this->authorize('view', $monthlyInvoice);

        $result = $action->execute($monthlyInvoice->id);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return InvoicePdfResponse::fromResult($result->data);
    }

    public function finalize(
        MonthlyInvoice $monthlyInvoice,
        FinalizeMonthlyInvoiceRequest $request,
        FinalizeMonthlyInvoiceAction $action,
    ): JsonResponse {
        $this->authorize('finalize', $monthlyInvoice);

        $result = $action->execute($monthlyInvoice->id);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success(
            new OpsMonthlyInvoiceResource($result->data),
            $result->message,
        );
    }

    public function void(
        MonthlyInvoice $monthlyInvoice,
        VoidMonthlyInvoiceRequest $request,
        VoidMonthlyInvoiceAction $action,
    ): JsonResponse {
        $this->authorize('void', $monthlyInvoice);

        $result = $action->execute($monthlyInvoice->id);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success(
            new OpsMonthlyInvoiceResource($result->data),
            $result->message,
        );
    }
}

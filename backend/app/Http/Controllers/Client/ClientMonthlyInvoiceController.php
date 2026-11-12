<?php

namespace App\Http\Controllers\Client;

use App\Actions\Invoice\DownloadClientMonthlyInvoicePdfAction;
use App\Actions\Invoice\GetClientMonthlyInvoiceAction;
use App\Actions\Invoice\QueryClientMonthlyInvoicesAction;
use App\Core\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\QueryClientMonthlyInvoicesRequest;
use App\Http\Requests\Client\ShowClientMonthlyInvoiceRequest;
use App\Http\Resources\Client\ClientMonthlyInvoiceListCollection;
use App\Http\Resources\Client\ClientMonthlyInvoiceShowResource;
use App\Support\InvoicePdfResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Client monthly invoice read API (Phase 6c).
 */
class ClientMonthlyInvoiceController extends Controller
{
    public function index(
        QueryClientMonthlyInvoicesRequest $request,
        QueryClientMonthlyInvoicesAction $action,
    ): JsonResponse {
        $this->authorize('client.monthlyInvoice.viewAny');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $request->validated());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new ClientMonthlyInvoiceListCollection($result->data),
            $result->message,
        );
    }

    public function show(
        ShowClientMonthlyInvoiceRequest $request,
        int|string $monthlyInvoice,
        GetClientMonthlyInvoiceAction $action,
    ): JsonResponse {
        $this->authorize('client.monthlyInvoice.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $monthlyInvoice);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new ClientMonthlyInvoiceShowResource($result->data),
            $result->message,
        );
    }

    public function pdf(
        ShowClientMonthlyInvoiceRequest $request,
        int|string $monthlyInvoice,
        DownloadClientMonthlyInvoicePdfAction $action,
    ): JsonResponse|Response {
        $this->authorize('client.monthlyInvoice.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $monthlyInvoice);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return InvoicePdfResponse::fromResult($result->data);
    }

    public function checkout(
        ShowClientMonthlyInvoiceRequest $request,
        int|string $monthlyInvoice,
        \App\Actions\Invoice\CreateInvoiceStripeCheckoutSessionAction $action,
    ): JsonResponse {
        $this->authorize('client.monthlyInvoice.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $monthlyInvoice);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function paymentStatus(
        ShowClientMonthlyInvoiceRequest $request,
        int|string $monthlyInvoice,
        GetClientMonthlyInvoiceAction $action,
    ): JsonResponse {
        $this->authorize('client.monthlyInvoice.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $monthlyInvoice);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        /** @var \App\Models\MonthlyInvoice $invoice */
        $invoice = $result->data;

        return ApiResponse::success([
            'invoice_id' => $invoice->id,
            'status' => $invoice->status->value,
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'payment_pending' => false,
            'stripe_checkout_session_id' => $invoice->stripe_checkout_session_id,
            'stripe_payment_intent_id' => $invoice->stripe_payment_intent_id,
        ], 'Payment status retrieved successfully');
    }
}

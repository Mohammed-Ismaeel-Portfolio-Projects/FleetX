<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\Invoice\FulfillStripeInvoicePaymentAction;
use App\Core\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Invoice\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function handle(
        Request $request,
        StripePaymentService $stripePaymentService,
        FulfillStripeInvoicePaymentAction $action,
    ): JsonResponse {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        if (! $sigHeader || ! $payload) {
            return ApiResponse::error('Missing Stripe signature header or payload', 400);
        }

        try {
            $event = $stripePaymentService->constructWebhookEvent($payload, $sigHeader);
            $eventArray = json_decode(json_encode($event->jsonSerialize()), true);
        } catch (\Throwable $e) {
            return ApiResponse::error('Invalid Stripe signature: ' . $e->getMessage(), 400);
        }

        $result = $action->execute($eventArray);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(null, $result->message);
    }
}

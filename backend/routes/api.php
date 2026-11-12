<?php

use App\Http\Controllers\Admin\AdminPricingController;
use App\Http\Controllers\Admin\AdminPricingPolicyController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\Client\ClientGeocodeController;
use App\Http\Controllers\Client\ClientMonthlyInvoiceController;
use App\Http\Controllers\Client\ClientNotificationController;
use App\Http\Controllers\Client\ClientPricingController;
use App\Http\Controllers\Client\ClientShipmentController;
use App\Http\Controllers\Client\ClientShipmentRequestController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dispatcher\TrackingSessionController;
use App\Http\Controllers\Driver\DriverAvailabilityController;
use App\Http\Controllers\Driver\DriverDeviceController;
use App\Http\Controllers\Driver\DriverHistoryController;
use App\Http\Controllers\Driver\DriverNotificationController;
use App\Http\Controllers\Driver\DriverWorkController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\DriverVerificationChallengeController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShipmentRequestController;
use App\Http\Controllers\TrailerController;
use App\Http\Controllers\TrackingLogController;
use App\Http\Controllers\LiveFleetController;
use App\Http\Controllers\MonthlyInvoiceController;
use App\Http\Controllers\TrackingStateController;
use App\Http\Controllers\StatusLookupController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API Version 1 (current)
Route::prefix('v1')->group(function () {
    
    // Public Webhooks (Outside Auth Middleware)
    Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
        ->name('v1.webhooks.stripe');

    // Authentication Routes
    Route::prefix('auth')->group(function () {
        // Public routes (Enterprise Login Only — public self-registration disabled)
        Route::post('login', [AuthController::class, 'login'])->name('v1.auth.login');

        // Protected routes (requires valid, non-blacklisted JWT)
        Route::middleware('auth:api')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('v1.auth.logout');
            Route::post('refresh', [AuthController::class, 'refresh'])->name('v1.auth.refresh');
            Route::get('me', [AuthController::class, 'me'])->name('v1.auth.me');
            Route::get('identity', [AuthController::class, 'identity'])->name('v1.auth.identity');
        });
    });

    // Protected Routes (requires authentication)
    Route::middleware('auth:api')->group(function () {
        
        // Dashboard Routes
        Route::prefix('dashboard')->group(function () {
            Route::get('/kpi', [DashboardController::class, 'kpi'])->name('v1.dashboard.kpi');
            Route::get('/activity', [DashboardController::class, 'activity'])->name('v1.dashboard.activity');
            Route::get('/performance', [DashboardController::class, 'performance'])->name('v1.dashboard.performance');
        });

        // Status lookup (read-only, for frontend selectors)
        Route::get('/statuses', [StatusLookupController::class, 'index'])->name('v1.statuses.index');

        // Company Routes
        Route::prefix('companies')->group(function () {
            Route::get('/', [CompanyController::class, 'index'])->name('v1.companies.index');
            Route::post('/', [CompanyController::class, 'store'])->name('v1.companies.store');
            Route::get('/{company}', [CompanyController::class, 'show'])->name('v1.companies.show');
            Route::put('/{company}', [CompanyController::class, 'update'])->name('v1.companies.update');
            Route::patch('/{company}/status', [CompanyController::class, 'toggleStatus'])->name('v1.companies.toggle-status');
            Route::delete('/{company}', [CompanyController::class, 'destroy'])->name('v1.companies.destroy');
            Route::patch('/{company}/restore', [CompanyController::class, 'restore'])->name('v1.companies.restore');
        });

        // Vehicle Routes
        Route::prefix('vehicles')->group(function () {
            Route::get('/', [VehicleController::class, 'index'])->name('v1.vehicles.index');
            Route::get('/availability', [VehicleController::class, 'availability'])->name('v1.vehicles.availability');
            Route::get('/fleet-kpis', [VehicleController::class, 'fleetKPIs'])->name('v1.vehicles.fleet-kpis');
            Route::post('/', [VehicleController::class, 'store'])->name('v1.vehicles.store');
            Route::get('/{vehicle}', [VehicleController::class, 'show'])->name('v1.vehicles.show');
            Route::put('/{vehicle}', [VehicleController::class, 'update'])->name('v1.vehicles.update');
            Route::patch('/{vehicle}/status', [VehicleController::class, 'toggleStatus'])->name('v1.vehicles.toggle-status');
            Route::delete('/{vehicle}', [VehicleController::class, 'destroy'])->name('v1.vehicles.destroy');
            Route::patch('/{vehicle}/restore', [VehicleController::class, 'restore'])->name('v1.vehicles.restore');
            Route::post('/{vehicle}/pair-driver', [VehicleController::class, 'pairDriver'])->name('v1.vehicles.pair-driver');
            Route::post('/{vehicle}/unpair-driver', [VehicleController::class, 'unpairDriver'])->name('v1.vehicles.unpair-driver');
        });

        // Trailer Routes
        Route::prefix('trailers')->group(function () {
            Route::get('/', [TrailerController::class, 'index'])->name('v1.trailers.index');
            Route::post('/', [TrailerController::class, 'store'])->name('v1.trailers.store');
            Route::get('/{trailer}', [TrailerController::class, 'show'])->name('v1.trailers.show');
            Route::put('/{trailer}', [TrailerController::class, 'update'])->name('v1.trailers.update');
            Route::patch('/{trailer}/status', [TrailerController::class, 'toggleStatus'])->name('v1.trailers.toggle-status');
            Route::delete('/{trailer}', [TrailerController::class, 'destroy'])->name('v1.trailers.destroy');
            Route::patch('/{trailer}/restore', [TrailerController::class, 'restore'])->name('v1.trailers.restore');
        });

        // Driver Routes
        Route::prefix('drivers')->group(function () {
            Route::get('/', [DriverController::class, 'index'])->name('v1.drivers.index');
            Route::get('/availability', [DriverController::class, 'availability'])->name('v1.drivers.availability');
            Route::get('/kpis', [DriverController::class, 'kpis'])->name('v1.drivers.kpis');
            // DRIVER-EVAL-08A — batch compact overall (must precede /{driver})
            Route::get('/performance/summary', [\App\Http\Controllers\DriverPerformanceController::class, 'summary'])
                ->name('v1.drivers.performance.summary');
            Route::post('/', [DriverController::class, 'store'])->name('v1.drivers.store');
            Route::get('/{driver}', [DriverController::class, 'show'])->name('v1.drivers.show');
            Route::get('/{driver}/performance', [\App\Http\Controllers\DriverPerformanceController::class, 'show'])
                ->name('v1.drivers.performance.show');
            Route::get('/{driver}/performance/evaluations', [\App\Http\Controllers\DriverPerformanceController::class, 'evaluations'])
                ->name('v1.drivers.performance.evaluations');
            Route::get('/{driver}/performance/evaluations/{evaluation}', [\App\Http\Controllers\DriverPerformanceController::class, 'evaluation'])
                ->name('v1.drivers.performance.evaluations.show');
            Route::put('/{driver}', [DriverController::class, 'update'])->name('v1.drivers.update');
            Route::patch('/{driver}/status', [DriverController::class, 'toggleStatus'])->name('v1.drivers.toggle-status');
            Route::delete('/{driver}', [DriverController::class, 'destroy'])->name('v1.drivers.destroy');
            Route::patch('/{driver}/restore', [DriverController::class, 'restore'])->name('v1.drivers.restore');
        });

        // Assignment Routes
        Route::prefix('assignments')->group(function () {
            Route::get('/', [AssignmentController::class, 'index'])->name('v1.assignments.index');
            Route::post('/', [AssignmentController::class, 'store'])->name('v1.assignments.store');
            // DRIVER-EVAL-08A — current evaluation (Admin+Dispatcher via evaluation gate, not assignment CRUD)
            Route::get('/{assignment}/evaluation/current', [\App\Http\Controllers\AssignmentEvaluationCurrentController::class, 'show'])
                ->name('v1.assignments.evaluation.current');
            Route::get('/{assignment}', [AssignmentController::class, 'show'])->name('v1.assignments.show');
            Route::put('/{assignment}', [AssignmentController::class, 'update'])->name('v1.assignments.update');
            Route::patch('/{assignment}/status', [AssignmentController::class, 'changeStatus'])->name('v1.assignments.change-status');
            Route::post('/{assignment}/engine-enroll', [AssignmentController::class, 'enrollInEngine'])->name('v1.assignments.engine-enroll');
            Route::delete('/{assignment}', [AssignmentController::class, 'destroy'])->name('v1.assignments.destroy');
            Route::patch('/{assignment}/restore', [AssignmentController::class, 'restore'])->name('v1.assignments.restore');
        });

        // Driver work read (D2) — dedicated; not ops /assignments
        Route::prefix('driver')->group(function () {
            Route::get('/work/active', [DriverWorkController::class, 'active'])
                ->name('v1.driver.work.active');
            Route::get('/assignments/history', [DriverHistoryController::class, 'index'])
                ->name('v1.driver.assignments.history');
            Route::get('/assignments/{assignment}/summary', [DriverHistoryController::class, 'summary'])
                ->name('v1.driver.assignments.summary');

            Route::get('/performance', [\App\Http\Controllers\Driver\DriverSelfPerformanceController::class, 'show'])
                ->name('v1.driver.performance.show');
            Route::get('/performance/evaluations', [\App\Http\Controllers\Driver\DriverSelfPerformanceController::class, 'evaluations'])
                ->name('v1.driver.performance.evaluations');
            Route::get('/performance/evaluations/{evaluation}', [\App\Http\Controllers\Driver\DriverSelfPerformanceController::class, 'evaluation'])
                ->name('v1.driver.performance.evaluations.show');
            // DRIVER-EVAL-08A — current trip evaluation (own assignment only)
            Route::get('/assignments/{assignment}/evaluation/current', [\App\Http\Controllers\Driver\DriverSelfPerformanceController::class, 'currentAssignmentEvaluation'])
                ->name('v1.driver.assignments.evaluation.current');

            Route::get('/assignments/{assignment}', [DriverWorkController::class, 'show'])
                ->name('v1.driver.assignments.show');
            Route::post('/assignments/{assignment}/acknowledge', [DriverWorkController::class, 'acknowledge'])
                ->name('v1.driver.assignments.acknowledge');
            Route::post('/assignments/{assignment}/start', [DriverWorkController::class, 'start'])
                ->name('v1.driver.assignments.start');
            Route::post('/assignments/{assignment}/pickup/arrive', [DriverWorkController::class, 'pickupArrive'])
                ->name('v1.driver.assignments.pickup.arrive');
            Route::post('/assignments/{assignment}/pickup/loading', [DriverWorkController::class, 'pickupLoading'])
                ->name('v1.driver.assignments.pickup.loading');
            Route::post('/assignments/{assignment}/pickup/loading-complete', [DriverWorkController::class, 'pickupLoadingComplete'])
                ->name('v1.driver.assignments.pickup.loading-complete');
            Route::post('/assignments/{assignment}/delivery/arrive', [DriverWorkController::class, 'deliveryArrive'])
                ->name('v1.driver.assignments.delivery.arrive');
            Route::post('/assignments/{assignment}/delivery/unloading', [DriverWorkController::class, 'deliveryUnloading'])
                ->name('v1.driver.assignments.delivery.unloading');
            Route::post('/assignments/{assignment}/delivery/unloading-complete', [DriverWorkController::class, 'deliveryUnloadingComplete'])
                ->name('v1.driver.assignments.delivery.unloading-complete');
            Route::post('/assignments/{assignment}/pickup/verify', [DriverWorkController::class, 'pickupVerify'])
                ->name('v1.driver.assignments.pickup.verify');
            Route::post('/assignments/{assignment}/delivery/verify', [DriverWorkController::class, 'deliveryVerify'])
                ->name('v1.driver.assignments.delivery.verify');
            Route::post('/assignments/{assignment}/tracking', [DriverWorkController::class, 'submitTracking'])
                ->name('v1.driver.assignments.tracking');
            Route::get('/assignments/{assignment}/trip-map', [DriverWorkController::class, 'tripMap'])
                ->name('v1.driver.assignments.trip-map');
            Route::get('/assignments/{assignment}/outcome', [DriverWorkController::class, 'outcome'])
                ->name('v1.driver.assignments.outcome');
            Route::post('/assignments/{assignment}/pod', [DriverWorkController::class, 'submitPod'])
                ->name('v1.driver.assignments.pod');
            Route::post('/assignments/{assignment}/complete', [DriverWorkController::class, 'complete'])
                ->name('v1.driver.assignments.complete');
            Route::post('/assignments/{assignment}/issues', [DriverWorkController::class, 'createIssue'])
                ->name('v1.driver.assignments.issues');
            Route::post('/assignments/{assignment}/fail-delivery', [DriverWorkController::class, 'failDelivery'])
                ->name('v1.driver.assignments.fail-delivery');

            Route::get('/availability', [DriverAvailabilityController::class, 'show'])
                ->name('v1.driver.availability.show');
            Route::patch('/availability', [DriverAvailabilityController::class, 'update'])
                ->name('v1.driver.availability.update');

            Route::post('/devices/register', [DriverDeviceController::class, 'register'])
                ->name('v1.driver.devices.register');
            Route::post('/devices/unregister', [DriverDeviceController::class, 'unregister'])
                ->name('v1.driver.devices.unregister');
            Route::get('/devices', [DriverDeviceController::class, 'index'])
                ->name('v1.driver.devices.index');

            Route::get('/notifications/unread-count', [DriverNotificationController::class, 'unreadCount'])
                ->name('v1.driver.notifications.unread-count');
            Route::post('/notifications/read-all', [DriverNotificationController::class, 'markAllRead'])
                ->name('v1.driver.notifications.read-all');
            Route::post('/notifications/{notification}/read', [DriverNotificationController::class, 'markRead'])
                ->name('v1.driver.notifications.read');
            Route::get('/notifications', [DriverNotificationController::class, 'index'])
                ->name('v1.driver.notifications.index');
        });

        // Client shipment list/show/tracking (C2.6-C2 / C2.6-C5-D3 / C2.7-C) — dedicated; not ops /shipments
        // Client notifications inbox (C2.8-C) — dedicated; not Issues/Alerts or messaging markRead
        Route::prefix('client')->group(function () {
            Route::get('/shipments', [ClientShipmentController::class, 'index'])
                ->name('v1.client.shipments.index');
            Route::get('/shipments/{shipment}/verification-qr', [ClientShipmentController::class, 'verificationQr'])
                ->name('v1.client.shipments.verification-qr');
            Route::get('/shipments/{shipment}/pod/status', [ClientShipmentController::class, 'podStatus'])
                ->name('v1.client.shipments.pod.status');
            Route::get('/shipments/{shipment}/pod', [ClientShipmentController::class, 'pod'])
                ->name('v1.client.shipments.pod');
            Route::get('/shipments/{shipment}/delivery-proof', [ClientShipmentController::class, 'deliveryProof'])
                ->name('v1.client.shipments.delivery-proof');
            Route::get('/shipments/{shipment}/delivery-proof/certificate', [ClientShipmentController::class, 'certificate'])
                ->name('v1.client.shipments.delivery-proof.certificate');
            Route::get('/shipments/{shipment}/tracking', [ClientShipmentController::class, 'tracking'])
                ->name('v1.client.shipments.tracking');
            Route::get('/shipments/{shipment}', [ClientShipmentController::class, 'show'])
                ->name('v1.client.shipments.show');

            Route::get('/shipment-requests', [ClientShipmentRequestController::class, 'index'])
                ->name('v1.client.shipment-requests.index');
            Route::post('/shipment-requests', [ClientShipmentRequestController::class, 'store'])
                ->name('v1.client.shipment-requests.store');
            Route::patch('/shipment-requests/{shipmentOrder}', [ClientShipmentRequestController::class, 'update'])
                ->name('v1.client.shipment-requests.update');
            Route::post('/shipment-requests/{shipmentOrder}/submit', [ClientShipmentRequestController::class, 'submit'])
                ->name('v1.client.shipment-requests.submit');
            Route::post('/shipment-requests/{shipmentOrder}/resubmit', [ClientShipmentRequestController::class, 'resubmit'])
                ->name('v1.client.shipment-requests.resubmit');
            Route::get('/shipment-requests/{shipmentOrder}', [ClientShipmentRequestController::class, 'show'])
                ->name('v1.client.shipment-requests.show');
            Route::post('/pricing/calculate', [ClientPricingController::class, 'calculate'])
                ->name('v1.client.pricing.calculate');

            Route::post('/geocode/reverse', [ClientGeocodeController::class, 'reverse'])
                ->name('v1.client.geocode.reverse');
            Route::post('/geocode/search', [ClientGeocodeController::class, 'search'])
                ->name('v1.client.geocode.search');

            Route::get('/notifications/unread-count', [ClientNotificationController::class, 'unreadCount'])
                ->name('v1.client.notifications.unread-count');
            Route::post('/notifications/read-all', [ClientNotificationController::class, 'markAllRead'])
                ->name('v1.client.notifications.read-all');
            Route::patch('/notifications/{notification}/read', [ClientNotificationController::class, 'markRead'])
                ->name('v1.client.notifications.read');
            Route::get('/notifications', [ClientNotificationController::class, 'index'])
                ->name('v1.client.notifications.index');

            Route::get('/monthly-invoices', [ClientMonthlyInvoiceController::class, 'index'])
                ->name('v1.client.monthly-invoices.index');
            Route::post('/monthly-invoices/{monthlyInvoice}/checkout', [ClientMonthlyInvoiceController::class, 'checkout'])
                ->name('v1.client.monthly-invoices.checkout');
            Route::get('/monthly-invoices/{monthlyInvoice}/payment-status', [ClientMonthlyInvoiceController::class, 'paymentStatus'])
                ->name('v1.client.monthly-invoices.payment-status');
            Route::get('/monthly-invoices/{monthlyInvoice}/pdf', [ClientMonthlyInvoiceController::class, 'pdf'])
                ->name('v1.client.monthly-invoices.pdf');
            Route::get('/monthly-invoices/{monthlyInvoice}', [ClientMonthlyInvoiceController::class, 'show'])
                ->name('v1.client.monthly-invoices.show');
        });

        // Ops monthly invoice read (Phase 6c) + lifecycle (Phase 6e)
        Route::get('/monthly-invoices', [MonthlyInvoiceController::class, 'index'])
            ->name('v1.monthly-invoices.index');
        Route::post('/monthly-invoices/{monthlyInvoice}/finalize', [MonthlyInvoiceController::class, 'finalize'])
            ->name('v1.monthly-invoices.finalize');
        Route::post('/monthly-invoices/{monthlyInvoice}/void', [MonthlyInvoiceController::class, 'void'])
            ->name('v1.monthly-invoices.void');
        Route::get('/monthly-invoices/{monthlyInvoice}/pdf', [MonthlyInvoiceController::class, 'pdf'])
            ->name('v1.monthly-invoices.pdf');
        Route::get('/monthly-invoices/{monthlyInvoice}', [MonthlyInvoiceController::class, 'show'])
            ->name('v1.monthly-invoices.show');

        // Ops Shipment Request read + review (C2.9-B2.1 / C2.9-B2.2) — admin/dispatcher
        Route::get('/shipment-requests', [ShipmentRequestController::class, 'index'])
            ->name('v1.shipment-requests.index');
        Route::post('/shipment-requests/{shipmentOrder}/start-review', [ShipmentRequestController::class, 'startReview'])
            ->name('v1.shipment-requests.start-review');
        Route::post('/shipment-requests/{shipmentOrder}/request-changes', [ShipmentRequestController::class, 'requestChanges'])
            ->name('v1.shipment-requests.request-changes');
        Route::post('/shipment-requests/{shipmentOrder}/approve', [ShipmentRequestController::class, 'approve'])
            ->name('v1.shipment-requests.approve');
        Route::post('/shipment-requests/{shipmentOrder}/reject', [ShipmentRequestController::class, 'reject'])
            ->name('v1.shipment-requests.reject');
        Route::post('/shipment-requests/{shipmentOrder}/convert', [ShipmentRequestController::class, 'convert'])
            ->name('v1.shipment-requests.convert');
        Route::get('/shipment-requests/{shipmentOrder}', [ShipmentRequestController::class, 'show'])
            ->name('v1.shipment-requests.show');

        Route::post('/pricing/calculate', [AdminPricingController::class, 'calculate'])
            ->name('v1.pricing.calculate');

        // Admin pricing policy governance (PRICING-02 read, PRICING-04 mutation); no calculation.
        Route::prefix('admin/pricing/policies')->group(function () {
            Route::get('/', [AdminPricingPolicyController::class, 'index'])
                ->name('v1.admin.pricing.policies.index');
            Route::post('/', [AdminPricingPolicyController::class, 'store'])
                ->name('v1.admin.pricing.policies.store');
            Route::get('/timeline', [AdminPricingPolicyController::class, 'timeline'])
                ->name('v1.admin.pricing.policies.timeline');
            Route::get('/active', [AdminPricingPolicyController::class, 'active'])
                ->name('v1.admin.pricing.policies.active');
            Route::get('/{pricingPolicy}', [AdminPricingPolicyController::class, 'show'])
                ->name('v1.admin.pricing.policies.show');
            Route::put('/{pricingPolicy}', [AdminPricingPolicyController::class, 'update'])
                ->name('v1.admin.pricing.policies.update');
            Route::post('/{pricingPolicy}/activate', [AdminPricingPolicyController::class, 'activate'])
                ->name('v1.admin.pricing.policies.activate');
            Route::post('/{pricingPolicy}/archive', [AdminPricingPolicyController::class, 'archive'])
                ->name('v1.admin.pricing.policies.archive');
        });

        // Shipment Routes (ops — admin/dispatcher)
        Route::prefix('shipments')->group(function () {
            Route::get('/', [ShipmentController::class, 'index'])->name('v1.shipments.index');
            Route::get('/queue', [ShipmentController::class, 'queue'])->name('v1.shipments.queue');
            Route::get('/kpis', [ShipmentController::class, 'kpis'])->name('v1.shipments.kpis');
            Route::post('/', [ShipmentController::class, 'store'])->name('v1.shipments.store');
            Route::get('/{shipment}', [ShipmentController::class, 'show'])->name('v1.shipments.show');
            Route::put('/{shipment}', [ShipmentController::class, 'update'])->name('v1.shipments.update');
            Route::patch('/{shipment}/status', [ShipmentController::class, 'changeStatus'])->name('v1.shipments.change-status');
            Route::post('/{shipment}/operational-actuals', [ShipmentController::class, 'captureOperationalActuals'])
                ->name('v1.shipments.operational-actuals.store');
            Route::post('/{shipment}/finalize-price', [ShipmentController::class, 'finalizePrice'])
                ->name('v1.shipments.finalize-price');
            Route::post('/{shipment}/driver-verification-challenges', [DriverVerificationChallengeController::class, 'issue'])
                ->name('v1.shipments.driver-verification-challenges.issue');
            Route::get('/{shipment}/driver-verification-challenges/active', [DriverVerificationChallengeController::class, 'active'])
                ->name('v1.shipments.driver-verification-challenges.active');
            Route::get('/{shipment}/delivery-proof', [ShipmentController::class, 'deliveryProof'])
                ->name('v1.shipments.delivery-proof');
            Route::get('/{shipment}/delivery-proof/certificate', [ShipmentController::class, 'certificate'])
                ->name('v1.shipments.delivery-proof.certificate');
            Route::get('/{shipment}/pod', [ShipmentController::class, 'pod'])
                ->name('v1.shipments.pod');
            Route::delete('/{shipment}', [ShipmentController::class, 'destroy'])->name('v1.shipments.destroy');
            Route::patch('/{shipment}/restore', [ShipmentController::class, 'restore'])->name('v1.shipments.restore');
        });

        // Route Routes
        Route::prefix('shipments/{shipment}/route')->group(function () {
            Route::get('/', [RouteController::class, 'show'])->name('v1.shipments.route.show');
        });

        // Tracking Routes
        Route::prefix('tracking-logs')->group(function () {
            Route::get('/', [TrackingLogController::class, 'index'])->name('v1.tracking-logs.index');
            Route::post('/', [TrackingLogController::class, 'store'])->name('v1.tracking-logs.store');
            Route::get('/{trackingLog}', [TrackingLogController::class, 'show'])->name('v1.tracking-logs.show');
            Route::put('/{trackingLog}', [TrackingLogController::class, 'update'])->name('v1.tracking-logs.update');
            Route::delete('/{trackingLog}', [TrackingLogController::class, 'destroy'])->name('v1.tracking-logs.destroy');
        });

        // Tracking State Routes
        Route::prefix('tracking-states')->group(function () {
            Route::get('/', [TrackingStateController::class, 'index'])->name('v1.tracking-states.index');
            Route::get('/{trackingState}', [TrackingStateController::class, 'show'])->name('v1.tracking-states.show');
        });

        // Dispatcher live fleet snapshot (one row per active vehicle).
        // Distinct from tracking-states (admin/history catalogue).
        Route::get('tracking/live-fleet', [LiveFleetController::class, 'index'])
            ->name('v1.tracking.live-fleet');

        // Dispatcher Tracking Session Routes
        Route::prefix('dispatcher/tracking-sessions')->group(function () {
            Route::get('/shipment-order/{shipmentOrder}', [TrackingSessionController::class, 'showForShipment'])->name('v1.dispatcher.tracking-sessions.show-for-shipment');
            Route::post('/start', [TrackingSessionController::class, 'start'])->name('v1.dispatcher.tracking-sessions.start');
            Route::post('/{session}/pause', [TrackingSessionController::class, 'pause'])->name('v1.dispatcher.tracking-sessions.pause');
            Route::post('/{session}/resume', [TrackingSessionController::class, 'resume'])->name('v1.dispatcher.tracking-sessions.resume');
            Route::post('/{session}/stop', [TrackingSessionController::class, 'stop'])->name('v1.dispatcher.tracking-sessions.stop');
            Route::post('/{session}/override', [TrackingSessionController::class, 'override'])->name('v1.dispatcher.tracking-sessions.override');
            Route::get('/{session}/events', [TrackingSessionController::class, 'events'])->name('v1.dispatcher.tracking-sessions.events');
        });


        // Messaging (Conversation / Participant / Message SSOT)
        Route::prefix('conversations')->group(function () {
            Route::get('/', [ConversationController::class, 'index'])->name('v1.conversations.index');
            Route::post('/', [ConversationController::class, 'store'])->name('v1.conversations.store');
            Route::get('/{conversation}', [ConversationController::class, 'show'])->name('v1.conversations.show');
            Route::get('/{conversation}/messages', [ConversationController::class, 'messages'])->name('v1.conversations.messages');
            Route::post('/{conversation}/messages', [ConversationController::class, 'sendMessage'])->name('v1.conversations.messages.store');
            Route::post('/{conversation}/read', [ConversationController::class, 'markRead'])->name('v1.conversations.read');
        });

        // Issue Routes
        Route::prefix('issues')->group(function () {
            Route::get('/', [IssueController::class, 'index'])->name('v1.issues.index');
            Route::post('/', [IssueController::class, 'store'])->name('v1.issues.store');
            Route::get('/{issue}', [IssueController::class, 'show'])->name('v1.issues.show');
            Route::put('/{issue}', [IssueController::class, 'update'])->name('v1.issues.update');
            Route::delete('/{issue}', [IssueController::class, 'destroy'])->name('v1.issues.destroy');
        });
    });

});

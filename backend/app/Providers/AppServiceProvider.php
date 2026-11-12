<?php

namespace App\Providers;

use App\Broadcasting\AuthorizedLogBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Services\Geocoding\GeocodeProviderInterface::class,
            \App\Services\Geocoding\GraphHopperGeocodingClient::class
        );

        $this->app->bind(
            \App\Services\Driver\PushNotificationService::class,
            \App\Services\Driver\ExpoPushNotificationService::class
        );

        $this->app->singleton(
            \App\Services\DriverEvaluation\Detection\DriverEvaluationDetectionService::class,
            function ($app) {
                $detectors = \App\Services\DriverEvaluation\Detection\DriverEvaluationDetectionService::defaultDetectors(
                    $app->make(\App\Services\DriverEvaluation\Detection\SpeedingDetector::class),
                    $app->make(\App\Services\DriverEvaluation\Detection\HarshBrakingDetector::class),
                    $app->make(\App\Services\DriverEvaluation\Detection\HarshAccelerationDetector::class),
                    $app->make(\App\Services\DriverEvaluation\Detection\SuddenStopDetector::class),
                    $app->make(\App\Services\DriverEvaluation\Detection\IdleStopDetector::class),
                    $app->make(\App\Services\DriverEvaluation\Detection\RouteDeviationEvaluationDetector::class),
                    $app->make(\App\Services\DriverEvaluation\Detection\TimingEvaluationDetector::class),
                    $app->make(\App\Services\DriverEvaluation\Detection\WorkflowEvaluationDetector::class),
                );

                return new \App\Services\DriverEvaluation\Detection\DriverEvaluationDetectionService(
                    $app->make(\App\Services\DriverEvaluation\AssignmentEvaluationPersistenceService::class),
                    $app->make(\App\Services\DriverEvaluation\Detection\EvaluationTelemetryLoader::class),
                    $app->make(\App\Services\DriverEvaluation\Scoring\DriverEvaluationScoringService::class),
                    $app->make(\App\Services\DriverEvaluation\Aggregation\DriverPerformanceAggregationService::class),
                    $detectors,
                );
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enforce channel authorization even when BROADCAST_CONNECTION=log.
        Broadcast::extend('log', function ($app) {
            return new AuthorizedLogBroadcaster($app->make(LoggerInterface::class));
        });

        // Super admin bypass for admin role — except explicit permission-checked abilities
        // and dispatcher-only operational abilities (ADMIN-07: Admin ≠ Dispatcher).
        $adminBypassExcludedAbilities = [
            'pricing.admin.calculate',
            'monthlyInvoice.finalize',
            'monthlyInvoice.void',
            // Assignment — operational dispatch; Admin UI removed (ADMIN-02).
            'assignment.view',
            'assignment.create',
            'assignment.update',
            'assignment.delete',
            'assignment.change-status',
            'assignment.restore',
            'assignment.force-delete',
            // Shipment execution mutations — Admin shipments are read-only (ADMIN-03).
            'shipment.create',
            'shipment.update',
            'shipment.change-status',
            // ShipmentOrder ops mutations — Dispatcher-only (ADMIN-07.6).
            'shipmentOrder.startReview',
            'shipmentOrder.requestChanges',
            'shipmentOrder.approve',
            'shipmentOrder.reject',
            'shipmentOrder.convert',
        ];

        Gate::before(function ($user, $ability, $arguments = []) use ($adminBypassExcludedAbilities) {
            if (in_array($ability, $adminBypassExcludedAbilities, true)) {
                return null;
            }

            if ($user->role?->name === 'admin' && $user->isFleetXInternal()) {
                // Policy checks use short method names (create, update, …) which would
                // otherwise hit the admin super-bypass before the policy/gate runs.
                $assignmentPolicyAbilities = [
                    'viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete', 'changeStatus',
                ];
                $shipmentDeniedPolicyAbilities = ['create', 'update', 'changeStatus'];
                $shipmentOrderDeniedPolicyAbilities = [
                    'startReview', 'requestChanges', 'approve', 'reject', 'convert',
                ];

                $subject = $arguments[0] ?? null;

                if (
                    in_array($ability, $assignmentPolicyAbilities, true)
                    && ($subject instanceof \App\Models\Assignment || $subject === \App\Models\Assignment::class)
                ) {
                    return false;
                }

                if (
                    in_array($ability, $shipmentDeniedPolicyAbilities, true)
                    && ($subject instanceof \App\Models\Shipment || $subject === \App\Models\Shipment::class)
                ) {
                    return false;
                }

                if (
                    in_array($ability, $shipmentOrderDeniedPolicyAbilities, true)
                    && ($subject instanceof \App\Models\ShipmentOrder || $subject === \App\Models\ShipmentOrder::class)
                ) {
                    return false;
                }

                return true;
            }
        });

        // Dashboard governance / operational KPIs (ADMIN-07 RBAC-05)
        Gate::define('dashboard.view', function ($user) {
            return $user->isFleetXInternal() && in_array($user->role?->name, ['admin', 'dispatcher'], true);
        });

        // Define specific company gates
        Gate::define('company.view', function ($user) {
            return in_array($user->role?->name, ['admin', 'dispatcher']);
        });

        Gate::define('company.create', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('company.update', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('company.delete', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('company.restore', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('company.toggle-status', function ($user) {
            return $user->role?->name === 'admin';
        });

        // Define specific vehicle gates
        Gate::define('vehicle.view', function ($user) {
            return in_array($user->role?->name, ['admin', 'dispatcher']);
        });

        Gate::define('vehicle.create', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('vehicle.update', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('vehicle.delete', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('vehicle.restore', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('vehicle.toggle-status', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('vehicle.pair-driver', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('vehicle.unpair-driver', function ($user) {
            return $user->role?->name === 'admin';
        });

        // Define specific driver gates (parity with vehicle.view for Dispatcher Ops)
        Gate::define('driver.view', function ($user) {
            return in_array($user->role?->name, ['admin', 'dispatcher']);
        });

        Gate::define('driver.create', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('driver.update', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('driver.delete', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('driver.restore', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('driver.toggle-status', function ($user) {
            return $user->role?->name === 'admin';
        });

        // Define tracking gates
        Gate::define('tracking.view', function ($user) {
            return in_array($user->role?->name, ['admin', 'dispatcher']);
        });

        Gate::define('tracking.create', function ($user) {
            return in_array($user->role?->name, ['admin', 'dispatcher']);
        });

        Gate::define('tracking.update', function ($user) {
            return in_array($user->role?->name, ['admin', 'dispatcher']);
        });

        Gate::define('tracking.delete', function ($user) {
            return $user->role?->name === 'admin';
        });

        // Define shipment gates (required by ShipmentPolicy / route endpoint)
        Gate::define('shipment.view', function ($user) {
            return in_array($user->role?->name, ['admin', 'dispatcher']);
        });

        Gate::define('shipment.create', function ($user) {
            return $user->role?->name === 'dispatcher';
        });

        Gate::define('shipment.update', function ($user) {
            return $user->role?->name === 'dispatcher';
        });

        Gate::define('shipment.delete', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('shipment.restore', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('shipment.force-delete', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('shipment.change-status', function ($user) {
            return $user->role?->name === 'dispatcher';
        });

        // Monthly invoice read (Phase 6c) — ops visibility
        Gate::define('monthlyInvoice.view', function ($user) {
            return in_array($user->role?->name, ['admin', 'dispatcher']);
        });

        Gate::define('monthlyInvoice.finalize', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('monthlyInvoice.void', function ($user) {
            return $user->role?->name === 'admin';
        });

        // Assignment gates (C2.9-C1.2) — dispatcher-only ops; Admin denied (ADMIN-07).
        Gate::define('assignment.view', function ($user) {
            return $user->role?->name === 'dispatcher';
        });

        Gate::define('assignment.create', function ($user) {
            return $user->role?->name === 'dispatcher';
        });

        Gate::define('assignment.update', function ($user) {
            return $user->role?->name === 'dispatcher';
        });

        Gate::define('assignment.delete', function ($user) {
            return $user->role?->name === 'dispatcher';
        });

        Gate::define('assignment.restore', function ($user) {
            return $user->role?->name === 'dispatcher';
        });

        Gate::define('assignment.force-delete', function ($user) {
            return $user->role?->name === 'dispatcher';
        });

        Gate::define('assignment.change-status', function ($user) {
            return $user->role?->name === 'dispatcher';
        });

        // Trailer gates (parity with vehicle.* — ADMIN-07 RBAC-08)
        Gate::define('trailer.view', function ($user) {
            return in_array($user->role?->name, ['admin', 'dispatcher'], true);
        });

        Gate::define('trailer.create', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('trailer.update', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('trailer.delete', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('trailer.restore', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('trailer.toggle-status', function ($user) {
            return $user->role?->name === 'admin';
        });

        // Driver-scoped work read (D2) — distinct from ops assignment.view.
        // Linked Driver employee required; instance ownership enforced in Driver actions.
        Gate::define('driver.work.view', function ($user) {
            if ($user->role?->name !== 'driver') {
                return false;
            }

            $user->loadMissing('driver');

            return $user->driver !== null;
        });

        // Client-scoped shipment list (C2.6-C2) — distinct from ops shipment.view
        Gate::define('client.shipment.viewAny', function ($user) {
            if ($user->role?->name !== 'client') {
                return false;
            }

            return $user->company_id !== null && $user->company !== null;
        });

        // Client-scoped shipment show (C2.6-C5-D3) — principal rules match viewAny;
        // instance ownership is enforced via scoped retrieval in GetClientShipmentAction.
        Gate::define('client.shipment.view', function ($user) {
            if ($user->role?->name !== 'client') {
                return false;
            }

            return $user->company_id !== null && $user->company !== null;
        });

        // Client-scoped Shipment Request list/show (C2.9-A3.4.1)
        Gate::define('client.shipmentRequest.viewAny', function ($user) {
            if ($user->role?->name !== 'client') {
                return false;
            }

            return $user->company_id !== null && $user->company !== null;
        });

        Gate::define('client.shipmentRequest.view', function ($user) {
            if ($user->role?->name !== 'client') {
                return false;
            }

            return $user->company_id !== null && $user->company !== null;
        });

        Gate::define('client.shipmentRequest.create', function ($user) {
            if ($user->role?->name !== 'client') {
                return false;
            }

            return $user->company_id !== null && $user->company !== null;
        });

        Gate::define('client.shipmentRequest.update', function ($user) {
            if ($user->role?->name !== 'client') {
                return false;
            }

            return $user->company_id !== null && $user->company !== null;
        });

        Gate::define('client.shipmentRequest.submit', function ($user) {
            if ($user->role?->name !== 'client') {
                return false;
            }

            return $user->company_id !== null && $user->company !== null;
        });

        Gate::define('client.shipmentRequest.resubmit', function ($user) {
            if ($user->role?->name !== 'client') {
                return false;
            }

            return $user->company_id !== null && $user->company !== null;
        });

        // Client-scoped monthly invoice read (Phase 6c)
        Gate::define('client.monthlyInvoice.viewAny', function ($user) {
            if ($user->role?->name !== 'client') {
                return false;
            }

            return $user->company_id !== null && $user->company !== null;
        });

        Gate::define('client.monthlyInvoice.view', function ($user) {
            if ($user->role?->name !== 'client') {
                return false;
            }

            return $user->company_id !== null && $user->company !== null;
        });

        Gate::define('client.pricing.calculate', function ($user) {
            if ($user->role?->name !== 'client') {
                return false;
            }

            return $user->company_id !== null && $user->company !== null;
        });

        Gate::define('pricing.calculate', function ($user) {
            return in_array($user->role?->name, ['admin', 'dispatcher'], true);
        });

        Gate::define('pricing.admin.calculate', function ($user) {
            return $user->role?->name === 'admin';
        });

        // Pricing policy configuration read (PRICING-02) — Admin governance only.
        Gate::define('pricing.policy.viewAny', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('pricing.policy.view', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('pricing.policy.create', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('pricing.policy.update', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('pricing.policy.activate', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('pricing.policy.archive', function ($user) {
            return $user->role?->name === 'admin';
        });

        // Driver Evaluation domain (DRIVER-EVAL-02) — read/manage only; no scoring yet.
        Gate::define('driverEvaluation.viewAny', function ($user) {
            return in_array($user->role?->name, ['admin', 'dispatcher'], true);
        });

        Gate::define('driverEvaluation.manage', function ($user) {
            return $user->role?->name === 'admin';
        });

        // Ops Shipment Request read (C2.9-B2.1) — governance/ops visibility for admin/dispatcher.
        // Mutations are dispatcher-only (ADMIN-07.6).
        Gate::define('shipmentOrder.viewAny', function ($user) {
            return in_array($user->role?->name, ['admin', 'dispatcher'], true);
        });

        Gate::define('shipmentOrder.view', function ($user) {
            return in_array($user->role?->name, ['admin', 'dispatcher'], true);
        });

        Gate::define('shipmentOrder.startReview', function ($user) {
            return $user->role?->name === 'dispatcher';
        });

        Gate::define('shipmentOrder.requestChanges', function ($user) {
            return $user->role?->name === 'dispatcher';
        });

        Gate::define('shipmentOrder.approve', function ($user) {
            return $user->role?->name === 'dispatcher';
        });

        Gate::define('shipmentOrder.reject', function ($user) {
            return $user->role?->name === 'dispatcher';
        });

        Gate::define('shipmentOrder.convert', function ($user) {
            return $user->role?->name === 'dispatcher';
        });

        // Client notification inbox (C2.8-C) — principal rules match client.shipment.viewAny;
        // instance ownership is enforced via scoped queries (user_id = auth id).
        Gate::define('client.notification.viewAny', function ($user) {
            if ($user->role?->name !== 'client') {
                return false;
            }

            return $user->company_id !== null && $user->company !== null;
        });

        Gate::define('client.notification.update', function ($user) {
            if ($user->role?->name !== 'client') {
                return false;
            }

            return $user->company_id !== null && $user->company !== null;
        });

        // Issue gates (parity with shipment.view/update for Dispatcher Alerts Center)
        // IssuePolicy previously called undefined abilities ("view issues" / "update issues").
        Gate::define('issue.view', function ($user) {
            return in_array($user->role?->name, ['admin', 'dispatcher']);
        });

        Gate::define('issue.create', function ($user) {
            return $user->role?->name === 'admin';
        });

        Gate::define('issue.update', function ($user) {
            return in_array($user->role?->name, ['admin', 'dispatcher']);
        });

        Gate::define('issue.delete', function ($user) {
            return $user->role?->name === 'admin';
        });

        // Messaging — authenticated roles may list/create scoped threads; manage = dispatcher/admin
        Gate::define('conversation.viewAny', function ($user) {
            return in_array($user->role?->name, ['admin', 'dispatcher', 'driver', 'client'], true);
        });

        Gate::define('conversation.create', function ($user) {
            return in_array($user->role?->name, ['admin', 'dispatcher', 'driver', 'client'], true);
        });

        Gate::define('conversation.manage', function ($user) {
            return in_array($user->role?->name, ['admin', 'dispatcher'], true);
        });
    }
}

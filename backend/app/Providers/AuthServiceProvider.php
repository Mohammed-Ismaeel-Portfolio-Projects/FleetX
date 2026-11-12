<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Company Policy
        \App\Models\Company::class => \App\Policies\CompanyPolicy::class,

        // Driver Policy (identity show + ops gates)
        \App\Models\Driver::class => \App\Policies\DriverPolicy::class,

        // Vehicle Policy
        \App\Models\Vehicle::class => \App\Policies\VehiclePolicy::class,

        // Trailer Policy
        \App\Models\Trailer::class => \App\Policies\TrailerPolicy::class,

        // Shipment Policy (used by RouteController authorize('view', $shipment))
        \App\Models\Shipment::class => \App\Policies\ShipmentPolicy::class,

        // Issue Policy (Alerts Center / IssueController)
        \App\Models\Issue::class => \App\Policies\IssuePolicy::class,

        // Messaging
        \App\Models\Conversation::class => \App\Policies\ConversationPolicy::class,

        // Assignment Policy
        \App\Models\Assignment::class => \App\Policies\AssignmentPolicy::class,

        // Shipment Order Policy (ops read — C2.9-B2.1)
        \App\Models\ShipmentOrder::class => \App\Policies\ShipmentOrderPolicy::class,

        // Monthly Invoice Policy (Phase 6c read)
        \App\Models\MonthlyInvoice::class => \App\Policies\MonthlyInvoicePolicy::class,

        // Tracking Policies
        \App\Models\TrackingLog::class => \App\Policies\TrackingLogPolicy::class,
        \App\Models\TrackingState::class => \App\Policies\TrackingStatePolicy::class,

        // Driver Evaluation (DRIVER-EVAL-02)
        \App\Models\AssignmentEvaluation::class => \App\Policies\AssignmentEvaluationPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}

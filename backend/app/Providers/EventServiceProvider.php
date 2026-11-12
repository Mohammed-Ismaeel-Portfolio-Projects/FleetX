<?php

namespace App\Providers;

use App\Domain\Events\RouteDeviationDetected;
use App\Events\Tracking\TrackingStateUpdated;
use App\Listeners\DriverEvaluation\ProcessDriverEvaluationOnTrackingListener;
use App\Listeners\Tracking\AutoRerouteOnDeviationListener;
use App\Listeners\Tracking\BroadcastRouteDeviationListener;
use App\Listeners\Tracking\BroadcastTrackingStateListener;
use App\Listeners\Tracking\DetectRouteDeviationListener;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Event Service Provider
 *
 * Registers event listeners for the application.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        TrackingStateUpdated::class => [
            BroadcastTrackingStateListener::class,
            DetectRouteDeviationListener::class,
            ProcessDriverEvaluationOnTrackingListener::class,
        ],

        RouteDeviationDetected::class => [
            BroadcastRouteDeviationListener::class,
            AutoRerouteOnDeviationListener::class,
        ],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}

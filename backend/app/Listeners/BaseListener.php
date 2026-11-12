<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Base Listener
 * 
 * Base class for all event listeners.
 * Listeners handle domain events asynchronously via queues.
 */
abstract class BaseListener implements ShouldQueue
{
    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'default';

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 5;

    /**
     * Handle the event.
     */
    abstract public function handle(object $event): void;

    /**
     * Handle a job failure.
     */
    public function failed(object $event, \Throwable $exception): void
    {
        // Log the failure
        \Log::error('Listener failed', [
            'event' => get_class($event),
            'listener' => get_class($this),
            'error' => $exception->getMessage(),
        ]);
    }
}

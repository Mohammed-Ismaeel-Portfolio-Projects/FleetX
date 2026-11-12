<?php

namespace App\Simulation\Contracts;

/**
 * Event Bus for module communication.
 * Modules publish events and subscribe to events.
 */
interface EventBusInterface
{
    /**
     * Subscribe to an event.
     */
    public function subscribe(string $event, callable $handler): void;

    /**
     * Unsubscribe from an event.
     */
    public function unsubscribe(string $event, callable $handler): void;

    /**
     * Publish an event to all subscribers.
     */
    public function publish(string $event, array $payload = []): void;

    /**
     * Publish event asynchronously.
     */
    public function publishAsync(string $event, array $payload = []): void;

    /**
     * Clear all subscribers for an event.
     */
    public function clear(string $event): void;

    /**
     * Clear all event subscriptions.
     */
    public function clearAll(): void;
}

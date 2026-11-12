<?php

namespace App\Simulation\Core;

use App\Simulation\Contracts\EventBusInterface;

/**
 * Implementation of Event Bus for module communication.
 */
class EventBus implements EventBusInterface
{
    private array $subscribers = [];

    public function subscribe(string $event, callable $handler): void
    {
        if (!isset($this->subscribers[$event])) {
            $this->subscribers[$event] = [];
        }
        $this->subscribers[$event][] = $handler;
    }

    public function unsubscribe(string $event, callable $handler): void
    {
        if (isset($this->subscribers[$event])) {
            $this->subscribers[$event] = array_filter(
                $this->subscribers[$event],
                fn($h) => $h !== $handler
            );
        }
    }

    public function publish(string $event, array $payload = []): void
    {
        if (isset($this->subscribers[$event])) {
            foreach ($this->subscribers[$event] as $handler) {
                $handler($event, $payload);
            }
        }
    }

    public function publishAsync(string $event, array $payload = []): void
    {
        // Placeholder for async event publishing
        // Will be implemented with queue system
        $this->publish($event, $payload);
    }

    public function clear(string $event): void
    {
        unset($this->subscribers[$event]);
    }

    public function clearAll(): void
    {
        $this->subscribers = [];
    }
}

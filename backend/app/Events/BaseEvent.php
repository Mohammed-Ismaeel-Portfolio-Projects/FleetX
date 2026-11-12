<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base Event
 * 
 * Base class for all domain events.
 * Events are immutable and represent something that happened in the domain.
 */
abstract class BaseEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The event timestamp.
     */
    public readonly \DateTimeImmutable $occurredAt;

    /**
     * Create a new event instance.
     */
    public function __construct()
    {
        $this->occurredAt = new \DateTimeImmutable();
    }

    /**
     * Get the event type.
     */
    abstract public function getEventType(): string;

    /**
     * Get event data for serialization.
     */
    abstract public function toArray(): array;
}

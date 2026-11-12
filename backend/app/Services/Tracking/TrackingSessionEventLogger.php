<?php

namespace App\Services\Tracking;

use App\Models\TrackingSession;
use App\Models\TrackingSessionEvent;

class TrackingSessionEventLogger
{
    public function log(
        TrackingSession $session,
        string $eventType,
        array $payload = []
    ): TrackingSessionEvent {
        return TrackingSessionEvent::create([
            'tracking_session_id' => $session->id,
            'event_type' => $eventType,
            'payload' => empty($payload) ? null : $payload,
        ]);
    }
}

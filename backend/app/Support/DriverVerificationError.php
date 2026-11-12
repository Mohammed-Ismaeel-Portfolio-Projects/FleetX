<?php

namespace App\Support;

/**
 * Machine-readable Driver verification failure categories (D5).
 */
final class DriverVerificationError
{
    public const INVALID_CHALLENGE = 'invalid_challenge';

    public const EXPIRED_CHALLENGE = 'expired_challenge';

    public const REVOKED_CHALLENGE = 'revoked_challenge';

    public const ALREADY_CONSUMED = 'already_consumed';

    public const WRONG_EVENT = 'wrong_event';

    public const WRONG_ASSIGNMENT = 'wrong_assignment';

    public const WRONG_SHIPMENT = 'wrong_shipment';

    public const INVALID_PHASE = 'invalid_phase';

    public const UNAUTHORIZED_DRIVER = 'unauthorized_driver';

    public const ASSIGNMENT_INACTIVE = 'assignment_inactive';

    public const SHIPMENT_TRANSITION_INVALID = 'shipment_transition_invalid';
}

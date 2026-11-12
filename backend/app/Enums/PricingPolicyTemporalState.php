<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Admin-facing temporal classification for PricingPolicy (PRICING-05).
 *
 * Distinct from PricingPolicyStatus lifecycle enum. Derived from status + effective dates + resolver.
 */
enum PricingPolicyTemporalState: string
{
    case DRAFT = 'draft';
    case CURRENT = 'current';
    case FUTURE = 'future';
    case EXPIRED = 'expired';
    case ARCHIVED = 'archived';
    case ACTIVE_NOT_SELECTED = 'active_not_selected';
}

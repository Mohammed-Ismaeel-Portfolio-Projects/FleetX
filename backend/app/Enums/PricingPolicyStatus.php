<?php

namespace App\Enums;

enum PricingPolicyStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case SCHEDULED = 'scheduled';
    case EXPIRED = 'expired';
    case ARCHIVED = 'archived';
}

<?php

namespace App\Enums;

/**
 * Status Enum
 * 
 * Placeholder enum for status values.
 * TODO: Define actual status values based on domain requirements.
 */
enum Status: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}

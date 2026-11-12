<?php

namespace App\Enums;

/**
 * Priority Enum
 * 
 * Placeholder enum for priority values.
 * TODO: Define actual priority values based on domain requirements.
 */
enum Priority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case URGENT = 'urgent';
    case EMERGENCY = 'emergency';
}

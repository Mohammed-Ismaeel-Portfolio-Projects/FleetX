<?php

namespace App\Enums;

/**
 * Role Enum
 * 
 * Placeholder enum for role values.
 * TODO: Define actual role values based on domain requirements.
 */
enum Role: string
{
    case ADMIN = 'admin';
    case DISPATCHER = 'dispatcher';
    case DRIVER = 'driver';
    case CLIENT = 'client';
}

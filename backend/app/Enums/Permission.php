<?php

namespace App\Enums;

/**
 * Permission Enum
 * 
 * Placeholder enum for permission values.
 * TODO: Define actual permission values based on domain requirements.
 */
enum Permission: string
{
    case VIEW = 'view';
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case MANAGE = 'manage';
}

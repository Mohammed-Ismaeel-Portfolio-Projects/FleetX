<?php

namespace App\Enums;

enum CargoType: string
{
    case GENERAL = 'general';
    case REFRIGERATED = 'refrigerated';
    case HEAVY = 'heavy';
    case HAZARDOUS = 'hazardous';
    case FRAGILE = 'fragile';
}

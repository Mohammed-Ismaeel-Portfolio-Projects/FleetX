<?php

namespace App\Enums;

enum FinalPriceCalculationTrigger: string
{
    case COMPLETION_HOOK = 'completion_hook';
    case MANUAL = 'manual';
    case SIMULATION = 'simulation';
}

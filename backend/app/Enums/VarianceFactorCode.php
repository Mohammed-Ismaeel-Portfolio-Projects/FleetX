<?php

namespace App\Enums;

/**
 * Tier 1 material variance factors (Phase 5.5b).
 */
enum VarianceFactorCode: string
{
    case DISTANCE = 'distance';
    case WEIGHT = 'weight';
    case WAITING_LOADING = 'waiting_loading';
    case WAITING_UNLOADING = 'waiting_unloading';
}

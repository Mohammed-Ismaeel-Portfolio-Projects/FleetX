<?php

namespace App\Enums;

enum FinalPriceChangeDirection: string
{
    case NONE = 'none';
    case INCREASE = 'increase';
    case DECREASE = 'decrease';
}

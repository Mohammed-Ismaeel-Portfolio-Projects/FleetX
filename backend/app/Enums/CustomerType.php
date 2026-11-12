<?php

namespace App\Enums;

enum CustomerType: string
{
    case STANDARD = 'standard';
    case CORPORATE = 'corporate';
    case VIP = 'vip';
    case LONG_TERM = 'long_term';
}

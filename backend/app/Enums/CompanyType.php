<?php

namespace App\Enums;

enum CompanyType: string
{
    case INTERNAL = 'internal';
    case CLIENT = 'client';
    case PARTNER_CARRIER = 'partner_carrier';
    case HYBRID = 'hybrid';
}

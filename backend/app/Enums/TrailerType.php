<?php

namespace App\Enums;

enum TrailerType: string
{
    case DRY_VAN = 'dry_van';
    case REEFER = 'reefer';
    case FLATBED = 'flatbed';
    case LOWBOY = 'lowboy';
    case TANKER = 'tanker';
}

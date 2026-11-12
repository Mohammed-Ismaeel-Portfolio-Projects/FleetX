<?php

namespace App\Enums;

enum CollaborationType: string
{
    case INTERNAL = 'internal';
    case OUTBOUND_PARTNER = 'outbound_partner';
    case INBOUND_PARTNER = 'inbound_partner';
}

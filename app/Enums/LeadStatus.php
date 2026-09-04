<?php

namespace App\Enums;

enum LeadStatus: string
{
    case NEW = 'new';
    case CONTACTED = 'contacted';
    case INTERESTED = 'interested';
    case CONVERTED = 'converted';
    case NOT_INTERESTED = 'not_interested';
    case CLOSED = 'closed';
}

<?php

namespace App\Enums;

enum SaleType: string
{
    case POS_COUNTER = 'pos_counter';
    case WEB_LEAD = 'web_lead';
    case WHOLESALE = 'wholesale';
}

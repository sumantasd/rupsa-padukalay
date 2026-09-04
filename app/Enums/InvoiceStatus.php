<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case COMPLETED = 'completed';
    case RETURNED = 'returned';
    case PARTIALLY_RETURNED = 'partially_returned';
    case CANCELLED = 'cancelled';
}

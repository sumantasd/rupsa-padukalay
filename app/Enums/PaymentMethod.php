<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case UPI = 'upi';
    case CARD = 'card';
    case STORE_CREDIT = 'store_credit';
    case OTHER = 'other';
}

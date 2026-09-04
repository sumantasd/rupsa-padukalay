<?php

namespace App\Enums;

enum PurchaseStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case ORDERED = 'ordered';
    case PARTIAL = 'partial';
    case PARTIALLY_RECEIVED = 'partially_received';
    case RECEIVED = 'received';
    case FULLY_RECEIVED = 'fully_received';
    case CANCELLED = 'cancelled';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SENT => 'Sent',
            self::ORDERED => 'Ordered',
            self::PARTIAL, self::PARTIALLY_RECEIVED => 'Partially Received',
            self::RECEIVED, self::FULLY_RECEIVED => 'Fully Received',
            self::CANCELLED => 'Cancelled',
            self::CLOSED => 'Closed',
        };
    }
}

<?php

namespace App\Enums;

enum StockMovementType: string
{
    case PURCHASE_RECEIVE = 'purchase_receive';
    case PURCHASE = 'purchase';
    case SALE_POS = 'sale_pos';
    case SALE_RETURN = 'sale_return';
    case EXCHANGE = 'exchange';
    case TRANSFER_OUT = 'transfer_out';
    case TRANSFER_IN = 'transfer_in';
    case ADJUSTMENT_ADD = 'adjustment_add';
    case ADJUSTMENT_DEDUCT = 'adjustment_deduct';
    case ADJUSTMENT_IN = 'adjustment_in';
    case ADJUSTMENT_OUT = 'adjustment_out';
    case PURCHASE_RETURN = 'purchase_return';
    case OPENING_STOCK = 'opening_stock';
    case DAMAGE = 'damage';
    case STOCK_OUT_DAMAGE = 'stock_out_damage';
    case LOST = 'lost';
    case STOCK_CORRECTION = 'stock_correction';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE_RECEIVE, self::PURCHASE => 'PURCHASE',
            self::SALE_POS => 'POS SALE',
            self::SALE_RETURN => 'SALES RETURN',
            self::EXCHANGE => 'EXCHANGE',
            self::TRANSFER_OUT => 'TRANSFER OUT',
            self::TRANSFER_IN => 'TRANSFER IN',
            self::ADJUSTMENT_ADD, self::ADJUSTMENT_IN => 'ADJUSTMENT (ADD)',
            self::ADJUSTMENT_DEDUCT, self::ADJUSTMENT_OUT => 'ADJUSTMENT (REDUCE)',
            self::PURCHASE_RETURN => 'PURCHASE RETURN',
            self::OPENING_STOCK => 'OPENING STOCK',
            self::DAMAGE, self::STOCK_OUT_DAMAGE => 'Stock Out – Damage',
            self::LOST => 'LOST',
            self::STOCK_CORRECTION => 'STOCK CORRECTION',
        };
    }
}

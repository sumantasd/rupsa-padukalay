<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'company_name',
        'gstin',
        'pan',
        'phone',
        'alternate_mobile',
        'email',
        'address',
        'city',
        'state',
        'pincode',
        'current_balance',
        'opening_balance',
        'opening_balance_type',
        'payment_terms',
        'credit_limit',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function goodsReceives(): HasMany
    {
        return $this->hasMany(GoodsReceive::class);
    }

    public function purchaseBills(): HasMany
    {
        return $this->hasMany(PurchaseBill::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function calculateBalances(): array
    {
        $openingPayable = strtolower((string) $this->opening_balance_type) === 'advance' ? -abs((float) $this->opening_balance) : abs((float) $this->opening_balance);

        $hasBills = $this->purchaseBills()->count() > 0;
        if ($hasBills) {
            $totalPurchases = (float) $this->purchaseBills()->sum('grand_total');
        } else {
            $validPoQuery = $this->purchaseOrders()->whereNotIn('status', ['cancelled', 'CANCELLED', 'draft', 'DRAFT']);
            $totalPurchases = (float) (clone $validPoQuery)->sum('grand_total');
        }

        $totalPayments = (float) $this->payments()->sum('amount');
        $totalReturns = (float) $this->purchaseReturns()->sum('total_return_amount');

        $netPayable = round($openingPayable + $totalPurchases - $totalPayments - $totalReturns, 2);

        $currentDue = max(0.00, $netPayable);
        $advanceCredit = $netPayable < 0 ? abs($netPayable) : 0.00;

        return [
            'total_purchases' => $totalPurchases,
            'total_paid' => $totalPayments,
            'total_returns' => $totalReturns,
            'current_due' => $currentDue,
            'supplier_credit' => $advanceCredit,
            'net_balance' => $netPayable,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ReturnSale;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CustomerAnalyticsService
{
    public function getAuthorizedStoreIds(User $user, ?int $requestedStoreId = null): ?array
    {
        if ($user->roles()->where('name', 'Super Admin')->exists()) {
            return $requestedStoreId ? [$requestedStoreId] : null;
        }

        $userStoreIds = $user->stores()->pluck('stores.id')->toArray();
        if ($requestedStoreId !== null) {
            if (! in_array($requestedStoreId, $userStoreIds)) {
                throw new \RuntimeException('Forbidden: You are not authorized to view analytics for this store.', 403);
            }

            return [$requestedStoreId];
        }

        return $userStoreIds;
    }

    public function buildBaseInvoiceQuery(Customer $customer, array $filters, User $user)
    {
        $requestedStoreId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $authorizedStoreIds = $this->getAuthorizedStoreIds($user, $requestedStoreId);

        $query = Invoice::where('customer_id', $customer->id)
            ->where(function ($q) {
                $q->where('status', '!=', 'cancelled')
                    ->where('status', '!=', 'CANCELLED');
            });

        if ($authorizedStoreIds !== null) {
            $query->whereIn('store_id', $authorizedStoreIds);
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('items', function ($iq) use ($search) {
                        $iq->where('sku_snapshot', 'LIKE', "%{$search}%")
                            ->orWhere('product_name_snapshot', 'LIKE', "%{$search}%")
                            ->orWhere('article_number_snapshot', 'LIKE', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['payment_method'])) {
            $pm = strtolower(trim((string) $filters['payment_method']));
            $query->whereHas('payments', function ($pq) use ($pm) {
                $pq->where('payment_method', $pm);
            });
        }

        if (! empty($filters['status'])) {
            $st = strtolower(trim((string) $filters['status']));
            if (in_array($st, ['paid', 'partially_paid', 'unpaid'])) {
                $query->where('payment_status', $st);
            } else {
                $query->where('status', $st);
            }
        }

        return $query;
    }

    public function getPurchaseHistory(Customer $customer, array $filters, User $user): LengthAwarePaginator
    {
        $query = $this->buildBaseInvoiceQuery($customer, $filters, $user);
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $query->with(['store', 'creator', 'items', 'payments', 'returns'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function getPurchaseSummary(Customer $customer, array $filters, User $user): array
    {
        $invQuery = $this->buildBaseInvoiceQuery($customer, $filters, $user);
        $totalOrders = (clone $invQuery)->count();

        $grossValue = $totalOrders > 0 ? (float) (clone $invQuery)->sum('subtotal') : 0.00;
        $totalDiscounts = $totalOrders > 0 ? (float) (clone $invQuery)->sum('discount_amount') : 0.00;
        $totalTax = $totalOrders > 0 ? (float) (clone $invQuery)->sum('total_tax') : 0.00;
        $netValue = $totalOrders > 0 ? (float) (clone $invQuery)->sum('grand_total') : 0.00;
        $paidAmount = $totalOrders > 0 ? (float) (clone $invQuery)->sum('paid_amount') : 0.00;
        $outstanding = max(0.0, round($netValue - $paidAmount, 2));

        $validInvIds = (clone $invQuery)->pluck('id');
        $totalItemsPurchased = $validInvIds->isNotEmpty()
            ? (int) InvoiceItem::whereIn('invoice_id', $validInvIds)->sum('quantity')
            : 0;

        // Returns Query
        $requestedStoreId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $authorizedStoreIds = $this->getAuthorizedStoreIds($user, $requestedStoreId);

        $retQuery = ReturnSale::where('customer_id', $customer->id);
        if ($authorizedStoreIds !== null) {
            $retQuery->whereIn('store_id', $authorizedStoreIds);
        }
        if (! empty($filters['start_date'])) {
            $retQuery->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $retQuery->whereDate('created_at', '<=', $filters['end_date']);
        }

        $totalReturns = (float) $retQuery->sum('total_refund_amount');
        $numberOfReturns = (int) $retQuery->count();

        $firstInvoice = $totalOrders > 0 ? (clone $invQuery)->orderBy('created_at', 'asc')->first() : null;
        $lastInvoice = $totalOrders > 0 ? (clone $invQuery)->orderBy('created_at', 'desc')->first() : null;

        $firstPurchaseDate = $firstInvoice?->created_at ? $firstInvoice->created_at->toIso8601String() : null;
        $lastPurchaseDate = $lastInvoice?->created_at ? $lastInvoice->created_at->toIso8601String() : null;

        return [
            'customer_id' => $customer->id,
            'total_orders' => $totalOrders,
            'total_gross_purchase_value' => round($grossValue, 2),
            'total_discounts' => round($totalDiscounts, 2),
            'total_tax' => round($totalTax, 2),
            'total_net_purchase_value' => round($netValue, 2),
            'total_paid_amount' => round($paidAmount, 2),
            'outstanding_amount' => round($outstanding, 2),
            'total_returned_amount' => round($totalReturns, 2),
            'number_of_returns' => $numberOfReturns,
            'total_items_purchased' => $totalItemsPurchased,
            'first_purchase_date' => $firstPurchaseDate,
            'last_purchase_date' => $lastPurchaseDate,
        ];
    }


    public function getPurchaseAnalytics(Customer $customer, array $filters, User $user): array
    {
        $summary = $this->getPurchaseSummary($customer, $filters, $user);

        $netValue = $summary['total_net_purchase_value'];
        $returnedAmt = $summary['total_returned_amount'];
        $totalOrders = $summary['total_orders'];

        $clv = max(0.0, round($netValue - $returnedAmt, 2));
        $aov = $totalOrders > 0 ? round($netValue / $totalOrders, 2) : 0.00;
        $repurchaseRate = $totalOrders > 1 ? round((($totalOrders - 1) / $totalOrders) * 100, 2) : 0.00;

        $frequencyDays = 0.00;
        if ($totalOrders >= 2 && ! empty($summary['first_purchase_date']) && ! empty($summary['last_purchase_date'])) {
            $firstTs = strtotime($summary['first_purchase_date']);
            $lastTs = strtotime($summary['last_purchase_date']);
            $daysDiff = max(0, ($lastTs - $firstTs) / 86400);
            $frequencyDays = round($daysDiff / ($totalOrders - 1), 2);
        }

        // Favorites & Top SKUs Query
        $invQuery = $this->buildBaseInvoiceQuery($customer, $filters, $user);
        $validInvoiceIds = (clone $invQuery)->pluck('id')->toArray();

        $favProduct = null;
        $favCategory = null;
        $favBrand = null;
        $topSkus = [];
        $lastPurchaseInfo = null;
        $monthlyTrends = [];

        if (count($validInvoiceIds) > 0) {
            // Favorite Product
            $favProdRow = InvoiceItem::whereIn('invoice_id', $validInvoiceIds)
                ->select('product_name_snapshot', 'article_number_snapshot', DB::raw('SUM(quantity) as total_qty'))
                ->groupBy('product_name_snapshot', 'article_number_snapshot')
                ->orderBy('total_qty', 'desc')
                ->first();

            if ($favProdRow) {
                $favProduct = [
                    'name' => $favProdRow->product_name_snapshot,
                    'article_number' => $favProdRow->article_number_snapshot,
                    'total_quantity' => (int) $favProdRow->total_qty,
                ];
            }

            // Top SKUs
            $topSkuRows = InvoiceItem::whereIn('invoice_id', $validInvoiceIds)
                ->select('sku_snapshot', 'product_name_snapshot', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(subtotal) as total_spent'))
                ->groupBy('sku_snapshot', 'product_name_snapshot')
                ->orderBy('total_qty', 'desc')
                ->limit(5)
                ->get();

            $topSkus = $topSkuRows->map(fn ($r) => [
                'sku' => $r->sku_snapshot,
                'product_name' => $r->product_name_snapshot,
                'total_quantity' => (int) $r->total_qty,
                'total_spent' => (float) $r->total_spent,
            ])->toArray();

            // Favorite Category & Brand via Variant Size relationship
            $favCategoryRow = DB::table('invoice_items')
                ->join('product_variant_sizes', 'invoice_items.product_variant_size_id', '=', 'product_variant_sizes.id')
                ->join('product_variants', 'product_variant_sizes.product_variant_id', '=', 'product_variants.id')
                ->join('products', 'product_variants.product_id', '=', 'products.id')
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->whereIn('invoice_items.invoice_id', $validInvoiceIds)
                ->select('categories.name as category_name', DB::raw('SUM(invoice_items.quantity) as total_qty'))
                ->groupBy('categories.id', 'categories.name')
                ->orderBy('total_qty', 'desc')
                ->first();

            if ($favCategoryRow) {
                $favCategory = [
                    'name' => $favCategoryRow->category_name,
                    'total_quantity' => (int) $favCategoryRow->total_qty,
                ];
            }

            $favBrandRow = DB::table('invoice_items')
                ->join('product_variant_sizes', 'invoice_items.product_variant_size_id', '=', 'product_variant_sizes.id')
                ->join('product_variants', 'product_variant_sizes.product_variant_id', '=', 'product_variants.id')
                ->join('products', 'product_variants.product_id', '=', 'products.id')
                ->join('brands', 'products.brand_id', '=', 'brands.id')
                ->whereIn('invoice_items.invoice_id', $validInvoiceIds)
                ->select('brands.name as brand_name', DB::raw('SUM(invoice_items.quantity) as total_qty'))
                ->groupBy('brands.id', 'brands.name')
                ->orderBy('total_qty', 'desc')
                ->first();

            if ($favBrandRow) {
                $favBrand = [
                    'name' => $favBrandRow->brand_name,
                    'total_quantity' => (int) $favBrandRow->total_qty,
                ];
            }

            // Last Purchase Info
            $latestInvoice = (clone $invQuery)->with('store')->orderBy('created_at', 'desc')->first();
            if ($latestInvoice) {
                $lastPurchaseInfo = [
                    'invoice_id' => $latestInvoice->id,
                    'invoice_number' => $latestInvoice->invoice_number,
                    'invoice_date' => $latestInvoice->created_at ? $latestInvoice->created_at->toIso8601String() : null,
                    'grand_total' => (float) $latestInvoice->grand_total,
                    'store_name' => $latestInvoice->store?->name,
                ];
            }

            // Monthly Trends (Supports MySQL & SQLite)
            $driver = DB::connection()->getDriverName();
            $dateFormatExpr = $driver === 'sqlite'
                ? "strftime('%Y-%m', created_at)"
                : "DATE_FORMAT(created_at, '%Y-%m')";

            $monthlyRows = (clone $invQuery)
                ->select(
                    DB::raw("{$dateFormatExpr} as year_month"),
                    DB::raw('COUNT(id) as order_count'),
                    DB::raw('SUM(subtotal) as gross_total'),
                    DB::raw('SUM(grand_total) as net_total')
                )
                ->groupBy('year_month')
                ->orderBy('year_month', 'asc')
                ->get();

            $monthlyTrends = $monthlyRows->map(fn ($m) => [
                'year_month' => $m->year_month,
                'order_count' => (int) $m->order_count,
                'gross_total' => (float) $m->gross_total,
                'net_total' => (float) $m->net_total,
            ])->toArray();
        }

        return [
            'customer_id' => $customer->id,
            'clv' => $clv,
            'aov' => $aov,
            'repurchase_rate' => $repurchaseRate,
            'purchase_frequency_days' => $frequencyDays,
            'favorite_product' => $favProduct,
            'favorite_category' => $favCategory,
            'favorite_brand' => $favBrand,
            'top_purchased_skus' => $topSkus,
            'last_purchase_info' => $lastPurchaseInfo,
            'monthly_trends' => $monthlyTrends,
        ];
    }

    public function getRfmSegmentation(Customer $customer, User $user): array
    {
        $summary = $this->getPurchaseSummary($customer, [], $user);

        $totalOrders = $summary['total_orders'];
        $netSpend = max(0.0, round($summary['total_net_purchase_value'] - $summary['total_returned_amount'], 2));

        $recencyDays = null;
        $recencyScore = 1;

        if ($totalOrders > 0 && ! empty($summary['last_purchase_date'])) {
            $lastTs = strtotime($summary['last_purchase_date']);
            $recencyDays = (int) max(0, floor((time() - $lastTs) / 86400));

            if ($recencyDays <= 30) {
                $recencyScore = 5;
            } elseif ($recencyDays <= 60) {
                $recencyScore = 4;
            } elseif ($recencyDays <= 90) {
                $recencyScore = 3;
            } elseif ($recencyDays <= 180) {
                $recencyScore = 2;
            } else {
                $recencyScore = 1;
            }
        } else {
            $recencyScore = 1;
        }

        // Frequency Score (Total completed orders)
        if ($totalOrders >= 10) {
            $frequencyScore = 5;
        } elseif ($totalOrders >= 5) {
            $frequencyScore = 4;
        } elseif ($totalOrders >= 3) {
            $frequencyScore = 3;
        } elseif ($totalOrders >= 2) {
            $frequencyScore = 2;
        } elseif ($totalOrders === 1) {
            $frequencyScore = 1;
        } else {
            $frequencyScore = 0;
        }

        // Monetary Score (Net monetary spend)
        if ($netSpend >= 10000) {
            $monetaryScore = 5;
        } elseif ($netSpend >= 5000) {
            $monetaryScore = 4;
        } elseif ($netSpend >= 2500) {
            $monetaryScore = 3;
        } elseif ($netSpend >= 1000) {
            $monetaryScore = 2;
        } elseif ($netSpend > 0) {
            $monetaryScore = 1;
        } else {
            $monetaryScore = 0;
        }

        // Segment Classification
        $segment = 'New / Occasional';
        if ($recencyScore >= 4 && $frequencyScore >= 4 && $monetaryScore >= 4) {
            $segment = 'Champions';
        } elseif ($frequencyScore >= 3 && $monetaryScore >= 3) {
            $segment = 'Loyal Customers';
        } elseif ($recencyScore >= 4) {
            $segment = 'Recent Customers';
        } elseif ($recencyScore <= 2 && $frequencyScore >= 3) {
            $segment = 'At Risk';
        } elseif ($recencyScore === 1 && $frequencyScore <= 2) {
            $segment = 'Lost Customers';
        }

        $rfmScoreStr = "{$recencyScore}{$frequencyScore}{$monetaryScore}";

        return [
            'customer_id' => $customer->id,
            'recency_days' => $recencyDays,
            'frequency' => $totalOrders,
            'monetary_value' => $netSpend,
            'recency_score' => $recencyScore,
            'frequency_score' => $frequencyScore,
            'monetary_score' => $monetaryScore,
            'rfm_score' => $rfmScoreStr,
            'segment' => $segment,
        ];
    }
}

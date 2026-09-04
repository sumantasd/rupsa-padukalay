<?php

namespace App\Services;

use App\Models\ProductVariantSize;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use Illuminate\Support\Facades\DB;

class PromotionEngineService
{
    public function evaluateCart(array $cartItemsInput, ?string $couponCode = null, ?int $storeId = null, ?int $customerId = null): array
    {
        // 1. Resolve Items & Attributes
        $items = [];
        $originalSubtotal = 0.0;

        foreach ($cartItemsInput as $idx => $input) {
            $vs = null;
            if (! empty($input['product_variant_size_id'])) {
                $vs = ProductVariantSize::with(['variant.product'])->find((int) $input['product_variant_size_id']);
            } elseif (! empty($input['sku'])) {
                $vs = ProductVariantSize::with(['variant.product'])->where('sku', trim($input['sku']))->first();
            }

            if (! $vs) {
                throw new \InvalidArgumentException("Invalid product variant size ID or SKU in cart item at index {$idx}.");
            }

            $qty = (int) ($input['quantity'] ?? 1);
            $unitPrice = isset($input['unit_price']) ? (float) $input['unit_price'] : (float) ($vs->selling_price ?? 0.00);
            $lineSubtotal = round($unitPrice * $qty, 2);
            $originalSubtotal += $lineSubtotal;

            $items[] = [
                'index' => $idx,
                'variant_size_id' => $vs->id,
                'sku' => $vs->sku,
                'product_id' => $vs->variant?->product_id,
                'category_id' => $vs->variant?->product?->category_id,
                'brand_id' => $vs->variant?->product?->brand_id,
                'unit_price' => $unitPrice,
                'quantity' => $qty,
                'original_subtotal' => $lineSubtotal,
                'line_discount' => 0.00,
                'final_subtotal' => $lineSubtotal,
                'applied_promotions' => [],
                'locked' => false,
            ];
        }

        $originalSubtotal = round($originalSubtotal, 2);

        // 2. Fetch Eligible Active Promotions
        $now = now();
        $promoQuery = Promotion::with('targets')
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $now);
            })
            ->where(function ($q) use ($storeId) {
                $q->whereNull('store_id')->orWhere('store_id', $storeId);
            });

        // Filter usage limits
        $promotions = $promoQuery->orderBy('priority', 'desc')
            ->orderBy('discount_value', 'desc')
            ->get()
            ->filter(function ($promo) use ($customerId) {
                if ($promo->usage_limit !== null && $promo->usage_count >= $promo->usage_limit) {
                    return false;
                }
                if ($customerId && $promo->usage_limit_per_customer !== null) {
                    $custUsage = PromotionUsage::where('promotion_id', $promo->id)
                        ->where('customer_id', $customerId)
                        ->count();
                    if ($custUsage >= $promo->usage_limit_per_customer) {
                        return false;
                    }
                }

                return true;
            });

        $couponPromo = null;
        if (! empty($couponCode)) {
            $codeClean = strtoupper(trim($couponCode));
            $couponPromo = $promotions->firstWhere('code', $codeClean);
            if (! $couponPromo) {
                $dbCoupon = Promotion::where('code', $codeClean)->first();
                if (! $dbCoupon || ! $dbCoupon->is_active) {
                    throw new \InvalidArgumentException("Invalid or inactive coupon code '{$couponCode}'.");
                }
                if ($dbCoupon->start_date && $dbCoupon->start_date > $now) {
                    throw new \InvalidArgumentException("Coupon code '{$couponCode}' is not yet active.");
                }
                if ($dbCoupon->end_date && $dbCoupon->end_date < $now) {
                    throw new \InvalidArgumentException("Coupon code '{$couponCode}' has expired.");
                }
                if ($dbCoupon->usage_limit !== null && $dbCoupon->usage_count >= $dbCoupon->usage_limit) {
                    throw new \InvalidArgumentException("Coupon code '{$couponCode}' has reached its usage limit.");
                }
                if ($customerId && $dbCoupon->usage_limit_per_customer !== null) {
                    $custUsage = PromotionUsage::where('promotion_id', $dbCoupon->id)
                        ->where('customer_id', $customerId)
                        ->count();
                    if ($custUsage >= $dbCoupon->usage_limit_per_customer) {
                        throw new \InvalidArgumentException("Coupon code '{$couponCode}' has reached its usage limit per customer.");
                    }
                }
                $couponPromo = $dbCoupon;
            }
        }

        $appliedPromotions = [];
        $totalDiscountAmount = 0.0;

        // 3. Process Line-Item Level Promotions (Product, Category, Brand)
        foreach ($promotions as $promo) {
            if (in_array($promo->discount_scope, ['product', 'category', 'brand'])) {
                $promoDiscountTotal = 0.0;

                foreach ($items as &$item) {
                    if ($item['locked']) {
                        continue;
                    }

                    $matchesTarget = false;
                    foreach ($promo->targets as $target) {
                        if ($promo->discount_scope === 'product' && $target->target_type === 'product' && $target->target_id == $item['product_id']) {
                            $matchesTarget = true;
                            break;
                        }
                        if ($promo->discount_scope === 'category' && $target->target_type === 'category' && $target->target_id == $item['category_id']) {
                            $matchesTarget = true;
                            break;
                        }
                        if ($promo->discount_scope === 'brand' && $target->target_type === 'brand' && $target->target_id == $item['brand_id']) {
                            $matchesTarget = true;
                            break;
                        }
                    }

                    if (! $matchesTarget && $promo->targets->count() > 0) {
                        continue;
                    }

                    if ($promo->min_cart_amount > 0 && $originalSubtotal < $promo->min_cart_amount) {
                        continue;
                    }

                    $itemDiscount = 0.0;
                    if ($promo->promotion_type === 'percentage') {
                        $itemDiscount = round(($item['unit_price'] * $item['quantity']) * ((float) $promo->discount_value / 100), 2);
                    } elseif ($promo->promotion_type === 'fixed_amount') {
                        $itemDiscount = min((float) $promo->discount_value, $item['final_subtotal']);
                    }

                    if ($promo->max_discount_amount && $itemDiscount > (float) $promo->max_discount_amount) {
                        $itemDiscount = (float) $promo->max_discount_amount;
                    }

                    if ($itemDiscount > 0) {
                        $item['line_discount'] = round($item['line_discount'] + $itemDiscount, 2);
                        $item['final_subtotal'] = max(0.0, round($item['original_subtotal'] - $item['line_discount'], 2));
                        $item['applied_promotions'][] = $promo->name;
                        $promoDiscountTotal += $itemDiscount;

                        if (! $promo->allow_stacking) {
                            $item['locked'] = true;
                        }
                    }
                }
                unset($item);

                if ($promoDiscountTotal > 0) {
                    $appliedPromotions[] = [
                        'id' => $promo->id,
                        'name' => $promo->name,
                        'code' => $promo->code,
                        'discount_amount' => round($promoDiscountTotal, 2),
                    ];
                    $totalDiscountAmount += $promoDiscountTotal;
                }
            }
        }

        // 4. Process BOGO Promotions
        foreach ($promotions as $promo) {
            if ($promo->promotion_type === 'bogo') {
                $qualifyingUnits = [];
                foreach ($items as $itemIndex => $item) {
                    $matches = false;
                    if ($promo->targets->count() === 0) {
                        $matches = true;
                    } else {
                        foreach ($promo->targets as $target) {
                            if ($target->target_type === 'product' && $target->target_id == $item['product_id']) {
                                $matches = true;
                                break;
                            }
                            if ($target->target_type === 'category' && $target->target_id == $item['category_id']) {
                                $matches = true;
                                break;
                            }
                            if ($target->target_type === 'brand' && $target->target_id == $item['brand_id']) {
                                $matches = true;
                                break;
                            }
                        }
                    }

                    if ($matches) {
                        for ($q = 0; $q < $item['quantity']; $q++) {
                            $qualifyingUnits[] = [
                                'item_index' => $itemIndex,
                                'unit_price' => $item['unit_price'],
                            ];
                        }
                    }
                }

                $totalUnits = count($qualifyingUnits);
                $groupSize = $promo->buy_quantity + $promo->get_quantity;

                if ($totalUnits >= $groupSize) {
                    // Sort unit prices descending
                    usort($qualifyingUnits, fn ($a, $b) => $b['unit_price'] <=> $a['unit_price']);

                    $numSets = (int) floor($totalUnits / $groupSize);
                    $bogoDiscountTotal = 0.0;

                    for ($s = 0; $s < $numSets; $s++) {
                        // Discount lowest priced get_quantity units in this set
                        for ($g = 0; $g < $promo->get_quantity; $g++) {
                            $targetUnitIndex = ($s + 1) * $groupSize - 1 - $g;
                            if (isset($qualifyingUnits[$targetUnitIndex])) {
                                $targetUnit = $qualifyingUnits[$targetUnitIndex];
                                $unitDiscount = round($targetUnit['unit_price'] * ((float) $promo->get_discount_percentage / 100), 2);

                                $itemIdx = $targetUnit['item_index'];
                                $items[$itemIdx]['line_discount'] = round($items[$itemIdx]['line_discount'] + $unitDiscount, 2);
                                $items[$itemIdx]['final_subtotal'] = max(0.0, round($items[$itemIdx]['original_subtotal'] - $items[$itemIdx]['line_discount'], 2));
                                $items[$itemIdx]['applied_promotions'][] = $promo->name;
                                $bogoDiscountTotal += $unitDiscount;
                            }
                        }
                    }

                    if ($bogoDiscountTotal > 0) {
                        $appliedPromotions[] = [
                            'id' => $promo->id,
                            'name' => $promo->name,
                            'code' => $promo->code,
                            'discount_amount' => round($bogoDiscountTotal, 2),
                        ];
                        $totalDiscountAmount += $bogoDiscountTotal;
                    }
                }
            }
        }

        // 5. Process Cart-Level Promotions & Coupon Codes
        $currentSubtotal = max(0.0, round($originalSubtotal - $totalDiscountAmount, 2));

        $cartPromotions = $promotions->filter(fn ($p) => $p->discount_scope === 'cart' && $p->promotion_type !== 'bogo');

        if ($couponPromo && $couponPromo->discount_scope === 'cart' && ! $cartPromotions->contains('id', $couponPromo->id)) {
            $cartPromotions->push($couponPromo);
        }

        foreach ($cartPromotions as $promo) {
            if ($currentSubtotal <= 0) {
                break;
            }

            if ($promo->min_cart_amount > 0 && $originalSubtotal < (float) $promo->min_cart_amount) {
                continue;
            }

            $cartDiscount = 0.0;
            if ($promo->promotion_type === 'percentage') {
                $cartDiscount = round($currentSubtotal * ((float) $promo->discount_value / 100), 2);
            } elseif ($promo->promotion_type === 'fixed_amount') {
                $cartDiscount = min((float) $promo->discount_value, $currentSubtotal);
            }

            if ($promo->max_discount_amount && $cartDiscount > (float) $promo->max_discount_amount) {
                $cartDiscount = (float) $promo->max_discount_amount;
            }

            if ($cartDiscount > 0) {
                $appliedPromotions[] = [
                    'id' => $promo->id,
                    'name' => $promo->name,
                    'code' => $promo->code,
                    'discount_amount' => round($cartDiscount, 2),
                ];
                $totalDiscountAmount += $cartDiscount;
                $currentSubtotal = max(0.0, round($currentSubtotal - $cartDiscount, 2));

                if (! $promo->allow_stacking) {
                    break;
                }
            }
        }

        $totalDiscountAmount = round(min($originalSubtotal, $totalDiscountAmount), 2);
        $finalSubtotal = max(0.0, round($originalSubtotal - $totalDiscountAmount, 2));

        return [
            'original_subtotal' => $originalSubtotal,
            'total_discount_amount' => $totalDiscountAmount,
            'final_subtotal' => $finalSubtotal,
            'applied_promotions' => $appliedPromotions,
            'items' => $items,
        ];
    }

    public function recordUsages(array $appliedPromotions, ?int $customerId, ?int $invoiceId, ?string $clientUuid = null): void
    {
        foreach ($appliedPromotions as $applied) {
            $promoId = $applied['id'];
            $discountAmt = (float) ($applied['discount_amount'] ?? 0.0);

            if (! empty($clientUuid)) {
                $existing = PromotionUsage::where('promotion_id', $promoId)
                    ->where('client_trans_uuid', $clientUuid)
                    ->first();
                if ($existing) {
                    continue;
                }
            }

            if ($invoiceId) {
                $existingInv = PromotionUsage::where('promotion_id', $promoId)
                    ->where('invoice_id', $invoiceId)
                    ->first();
                if ($existingInv) {
                    continue;
                }
            }

            PromotionUsage::create([
                'promotion_id' => $promoId,
                'customer_id' => $customerId,
                'invoice_id' => $invoiceId,
                'discount_amount_applied' => $discountAmt,
                'client_trans_uuid' => $clientUuid,
            ]);

            Promotion::where('id', $promoId)->increment('usage_count');
        }
    }
}

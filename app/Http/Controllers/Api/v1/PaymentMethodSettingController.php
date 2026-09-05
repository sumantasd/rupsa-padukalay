<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\CmsSetting;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentMethodSettingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuditService $auditService
    ) {}

    private function getDefaultPaymentMethods(): array
    {
        return [
            [
                'id' => 'cash',
                'name' => 'Cash',
                'code' => 'CASH',
                'description' => 'Physical cash payment at counter',
                'icon' => '💵',
                'is_active' => true,
                'requires_reference' => false,
                'sort_order' => 1,
            ],
            [
                'id' => 'upi',
                'name' => 'UPI / QR Code',
                'code' => 'UPI',
                'description' => 'Google Pay, PhonePe, Paytm, BHIM UPI',
                'icon' => '📱',
                'is_active' => true,
                'requires_reference' => true,
                'sort_order' => 2,
            ],
            [
                'id' => 'card',
                'name' => 'Credit / Debit Card',
                'code' => 'CARD',
                'description' => 'POS Card Swipe / Tap Machine',
                'icon' => '💳',
                'is_active' => true,
                'requires_reference' => true,
                'sort_order' => 3,
            ],
            [
                'id' => 'bank_transfer',
                'name' => 'Bank Transfer / NEFT / RTGS',
                'code' => 'BANK_TRANSFER',
                'description' => 'Direct bank deposit or electronic transfer',
                'icon' => '🏦',
                'is_active' => true,
                'requires_reference' => true,
                'sort_order' => 4,
            ],
            [
                'id' => 'store_credit',
                'name' => 'Store Credit Note',
                'code' => 'STORE_CREDIT',
                'description' => 'Customer store credit adjustment',
                'icon' => '🏷️',
                'is_active' => true,
                'requires_reference' => true,
                'sort_order' => 5,
            ],
            [
                'id' => 'loyalty_points',
                'name' => 'Loyalty Points',
                'code' => 'LOYALTY_POINTS',
                'description' => 'Redeem accumulated rewards points',
                'icon' => '⭐',
                'is_active' => true,
                'requires_reference' => false,
                'sort_order' => 6,
            ],
        ];
    }

    private function getMethodsList(): array
    {
        $raw = CmsSetting::getSetting('payment_methods');
        $saved = $raw ? json_decode($raw, true) : null;
        if (! is_array($saved) || empty($saved)) {
            return $this->getDefaultPaymentMethods();
        }

        // Sort by sort_order
        usort($saved, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));
        return $saved;
    }

    private function saveMethodsList(array $methods): void
    {
        CmsSetting::setSetting('payment_methods', json_encode(array_values($methods)));
    }

    /**
     * Get active payment methods for runtime POS consumption (Cashiers & Sales).
     */
    public function getActiveMethods(): JsonResponse
    {
        $all = $this->getMethodsList();
        $active = array_values(array_filter($all, fn($m) => ! empty($m['is_active'])));

        return $this->successResponse($active, 'Active payment methods retrieved successfully.');
    }

    /**
     * Admin endpoint: List all payment methods including inactive ones.
     */
    public function index(): JsonResponse
    {
        $methods = $this->getMethodsList();

        return $this->successResponse($methods, 'Payment methods retrieved successfully.');
    }

    /**
     * Admin endpoint: Create new payment method.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:10',
            'is_active' => 'required|boolean',
            'requires_reference' => 'required|boolean',
        ]);

        $methods = $this->getMethodsList();

        $newId = Str::slug($validated['name']) . '_' . time();
        $newMethod = [
            'id' => $newId,
            'name' => trim($validated['name']),
            'code' => strtoupper(trim($validated['code'])),
            'description' => $validated['description'] ?? '',
            'icon' => $validated['icon'] ?? '💰',
            'is_active' => (bool) $validated['is_active'],
            'requires_reference' => (bool) $validated['requires_reference'],
            'sort_order' => count($methods) + 1,
        ];

        $methods[] = $newMethod;
        $this->saveMethodsList($methods);

        $this->auditService->logEvent([
            'module' => 'payment_methods',
            'event_type' => 'payment_method_created',
            'after_state' => $newMethod,
            'reason_notes' => "Added new payment method: {$newMethod['name']}",
        ]);

        return $this->successResponse($newMethod, 'Payment method created successfully.');
    }

    /**
     * Admin endpoint: Update existing payment method.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:10',
            'is_active' => 'required|boolean',
            'requires_reference' => 'required|boolean',
        ]);

        $methods = $this->getMethodsList();
        $index = -1;
        foreach ($methods as $i => $m) {
            if ($m['id'] === $id) {
                $index = $i;
                break;
            }
        }

        if ($index === -1) {
            return $this->errorResponse('Payment method not found.', 404);
        }

        $before = $methods[$index];
        $methods[$index] = array_merge($methods[$index], [
            'name' => trim($validated['name']),
            'code' => strtoupper(trim($validated['code'])),
            'description' => $validated['description'] ?? '',
            'icon' => $validated['icon'] ?? $methods[$index]['icon'],
            'is_active' => (bool) $validated['is_active'],
            'requires_reference' => (bool) $validated['requires_reference'],
        ]);

        $this->saveMethodsList($methods);

        $this->auditService->logEvent([
            'module' => 'payment_methods',
            'event_type' => 'payment_method_updated',
            'before_state' => $before,
            'after_state' => $methods[$index],
            'reason_notes' => "Updated payment method: {$methods[$index]['name']}",
        ]);

        return $this->successResponse($methods[$index], 'Payment method updated successfully.');
    }

    /**
     * Admin endpoint: Toggle active status.
     */
    public function toggleStatus(string $id): JsonResponse
    {
        $methods = $this->getMethodsList();
        $index = -1;
        foreach ($methods as $i => $m) {
            if ($m['id'] === $id) {
                $index = $i;
                break;
            }
        }

        if ($index === -1) {
            return $this->errorResponse('Payment method not found.', 404);
        }

        $beforeStatus = $methods[$index]['is_active'];
        $methods[$index]['is_active'] = ! $methods[$index]['is_active'];

        $this->saveMethodsList($methods);

        $this->auditService->logEvent([
            'module' => 'payment_methods',
            'event_type' => 'payment_method_status_toggled',
            'before_state' => ['is_active' => $beforeStatus],
            'after_state' => ['is_active' => $methods[$index]['is_active']],
            'reason_notes' => "Toggled payment method {$methods[$index]['name']} to " . ($methods[$index]['is_active'] ? 'Active' : 'Inactive'),
        ]);

        return $this->successResponse($methods[$index], 'Payment method status toggled successfully.');
    }
}

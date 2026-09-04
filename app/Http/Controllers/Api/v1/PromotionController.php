<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\EvaluatePromotionRequest;
use App\Http\Requests\Master\StorePromotionRequest;
use App\Http\Requests\Master\UpdatePromotionRequest;
use App\Http\Resources\PromotionEvaluationResource;
use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Services\PromotionEngineService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromotionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PromotionEngineService $engineService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Promotion::with(['store', 'targets']);

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $query->where(function ($q) use ($userStoreIds) {
                $q->whereNull('store_id')->orWhereIn('store_id', $userStoreIds);
            });
        } else {
            if ($request->has('store_id')) {
                $query->where('store_id', (int) $request->input('store_id'));
            }
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('promotion_type')) {
            $query->where('promotion_type', trim((string) $request->input('promotion_type')));
        }

        if ($request->has('discount_scope')) {
            $query->where('discount_scope', trim((string) $request->input('discount_scope')));
        }

        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $promotions = $query->orderBy('priority', 'desc')->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse(
            [
                'items' => PromotionResource::collection($promotions->items()),
                'pagination' => [
                    'current_page' => $promotions->currentPage(),
                    'per_page' => $promotions->perPage(),
                    'total' => $promotions->total(),
                    'last_page' => $promotions->lastPage(),
                ],
            ],
            'Promotions retrieved successfully.'
        );
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $request->input('store_id');

        if ($storeId && ! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains((int) $storeId)) {
                return $this->errorResponse('Forbidden: You are not authorized to create promotions for this store.', 403);
            }
        }

        $data = $request->validated();
        if (! empty($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        $promotion = DB::transaction(function () use ($data) {
            $promo = Promotion::create($data);

            if (! empty($data['targets'])) {
                foreach ($data['targets'] as $targetData) {
                    PromotionTarget::create([
                        'promotion_id' => $promo->id,
                        'target_type' => $targetData['target_type'],
                        'target_id' => (int) $targetData['target_id'],
                    ]);
                }
            }

            return $promo;
        });

        return $this->successResponse(
            new PromotionResource($promotion->load(['store', 'targets'])),
            'Promotion created successfully.',
            201
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $promotion = Promotion::with(['store', 'targets'])->find($id);

        if (! $promotion) {
            return $this->errorResponse('Promotion not found.', 404);
        }

        $user = $request->user();

        // Store Access Control for non-Super Admin
        if ($promotion->store_id && ! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($promotion->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to view promotions for this store.', 403);
            }
        }

        return $this->successResponse(
            new PromotionResource($promotion),
            'Promotion details retrieved successfully.'
        );
    }

    public function update(UpdatePromotionRequest $request, int $id): JsonResponse
    {
        $promotion = Promotion::find($id);

        if (! $promotion) {
            return $this->errorResponse('Promotion not found.', 404);
        }

        $user = $request->user();

        // Store Access Control for non-Super Admin
        if ($promotion->store_id && ! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($promotion->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to edit promotions for this store.', 403);
            }
        }

        $data = $request->validated();
        if (! empty($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        DB::transaction(function () use ($promotion, $data) {
            $promotion->update($data);

            if (isset($data['targets'])) {
                PromotionTarget::where('promotion_id', $promotion->id)->delete();
                foreach ($data['targets'] as $targetData) {
                    PromotionTarget::create([
                        'promotion_id' => $promotion->id,
                        'target_type' => $targetData['target_type'],
                        'target_id' => (int) $targetData['target_id'],
                    ]);
                }
            }
        });

        return $this->successResponse(
            new PromotionResource($promotion->load(['store', 'targets'])),
            'Promotion updated successfully.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $promotion = Promotion::find($id);

        if (! $promotion) {
            return $this->errorResponse('Promotion not found.', 404);
        }

        $user = $request->user();

        if ($promotion->store_id && ! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($promotion->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to delete promotions for this store.', 403);
            }
        }

        $promotion->delete();

        return $this->successResponse(
            null,
            'Promotion deleted successfully.'
        );
    }

    public function evaluate(EvaluatePromotionRequest $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $request->input('store_id');
        $customerId = $request->input('customer_id');
        $couponCode = $request->input('coupon_code');
        $items = $request->input('items', []);

        if ($storeId && ! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains((int) $storeId)) {
                return $this->errorResponse('Forbidden: You are not authorized to evaluate promotions for this store.', 403);
            }
        }

        try {
            $evaluation = $this->engineService->evaluateCart($items, $couponCode, $storeId ? (int) $storeId : null, $customerId ? (int) $customerId : null);

            return $this->successResponse(
                new PromotionEvaluationResource($evaluation),
                'Promotion cart evaluation calculated successfully.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to evaluate promotions: '.$e->getMessage(), 500);
        }
    }
}

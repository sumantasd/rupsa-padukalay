<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class CheckStoreAccess
{
    use ApiResponse;

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if (! $user->is_active) {
            return $this->errorResponse('Forbidden: Account is deactivated.', 403);
        }

        // Super Admin has access to all stores
        if ($user->roles()->where('name', 'Super Admin')->exists()) {
            return $next($request);
        }

        $storeId = $request->input('store_id') ?? $request->route('store_id') ?? $request->route('id') ?? $request->route('store');

        if ($storeId) {
            $hasAccess = $user->stores()->where('stores.id', $storeId)->exists();
            if (! $hasAccess) {
                return $this->errorResponse(
                    "Forbidden: You are not authorized to access Store ID {$storeId}.",
                    403
                );
            }
        }

        return $next($request);
    }
}

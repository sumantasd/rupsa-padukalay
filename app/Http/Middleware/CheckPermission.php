<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    use ApiResponse;

    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $permsList = explode('|', $permission);
        $hasAny = false;

        foreach ($permsList as $p) {
            if ($user->hasPermissionTo(trim($p))) {
                $hasAny = true;
                break;
            }
        }

        if (! $hasAny) {
            return $this->errorResponse(
                "Forbidden: You do not have permission [{$permission}] to perform this action.",
                403
            );
        }

        return $next($request);
    }
}

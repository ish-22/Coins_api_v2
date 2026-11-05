<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     * Role-Based Access: Allow admin, super_admin, and operator staff.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['admin', 'super_admin', 'operator'])) {
            return response()->json([
                'message' => 'Unauthorized: Administrator or Operator privileges required.',
            ], 403);
        }

        return $next($request);
    }
}

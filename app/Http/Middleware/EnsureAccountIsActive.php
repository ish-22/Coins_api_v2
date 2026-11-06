<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Handle an incoming request.
     * Enforces that blocked or suspended clients cannot operate the exchange.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== 'active') {
            $msg = $user->status === 'blocked'
                ? 'Your account has been permanently blocked by the administration. Access is restricted.'
                : ($user->status === 'suspended'
                    ? 'Your account is suspended. Please contact customer support.'
                    : 'Your account is pending verification. Access is restricted.');

            return response()->json([
                'message' => $msg,
                'account_status' => $user->status,
            ], 403);
        }

        return $next($request);
    }
}


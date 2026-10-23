<?php

namespace App\Traits;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

trait HandlesExceptions
{
    /**
     * Map exception to HTTP status code.
     */
    protected function getHttpStatusCodeForException(Exception $e): int
    {
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode();
        }

        if ($e instanceof AuthenticationException) {
            return 401;
        }

        if ($e instanceof AuthorizationException) {
            return 403;
        }

        if ($e instanceof ModelNotFoundException) {
            return 404;
        }

        if ($e instanceof ValidationException) {
            return 422;
        }

        if ($e instanceof QueryException) {
            return 500;
        }

        $code = (int) $e->getCode();
        return ($code >= 400 && $code < 600) ? $code : 500;
    }

    /**
     * Handle controller action exception and log appropriately.
     */
    protected function handleException(Exception $e, string $context = 'Operation failed'): JsonResponse
    {
        $status = $this->getHttpStatusCodeForException($e);

        if ($status >= 500) {
            Log::error("[{$context}] Exception: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json([
            'result' => false,
            'msg' => $e->getMessage() ?: $context,
            'message' => $e->getMessage() ?: $context,
        ], $status);
    }
}

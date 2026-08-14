<?php

namespace App\Exceptions;

use App\Models\ApiErrorLog;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'new_password',
        'new_password_confirmation',
        'otp',
        'mobile_otp',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    protected function logApiError($request, Throwable $e, int $statusCode): void
    {
        try {
            $payload = $request->except($this->dontFlash);

            // For validation errors, store the field-level messages instead of the generic message
            if ($e instanceof ValidationException) {
                $errorMessage = json_encode($e->errors());
            } else {
                $errorMessage = $e->getMessage();
            }

            ApiErrorLog::create([
                'method'          => $request->method(),
                'endpoint'        => $request->path(),
                'full_url'        => $request->fullUrl(),
                'request_payload' => json_encode($payload),
                'error_message'   => $errorMessage,
                'stack_trace'     => $e->getTraceAsString(),
                'status_code'     => $statusCode,
                'user_id'         => optional(Auth::user())->id,
                'ip_address'      => $request->ip(),
                'user_agent'      => $request->userAgent(),
            ]);
        } catch (\Throwable) {
            // Never let error logging crash the app
        }
    }

    /**
     * API routes always return 401 JSON for auth failures, regardless of Accept header.
     * Web routes redirect to the login page.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please log in to continue.',
            ], 401);
        }

        return redirect()->guest(route('login'));
    }

    /**
     * Render an exception into an HTTP response.
     *
     * Catches the case where Auth::user() is null inside a protected controller
     * (phantom session — user deleted from DB while session cookie still exists).
     * Instead of a 500 crash, we treat it as an authentication failure.
     */
    public function render($request, Throwable $e)
    {
        // Null-user crash: "Attempt to read property '...' on null" when Auth::user() is null.
        if ($e instanceof \ErrorException && Auth::guest()) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'on null') || str_contains($msg, 'null given')) {
                if ($request->is('api/*') || $request->expectsJson()) {
                    $this->logApiError($request, $e, 401);
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthenticated. Please log in to continue.',
                    ], 401);
                }
                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
                return redirect()->route('login');
            }
        }

        // Log all API errors
        if ($request->is('api/*') || $request->expectsJson()) {
            if ($e instanceof ValidationException) {
                $statusCode = 422;
            } elseif ($e instanceof AuthenticationException) {
                $statusCode = 401;
            } elseif (method_exists($e, 'getStatusCode')) {
                $statusCode = $e->getStatusCode();
            } else {
                $statusCode = 500;
            }
            $this->logApiError($request, $e, $statusCode);
        }

        return parent::render($request, $e);
    }
}

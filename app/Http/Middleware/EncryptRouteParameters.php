<?php
// app/Http/Middleware/EncryptRouteParameters.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Helpers\EncryptHelper;
use Illuminate\Support\Facades\Log;

class EncryptRouteParameters
{
    public function handle(Request $request, Closure $next)
    {
        // Check if this is a URL generation (not yet a request to the controller)
        // Encrypt all the parameters in the route if they are numbers
        foreach ($request->route()->parameters() as $key => $value) {
            // If the value is a number, encrypt it (adjust based on your needs)
            if (is_numeric($value)) {
                // Encrypt the parameter value
                $encryptedValue = EncryptHelper::encrypt($value);
                // Replace the original value with the encrypted one
                $request->route()->setParameter($key, $encryptedValue);
                Log::info("Encrypted parameter {$key}: {$encryptedValue}");
            }
        }

        return $next($request);
    }
}

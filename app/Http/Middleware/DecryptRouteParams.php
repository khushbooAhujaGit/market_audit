<?php
// app/Http/Middleware/DecryptRouteParams.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Helpers\EncryptHelper;
use Illuminate\Support\Facades\Log;

class DecryptRouteParams
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        // Loop through all route parameters and decrypt them
        foreach ($route->parameters() as $key => $value) {
            try {
                // Decrypt the parameter if it's encrypted
                $decryptedValue = EncryptHelper::decrypt($value);
                // Set the decrypted value back into the route parameters
                $route->setParameter($key, $decryptedValue);
                Log::info("Decrypted route parameter: {$key} => {$decryptedValue}");
            } catch (\Exception $e) {
                // Log the failure or leave the original value in case of decryption failure
                Log::warning("Failed to decrypt parameter {$key}: {$value}");
            }
        }

        return $next($request);
    }
    
}

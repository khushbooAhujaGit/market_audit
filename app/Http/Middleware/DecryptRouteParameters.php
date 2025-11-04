<?php

namespace App\Http\Middleware;

use App\Helpers\EncryptRouteHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DecryptRouteParameters
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {
        $route = $request->route();
        $params = $route->parameters();
        foreach ($params as $key => $value) {
            $decrypted = EncryptRouteHelper::decrypt($value);
            if (!is_null($decrypted)) {
                $route->setParameter($key, $decrypted);
            }
        }

        return $next($request);
    }
}

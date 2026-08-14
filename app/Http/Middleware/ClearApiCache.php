<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ClearApiCache
{
    public function handle(Request $request, Closure $next)
    {
        Cache::flush();

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Automatically parse a raw JSON body into request input so that
 * $request->input() / $request->validate() work regardless of whether
 * the caller sets Content-Type: application/json.
 */
class ParseJsonBody
{
    public function handle(Request $request, Closure $next)
    {
        $content = $request->getContent();

        if ($content && !$request->isJson()) {
            $decoded = json_decode($content, true);
            if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                $request->merge($decoded);
            }
        }

        return $next($request);
    }
}

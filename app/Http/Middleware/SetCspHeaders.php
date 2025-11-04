<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\View;

class SetCspHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

    public function handle(Request $request, Closure $next)
    {
        $nonce = base64_encode(random_bytes(16));
        View::share('csp_nonce', $nonce); // Make it available to views
        app()->instance('csp_nonce', $nonce);
        $response = $next($request);
        // Apply only to HTML responses
        if (
            $response->headers->get('Content-Type') &&
            str_contains($response->headers->get('Content-Type'), 'text/html')
        ) {
            $content = $response->getContent();
            // Add nonce to <script> tags that do not already have one
            $content = preg_replace_callback(
                '/<script(?![^>]*\bnonce=)([^>]*)>/i',
                fn($matches) => '<script nonce="' . $nonce . '"' . $matches[1] . '>',
                $content
            );
            $response->setContent($content);
        }
        // Correct and single CSP header
        // $response->headers->set('Content-Security-Policy',
        //     "default-src 'self'; " .
        //     "connect-src 'self' https://ka-f.fontawesome.com https://api.iconify.design https://api.simplesvg.com https://api.unisvg.com; " .
        //     "script-src 'self' 'nonce-$nonce' https://cdn.jsdelivr.net https://cdn.datatables.net https://kit.fontawesome.com https://code.jquery.com https://cdnjs.cloudflare.com; " .
        //     "script-src-elem 'self' 'nonce-$nonce' https://cdn.jsdelivr.net https://cdn.datatables.net https://kit.fontawesome.com https://code.jquery.com https://cdnjs.cloudflare.com; " .
        //     "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.datatables.net https://ka-f.fontawesome.com; " .
        //     "style-src-elem 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.datatables.net https://ka-f.fontawesome.com; " .
        //     "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdn.jsdelivr.net/npm/bootstrap-icons data:; " .
        //     "img-src 'self' data: https://cdn.datatables.net https://*.iconify.design;".
        //     "script-src 'self' 'nonce-$nonce' https://cdn.jsdelivr.net https://cdn.datatables.net https://kit.fontawesome.com https://code.jquery.com https://cdnjs.cloudflare.com https://unpkg.com;" .
        //     "script-src-elem 'self' 'nonce-$nonce' https://cdn.jsdelivr.net https://cdn.datatables.net https://kit.fontawesome.com https://code.jquery.com https://cdnjs.cloudflare.com https://unpkg.com "
        // );
//        $response->headers->set('Content-Security-Policy',
//            "default-src 'self'; " .
//            "script-src 'self' 'nonce-$nonce' 'unsafe-hashes' 'sha256-AbCdEf123...' https://cdn.jsdelivr.net ..." .
//            "connect-src 'self' https://ka-f.fontawesome.com https://api.iconify.design https://api.simplesvg.com https://api.unisvg.com https://unpkg.com; " .
//            "script-src 'self' 'nonce-$nonce' https://cdn.jsdelivr.net https://cdn.datatables.net https://kit.fontawesome.com https://code.jquery.com https://cdnjs.cloudflare.com https://unpkg.com; " .
//            "script-src-elem 'self' 'nonce-$nonce' https://cdn.jsdelivr.net https://cdn.datatables.net https://kit.fontawesome.com https://code.jquery.com https://cdnjs.cloudflare.com https://unpkg.com; " .
//            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.datatables.net https://ka-f.fontawesome.com https://unpkg.com; " .
//            "style-src-elem 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.datatables.net https://ka-f.fontawesome.com https://unpkg.com; " .
//            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdn.jsdelivr.net/npm/bootstrap-icons data:; " .
//            "img-src 'self' data: https://cdn.datatables.net https://*.iconify.design https://unpkg.com https://*.tile.openstreetmap.org;"
//        );
        $response->headers->set('Content-Security-Policy',
            "default-src 'self'; " .
            "connect-src 'self' https://ka-f.fontawesome.com https://api.iconify.design https://api.simplesvg.com https://api.unisvg.com https://unpkg.com; " .
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.datatables.net https://kit.fontawesome.com https://code.jquery.com https://cdnjs.cloudflare.com https://unpkg.com; " .
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.datatables.net https://ka-f.fontawesome.com https://unpkg.com; " .
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdn.jsdelivr.net/npm/bootstrap-icons data:; " .
            "img-src 'self' data: https://cdn.datatables.net https://*.iconify.design https://unpkg.com https://*.tile.openstreetmap.org;"
        );
        $response->headers->set('X-Test-CSP', 'applied');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        return $response;
    }

}

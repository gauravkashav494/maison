<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Adds an X-Robots-Tag header to every response while "discourage indexing" is on. */
class DiscourageSearchEngines
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (setting('seo.discourage_indexing', false)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Service-business routes exist only in templates that opt in (Template::supportsServices()). */
class EnsureServicesTemplate
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(template()->supportsServices(), 404);

        return $next($request);
    }
}

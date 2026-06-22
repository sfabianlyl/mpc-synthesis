<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for Filament admin panel routes
        if ($request->is('admin/*')) {
            return $next($request);
        }

        View::share('appearance', $request->cookie('appearance') ?? 'system');

        return $next($request);
    }
}

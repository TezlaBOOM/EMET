<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckSetupCompleted
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $setupCompleted = config('agenthub.setup_completed') || Cache::get('agenthub_setup_completed', false);

        if (! $setupCompleted && auth()->check()) {
            if (! $request->is('setup*') && ! $request->is('logout') && ! $request->is('api/*')) {
                return redirect()->route('setup.step', ['step' => 1]);
            }
        }

        return $next($request);
    }
}

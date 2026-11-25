<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEffectivePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasEffectivePermission($permission)) {
            abort(403, 'No cuenta con permisos suficientes para acceder a este recurso.');
        }

        return $next($request);
    }
}

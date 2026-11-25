<?php

namespace App\Http\Middleware;

use App\Models\InstitutionSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $settings = InstitutionSetting::current();

        if (! $settings->isModuleEnabled($module)) {
            if ($module === 'direccion' && $request->user()?->hasRole('super_admin')) {
                return $next($request);
            }

            abort(403, sprintf('El módulo "%s" se encuentra deshabilitado temporalmente por Dirección.', config('cecap.modules.'.$module, $module)));
        }

        return $next($request);
    }
}

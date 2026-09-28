<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect('login');
        }

        $user = auth()->user();

        // Super admin puede acceder a cualquier empresa
        if ($user->is_super_admin) {
            return $next($request);
        }

        // Si hay un company_id en la ruta, verificar que el usuario pertenezca a esa empresa
        if ($request->route('company')) {
            $company = $request->route('company');
            if ($user->companies()->where('company_id', $company->id)->doesntExist()) {
                return response()->view('errors.403', [], 403);
            }

            session(['current_company_id' => $company->id]);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\Company;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        try {
            $login = trim((string) $request->email);
            $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

            if (!Auth::attempt([$field => $login, 'password' => $request->password], $request->boolean('remember'))) {
                return back()->withErrors(['email' => 'Las credenciales no son válidas']);
            }

            $user = Auth::user();

            if (!$user->active) {
                Auth::logout();
                return back()->withErrors(['email' => 'Tu usuario ha sido desactivado']);
            }

            if ($user->is_super_admin) {
                session(['current_company_id' => null]);
                app(Tenancy::class)->unrestrict();
                return redirect()->route('dashboard');
            }

            $companies = $user->activeCompanies()->get();

            if ($companies->isEmpty()) {
                Auth::logout();
                return back()->withErrors(['email' => 'No tienes acceso a ninguna empresa']);
            }

            if ($companies->count() === 1) {
                $this->activateCompany($companies->first()->id);
                return redirect()->route('dashboard');
            }

            return redirect()->route('select-company')->with('companies', $companies);
        } catch (\Throwable $exception) {
            Log::error('Error al iniciar sesión', [
                'login' => $request->email,
                'message' => $exception->getMessage(),
            ]);

            return back()->withErrors(['email' => 'No fue posible iniciar sesión en este momento.']);
        }
    }

    public function selectCompany()
    {
        $user = auth()->user();
        $companies = $user->activeCompanies()->get();

        if ($companies->isEmpty()) {
            Auth::logout();
            return redirect('login')->with('error', 'No tienes acceso a ninguna empresa');
        }

        return view('auth.select-company', compact('companies'));
    }

    public function setCompany($companyId)
    {
        $user = auth()->user();

        // El superadmin puede "entrar como" cualquier empresa para dar soporte;
        // el resto, solo a las suyas.
        $company = $user->is_super_admin
            ? Company::findOrFail($companyId)
            : $user->activeCompanies()->findOrFail($companyId);

        $this->activateCompany($company->id);

        return redirect()->route('dashboard');
    }

    /**
     * Vuelve al Modo Global (solo superadmin): deja de ver una empresa concreta.
     */
    public function exitCompany()
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        session()->forget('current_company_id');
        app(Tenancy::class)->unrestrict();

        return redirect()->route('dashboard')
            ->with('success', 'Volviste al Modo Global: estás viendo todas las empresas.');
    }

    public function logout()
    {
        Auth::logout();
        session()->flush();
        app(Tenancy::class)->forget();

        return redirect('/login');
    }

    /**
     * Fija la empresa activa en la sesión Y en la petición en curso: sin lo
     * segundo, el CompanyScope seguiría fail-closed hasta la siguiente request.
     */
    protected function activateCompany(int $companyId): void
    {
        session(['current_company_id' => $companyId]);
        app(Tenancy::class)->set($companyId);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Personal;
use App\Models\Branch;
use App\Models\Caja;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $company = request()->attributes->get('tenant_company');

        // Personal, Branch y Caja se filtran solos por la empresa activa
        // (CompanyScope). En Modo Global el superadmin ve el total real.
        $totalPersonal = Personal::count();
        $totalBranches = Branch::count();
        $totalCajas    = Caja::count();

        // User es un modelo global: aquí sí hay que acotar a mano.
        $totalUsers = $company
            ? User::whereHas('companies', fn ($q) => $q->where('companies.id', $company->id))->count()
            : User::count();

        return view('dashboard.index', compact(
            'totalUsers',
            'totalPersonal',
            'totalBranches',
            'totalCajas',
            'company'
        ));
    }
}

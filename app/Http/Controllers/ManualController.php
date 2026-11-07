<?php

namespace App\Http\Controllers;

use App\Support\UserManual;
use Illuminate\Http\Request;

/** El manual de usuario en la web. El contenido es el mismo de la app: ver UserManual. */
class ManualController extends Controller
{
    public function index(Request $request)
    {
        return view('manual.index', [
            'sections' => UserManual::for('web', $request->user(), $request->attributes->get('tenant_company')),
        ]);
    }
}

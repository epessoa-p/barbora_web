<?php

namespace App\Http\Controllers;

use App\Support\Catalog;

/**
 * El catálogo público: sin sesión, por el enlace con token. Si la barbería lo
 * apagó (o regeneró el enlace), responde 404 y no dice nada más.
 */
class CatalogController extends Controller
{
    /** La página que ve el cliente; se puede guardar como PDF desde el navegador. */
    public function show(string $token)
    {
        $company = Catalog::findPublished($token) ?? abort(404);

        return view('catalog.public', [
            'company' => $company,
            'services' => Catalog::services($company),
            'products' => Catalog::products($company),
            'url' => Catalog::url($company),
        ]);
    }

    /** El afiche para la puerta del local: logo, nombre y el QR del catálogo. */
    public function poster(string $token)
    {
        $company = Catalog::findPublished($token) ?? abort(404);

        return view('catalog.poster', [
            'company' => $company,
            'url' => Catalog::url($company),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\Catalog;
use Illuminate\Http\Request;

/**
 * Configuración del catálogo público: publicarlo o no, si muestra productos,
 * y su enlace / QR / afiche. Siempre de la empresa activa.
 */
class CatalogSettingsController extends Controller
{
    public function edit(Request $request)
    {
        $company = $this->company($request);

        return view('settings.catalog.edit', [
            'company' => $company,
            'url' => Catalog::url($company),
            'posterUrl' => Catalog::posterUrl($company),
            'servicesCount' => Catalog::services($company)->flatten()->count(),
        ]);
    }

    public function update(Request $request)
    {
        $company = $this->company($request);

        $company->update([
            'catalog_enabled' => $request->boolean('catalog_enabled'),
            'catalog_show_products' => $request->boolean('catalog_show_products'),
        ]);

        return redirect()->route('catalog-settings.edit')->with('success', $company->catalog_enabled
            ? 'Catálogo publicado: cualquiera con el enlace o el QR puede verlo.'
            : 'Catálogo despublicado: el enlace ya no muestra nada.');
    }

    /** Enlace nuevo: el anterior (y los QR impresos) dejan de funcionar. */
    public function regenerate(Request $request)
    {
        Catalog::regenerate($this->company($request));

        return redirect()->route('catalog-settings.edit')
            ->with('success', 'Enlace nuevo generado. El anterior y su QR ya no funcionan: vuelve a imprimir el afiche.');
    }

    protected function company(Request $request): Company
    {
        $company = $request->attributes->get('tenant_company');

        abort_unless($company, 404);

        return $company;
    }
}

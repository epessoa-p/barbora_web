<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Sube/quita el logo de una empresa, cuidando el archivo en disco.
 *
 * Reglas (las mismas que ya usaba el perfil de la barbería): el archivo nuevo
 * se guarda ANTES de tocar la BD; si la BD falla, el nuevo se borra para no
 * dejar huérfanos; el anterior se borra solo DESPUÉS de guardar bien, así ante
 * un fallo la empresa nunca se queda sin ninguno de los dos.
 */
trait HandlesCompanyLogo
{
    protected function syncCompanyLogo(Request $request, Company $company): void
    {
        $previous = $company->logo;
        $logo = $previous;
        $uploaded = null;

        if ($request->boolean('remove_logo')) {
            $logo = null;
        }

        if ($request->hasFile('logo')) {
            // Una carpeta por empresa: al dar de baja se sabe qué archivos son suyos.
            $uploaded = $request->file('logo')->store("companies/{$company->id}", 'public');
            $logo = $uploaded;
        }

        if ($logo === $previous) {
            return;
        }

        try {
            $company->update(['logo' => $logo]);
        } catch (\Throwable $e) {
            if ($uploaded) {
                Storage::disk('public')->delete($uploaded);
            }

            throw $e;
        }

        if ($previous && $previous !== $logo) {
            Storage::disk('public')->delete($previous);
        }
    }
}

<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Datos de la barbería que salen en el comprobante: logo, dirección, teléfono,
 * correo y el texto del pie.
 *
 * El nombre y el NIT NO se editan desde aquí. Son los datos con los que el
 * operador verifica la identidad de quien pide soporte (por ejemplo, un cambio
 * de contraseña del dueño): si el propio cliente pudiera cambiarlos, esa
 * verificación dejaría de valer. Se cambian desde el panel del operador.
 */
class CompanyProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('settings.company.edit', ['company' => $this->company($request)]);
    }

    public function update(Request $request)
    {
        $company = $this->company($request);

        $data = $request->validate([
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            // Cuadrado o apaisado, pequeño: va en un ticket de 80 mm y en el móvil.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [
            'logo.max' => 'El logo no puede pesar más de 1 MB.',
            'logo.mimes' => 'El logo tiene que ser PNG, JPG o WEBP.',
        ]);

        $previous = $company->logo;
        $logo = $previous;
        $uploaded = null;

        if ($request->boolean('remove_logo')) {
            $logo = null;
        }

        if ($request->hasFile('logo')) {
            // Una carpeta por empresa: al dar de baja una barbería se sabe qué
            // archivos son suyos.
            $uploaded = $request->file('logo')->store("companies/{$company->id}", 'public');
            $logo = $uploaded;
        }

        try {
            $company->update([
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'receipt_footer' => $data['receipt_footer'] ?? null,
                'logo' => $logo,
            ]);
        } catch (\Throwable $e) {
            // Si la base rechaza el cambio, el archivo recién subido se borra:
            // si no, quedaría huérfano en el disco sin nadie que lo apunte.
            if ($uploaded) {
                Storage::disk('public')->delete($uploaded);
            }

            throw $e;
        }

        // El logo anterior se borra DESPUÉS de guardar, nunca antes: si el
        // guardado fallara, la barbería se quedaría sin ninguno de los dos.
        if ($previous && $previous !== $logo) {
            Storage::disk('public')->delete($previous);
        }

        return redirect()->route('company-profile.edit')
            ->with('success', 'Datos de la barbería actualizados.');
    }

    /**
     * Siempre la empresa activa. No hay {company} en la ruta a propósito: así
     * no existe forma de apuntar a la ficha de otra barbería.
     */
    protected function company(Request $request): Company
    {
        $company = $request->attributes->get('tenant_company');

        abort_unless($company, 404);

        return $company;
    }
}

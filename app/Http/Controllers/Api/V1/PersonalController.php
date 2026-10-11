<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Cargo;
use App\Models\Personal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestión de barberos (personal) desde el móvil.
 *
 * El alta/edición de la CUENTA de acceso (usuario/contraseña) sigue en la web;
 * aquí se administra la ficha del barbero (nombre, cargo, si atiende citas…).
 * El CompanyScope acota todo a la empresa activa.
 */
class PersonalController extends ApiController
{
    /** Todo el personal de la barbería (activo e inactivo), para administrarlo. */
    public function index(): JsonResponse
    {
        $personal = Personal::with('cargo')->orderBy('full_name')->get();

        return $this->json(['data' => $personal->map($this->payload(...))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('tenant_company')->id;

        $data = $this->validateData($request, $companyId);

        $personal = Personal::create([
            'company_id' => $companyId,
            'cargo_id' => $data['cargo_id'],
            'full_name' => trim($data['full_name']),
            'id_number' => $data['id_number'] ?? null,
            'phone' => $data['phone'] ?? null,
            'specialty' => $data['specialty'] ?? null,
            'agenda_color' => $data['agenda_color'] ?? '#2563eb',
            'bookable' => $request->boolean('bookable'),
            'active' => $request->boolean('active', true),
        ]);

        $personal->load('cargo');

        return $this->json([
            'message' => 'Barbero registrado.',
            'data' => $this->payload($personal),
        ], 201);
    }

    public function update(Request $request, Personal $personal): JsonResponse
    {
        $data = $this->validateData($request, $personal->company_id);

        $personal->update([
            'cargo_id' => $data['cargo_id'],
            'full_name' => trim($data['full_name']),
            'id_number' => $data['id_number'] ?? null,
            'phone' => $data['phone'] ?? null,
            'specialty' => $data['specialty'] ?? null,
            'agenda_color' => $data['agenda_color'] ?? $personal->agenda_color,
            'bookable' => $request->boolean('bookable'),
            'active' => $request->boolean('active', (bool) $personal->active),
        ]);

        $personal->load('cargo');

        return $this->json([
            'message' => 'Barbero actualizado.',
            'data' => $this->payload($personal),
        ]);
    }

    /** Cargos activos, para el selector al crear/editar un barbero. */
    public function cargos(): JsonResponse
    {
        $cargos = Cargo::where('active', true)->orderBy('name')->get(['id', 'name']);

        return $this->json(['data' => $cargos->map(fn (Cargo $c) => [
            'id' => $c->id, 'name' => $c->name,
        ])->all()]);
    }

    /* ── Internos ─────────────────────────────────────────────────────────── */

    protected function validateData(Request $request, int $companyId): array
    {
        return $request->validate([
            'cargo_id' => ['required', Rule::exists('cargos', 'id')->where('company_id', $companyId)],
            'full_name' => ['required', 'string', 'max:255'],
            'id_number' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'agenda_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'bookable' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ], [
            'cargo_id.required' => 'Elige el cargo del barbero.',
            'full_name.required' => 'El nombre es obligatorio.',
        ]);
    }

    protected function payload(Personal $personal): array
    {
        return [
            'id' => $personal->id,
            'full_name' => $personal->full_name,
            'cargo_id' => $personal->cargo_id,
            'cargo' => $personal->cargo?->name,
            'phone' => $personal->phone,
            'specialty' => $personal->specialty,
            'agenda_color' => $personal->agenda_color,
            'id_number' => $personal->id_number,
            'bookable' => (bool) $personal->bookable,
            'active' => (bool) $personal->active,
            'has_user' => $personal->user_id !== null,
            'photo_url' => $personal->photoUrl(),
        ];
    }
}

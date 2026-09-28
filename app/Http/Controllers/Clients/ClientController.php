<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::search($request->query('q'))
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString();

        return view('clients.index', [
            'clients' => $clients,
            'q' => $request->query('q'),
        ]);
    }

    public function create()
    {
        return view('clients.create', ['client' => null]);
    }

    public function store(Request $request)
    {
        $client = Client::create($this->validated($request));

        return redirect()->route('clients.show', $client)
            ->with('success', "Cliente «{$client->full_name}» registrado exitosamente.");
    }

    public function show(Client $client)
    {
        return view('clients.show', compact('client'));
    }

    public function edit(Client $client)
    {
        return view('clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $client->update($this->validated($request, $client));

        return redirect()->route('clients.show', $client)
            ->with('success', 'Cliente actualizado exitosamente.');
    }

    public function destroy(Client $client)
    {
        $client->delete();

        return redirect()->route('clients.index')
            ->with('success', 'Cliente eliminado exitosamente.');
    }

    protected function validated(Request $request, ?Client $client = null): array
    {
        $companyId = $client?->company_id ?? $this->targetCompanyId();

        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            // El documento es opcional, pero si se registra no puede repetirse
            // dentro de la misma empresa.
            'document_number' => [
                'nullable', 'string', 'max:30',
                Rule::unique('clients', 'document_number')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($client?->id),
            ],
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'birth_date' => 'nullable|date|before:today',
            'preferences' => 'nullable|string|max:255',
            'allergies' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'active' => 'sometimes|boolean',
        ], [
            'document_number.unique' => 'Ya hay un cliente registrado con ese documento.',
            'birth_date.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
        ]);

        $data['active'] = $request->boolean('active');

        return $data;
    }
}

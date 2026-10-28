<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Support\ClientForm;
use Illuminate\Http\Request;

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

    /** Las reglas son las mismas que en la API: ver ClientForm. */
    protected function validated(Request $request, ?Client $client = null): array
    {
        return app(ClientForm::class)->validate(
            $request,
            $client?->company_id ?? $this->targetCompanyId(),
            $client,
            activeIsCheckbox: true,
        );
    }
}

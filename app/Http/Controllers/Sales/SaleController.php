<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Personal;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Service;
use App\Support\SaleItemResolver;
use App\Support\SaleRegistrar;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function __construct(
        protected SaleRegistrar $registrar,
        protected SaleItemResolver $itemResolver,
    ) {
    }

    public function index(Request $request)
    {
        $query = Sale::with(['client', 'personal', 'payments'])->latest('sold_at');

        if ($from = $request->query('from')) {
            $query->whereDate('sold_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('sold_at', '<=', $to);
        }

        if ($personalId = $request->integer('personal')) {
            $query->where('personal_id', $personalId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $sales = $query->paginate(25)->withQueryString();

        // Los totales se calculan sobre todo el filtro, no sobre la página, y
        // las anuladas no cuentan para la facturación.
        $totals = (clone $query)->reorder()->paid()->get();

        return view('sales.index', [
            'sales' => $sales,
            'staff' => Personal::bookable()->orderBy('full_name')->get(),
            'summary' => [
                'count' => $totals->count(),
                'total' => (float) $totals->sum('total'),
                'tips' => (float) $totals->sum('tip'),
                'discount' => (float) $totals->sum('discount'),
            ],
        ]);
    }

    /** La pantalla de cobro. */
    public function create(Request $request)
    {
        $appointment = null;

        // Al cobrar una cita, la comanda arranca con sus servicios cargados.
        if ($appointmentId = $request->integer('appointment')) {
            $appointment = Appointment::with(['services', 'client', 'personal'])->find($appointmentId);
        }

        return view('sales.create', [
            'appointment' => $appointment,
            'services' => Service::where('active', true)->with('category')->orderBy('name')->get(),
            'products' => Product::sellable()->with('stocks')->orderBy('name')->get(),
            'clients' => Client::where('active', true)->orderBy('full_name')->get(),
            'staff' => Personal::bookable()->orderBy('full_name')->get(),
            'branches' => Branch::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->targetCompanyId();
        $company = Company::findOrFail($companyId);

        $data = $request->validate([
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'personal_id' => ['nullable', Rule::exists('personal', 'id')->where('company_id', $companyId)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'appointment_id' => ['nullable', Rule::exists('appointments', 'id')->where('company_id', $companyId)],
            'discount' => 'nullable|numeric|min:0',
            'tip' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',

            'items' => 'required|array|min:1',
            'items.*.type' => ['required', Rule::in(['servicio', 'producto'])],
            'items.*.id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.01',

            'payments' => 'required|array|min:1',
            'payments.*.payment_method' => ['required', Rule::in(array_keys(SalePayment::methods()))],
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.reference' => 'nullable|string|max:255',
        ], [
            'items.required' => 'Añade al menos un servicio o producto a la comanda.',
            'payments.required' => 'Indica cómo se paga.',
        ]);

        $items = $this->itemResolver->resolve($data['items'], $data['personal_id'] ?? null);

        $sale = $this->registrar->register($company, $data, $items, $data['payments']);

        return redirect()->route('sales.show', $sale)
            ->with('success', "Venta {$sale->number} cobrada: "
                . \App\Support\Money::format($sale->total, $company).'.');
    }

    public function show(Sale $sale)
    {
        $sale->load([
            'items.personal', 'payments', 'client', 'personal',
            'branch', 'appointment', 'creator', 'canceller', 'cashSession.caja',
        ]);

        return view('sales.show', compact('sale'));
    }

    /** Comprobante imprimible, sin el armazón de la aplicación. */
    public function receipt(Sale $sale)
    {
        $sale->load(['items', 'payments', 'client', 'personal', 'branch']);

        return view('sales.receipt', [
            'sale' => $sale,
            'company' => $sale->company,
        ]);
    }

    /** Anular: revierte stock y caja. Una venta nunca se borra. */
    public function cancel(Request $request, Sale $sale)
    {
        $data = $request->validate([
            'cancel_reason' => 'nullable|string|max:255',
        ]);

        $this->registrar->cancel($sale, $data['cancel_reason'] ?? null);

        return redirect()->route('sales.show', $sale)
            ->with('success', "Venta {$sale->number} anulada. Se devolvió el stock y se registró el egreso en caja.");
    }

}

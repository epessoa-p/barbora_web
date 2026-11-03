<?php

namespace App\Support;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\FinanceAccount;
use App\Models\FinanceMovement;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * De dónde sale el dinero de un gasto o de un pago a un proveedor: de una
 * cuenta (capital, caja chica, banco…) o de la caja abierta.
 *
 *   source = account → finance_account_id (módulo «finanzas»)
 *   source = cash    → cash_session_id, un turno ABIERTO (módulo «caja»)
 *
 * charge() descuenta y devuelve los ids a guardar en el documento; refund()
 * los revierte al anular. Lo que sale del cajón es efectivo: el egreso de caja
 * va con el método que cuenta como efectivo, para que el arqueo lo descuente.
 */
class PaymentSource
{
    public function __construct(protected Treasury $treasury)
    {
    }

    /** Qué orígenes deja usar el plan de la barbería. */
    public static function available(Company $company): array
    {
        return array_keys(array_filter([
            'account' => $company->planAllows('finanzas'),
            'cash' => $company->planAllows('caja'),
        ]));
    }

    /**
     * Valida el origen del dinero. Devuelve [source, account|null, session|null].
     *
     * @return array{0: string, 1: ?FinanceAccount, 2: ?CashSession}
     */
    public function validate(Request $request, Company $company): array
    {
        $available = self::available($company);

        $data = $request->validate([
            'source' => ['required', Rule::in($available)],
            'finance_account_id' => [
                Rule::requiredIf($request->input('source') === 'account'), 'nullable',
                Rule::exists('finance_accounts', 'id')->where('company_id', $company->id)->where('active', true)->whereNull('deleted_at'),
            ],
            'cash_session_id' => [
                Rule::requiredIf($request->input('source') === 'cash'), 'nullable',
                Rule::exists('cash_sessions', 'id')->where('company_id', $company->id),
            ],
        ], [
            'source.required' => 'Elige de dónde sale el dinero.',
            'source.in' => 'Ese origen del dinero no está disponible en tu plan.',
            'finance_account_id.required' => 'Elige la cuenta de la que sale el dinero.',
            'cash_session_id.required' => 'Elige el turno de caja del que sale el dinero.',
        ]);

        if ($data['source'] === 'account') {
            return ['account', FinanceAccount::withoutGlobalScopes()->findOrFail($data['finance_account_id']), null];
        }

        $session = CashSession::withoutGlobalScopes()->findOrFail($data['cash_session_id']);

        if (! $session->isOpen()) {
            throw ValidationException::withMessages(['cash_session_id' => 'Ese turno de caja ya está cerrado: elige uno abierto.']);
        }

        return ['cash', null, $session];
    }

    /**
     * Saca el dinero. Devuelve los campos a guardar en el gasto o el pago.
     *
     * @return array<string, mixed>
     */
    public function charge(array $source, float $amount, string $concept, string $origin, ?string $reference = null, ?string $on = null): array
    {
        [$kind, $account, $session] = $source;

        if ($kind === 'account') {
            $movement = $this->treasury->move($account, 'egreso', $amount, $concept, [
                'origin' => $origin, 'reference' => $reference, 'occurred_on' => $on,
            ]);

            return ['source' => 'account', 'finance_account_id' => $account->id, 'finance_movement_id' => $movement->id];
        }

        $movement = CashMovement::withoutGlobalScopes()->create([
            'company_id' => $session->company_id,
            'cash_session_id' => $session->id,
            'type' => 'egreso',
            'payment_method' => self::cashMethod($session->company_id),
            'concept' => $concept,
            'amount' => round($amount, 2),
            'reference' => $reference,
            'created_by' => auth()->id(),
        ]);

        return ['source' => 'cash', 'cash_session_id' => $session->id, 'cash_movement_id' => $movement->id];
    }

    /**
     * Devuelve el dinero de un gasto o pago anulado.
     *
     * Cuenta: se anula su movimiento. Caja: se registra un ingreso en el mismo
     * turno si sigue abierto; si ya se cerró, en un turno abierto de la misma
     * sucursal (o cualquiera). Sin turno abierto no se puede: hay que abrir caja.
     */
    public function refund(object $document, string $concept): void
    {
        if ($document->source === 'account') {
            $movement = FinanceMovement::withoutGlobalScopes()->find($document->finance_movement_id);

            if ($movement && ! $movement->isCancelled()) {
                $this->treasury->cancel($movement);
            }

            return;
        }

        $original = CashSession::withoutGlobalScopes()->find($document->cash_session_id);
        $session = $original?->isOpen() ? $original : $this->anyOpenSession($document->company_id, $original?->branch_id);

        if (! $session) {
            throw ValidationException::withMessages([
                'error' => 'Para devolver el dinero a la caja tiene que haber un turno abierto. Abre la caja e inténtalo de nuevo.',
            ]);
        }

        DB::transaction(fn () => CashMovement::withoutGlobalScopes()->create([
            'company_id' => $session->company_id,
            'cash_session_id' => $session->id,
            'type' => 'ingreso',
            'payment_method' => self::cashMethod($session->company_id),
            'concept' => $concept,
            'amount' => $document->amount,
            'created_by' => auth()->id(),
        ]));
    }

    /** Turnos abiertos de la empresa, para elegir de cuál sale el dinero. */
    public static function openSessions(int $companyId, ?\App\Models\User $onlyFor = null)
    {
        $query = CashSession::withoutGlobalScopes()
            ->with('caja')
            ->where('company_id', $companyId)
            ->where('status', 'abierta')
            ->orderBy('opened_at');

        // Para elegir de dónde pagar: solo los turnos de sus sucursales.
        if ($onlyFor) {
            \App\Models\Branch::limitToWorkable($query, 'branch_id', $onlyFor, $companyId);
        }

        return $query->get();
    }

    protected function anyOpenSession(int $companyId, ?int $branchId): ?CashSession
    {
        $open = self::openSessions($companyId);

        return $open->firstWhere('branch_id', $branchId) ?? $open->first();
    }

    /** El método de lo que sale del cajón: el primero que cuenta como efectivo. */
    protected static function cashMethod(int $companyId): string
    {
        return app(Tenancy::class)->runFor($companyId, fn () => PaymentMethod::cashSlugs()[0] ?? 'efectivo');
    }
}

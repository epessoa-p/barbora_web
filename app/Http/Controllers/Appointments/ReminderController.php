<?php

namespace App\Http\Controllers\Appointments;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Recordatorios de cita. Módulo de plan «reservas_online».
 *
 * ALCANCE: esta pantalla NO envía nada sola. Lista las citas próximas que
 * todavía no se han avisado, abre WhatsApp o el teléfono con el mensaje ya
 * escrito, y guarda quién avisó y cuándo para que nadie llame dos veces.
 *
 * El envío automático necesita una pasarela de mensajería contratada, que el
 * proyecto todavía no tiene. Cuando la haya, escribirá las mismas columnas
 * (reminded_at / reminded_by) y esta pantalla seguirá sirviendo para el repaso
 * manual y para los clientes sin WhatsApp.
 */
class ReminderController extends Controller
{
    /** Cuántos días por delante se ofrecen para avisar. */
    private const DEFAULT_DAYS = 1;

    public function index(Request $request)
    {
        $days = max(0, min(14, (int) $request->query('days', self::DEFAULT_DAYS)));

        $from = Carbon::today()->startOfDay();
        $to = Carbon::today()->addDays($days)->endOfDay();

        $appointments = Appointment::with(['client', 'personal', 'services', 'branch'])
            ->whereBetween('starts_at', [$from, $to])
            ->whereIn('status', ['reservada', 'confirmada'])
            ->orderBy('starts_at')
            ->get();

        return view('appointments.reminders.index', [
            'days' => $days,
            'from' => $from,
            'to' => $to,
            'pending' => $appointments->whereNull('reminded_at')->values(),
            'done' => $appointments->whereNotNull('reminded_at')->values(),
            'company' => $request->attributes->get('tenant_company'),
        ]);
    }

    /** Marca la cita como avisada. */
    public function store(Request $request, Appointment $appointment)
    {
        if ($appointment->isReleased()) {
            throw ValidationException::withMessages([
                'error' => 'Esa cita ya no está activa.',
            ]);
        }

        $appointment->update([
            'reminded_at' => now(),
            'reminded_by' => auth()->id(),
        ]);

        return back()->with('success', 'Recordatorio marcado para '
            .($appointment->client?->full_name ?? 'el cliente').'.');
    }

    /** Deshace la marca, por si se apuntó por error. */
    public function destroy(Appointment $appointment)
    {
        $appointment->update(['reminded_at' => null, 'reminded_by' => null]);

        return back()->with('success', 'Recordatorio desmarcado: vuelve a la lista de pendientes.');
    }
}

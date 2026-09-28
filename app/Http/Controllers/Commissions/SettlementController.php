<?php

namespace App\Http\Controllers\Commissions;

use App\Http\Controllers\Controller;
use App\Models\CommissionSettlement;
use App\Models\Personal;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    public function index(Request $request)
    {
        $query = CommissionSettlement::with(['personal', 'paidBy'])->latest('paid_at');

        if ($personalId = $request->integer('personal')) {
            $query->where('personal_id', $personalId);
        }

        return view('commissions.settlements.index', [
            'settlements' => $query->paginate(25)->withQueryString(),
            'staff' => Personal::bookable()->orderBy('full_name')->get(),
        ]);
    }

    public function show(CommissionSettlement $settlement)
    {
        $settlement->load(['personal', 'paidBy', 'cashSession.caja', 'entries.sale', 'entries.item']);

        return view('commissions.settlements.show', compact('settlement'));
    }
}

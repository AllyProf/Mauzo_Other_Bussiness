<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Shift;
use App\Models\ShiftStockCheck;
use App\Services\ShiftPolicyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShiftController extends Controller
{
    public function index(ShiftPolicyService $shiftPolicy)
    {
        $this->authorizeAny(['open_shift', 'process_sales', 'view_all_shifts']);

        $businessId = Auth::user()->business_id;
        $business = Auth::user()->business;
        $openShift = Shift::openForUser(Auth::id(), $businessId);

        $query = Shift::where('business_id', $businessId)
            ->with('user')
            ->withCount('openingShortages')
            ->latest('opened_at');

        if (! $this->actsAsBusinessWideViewer() && ! Auth::user()->can('view_all_shifts')) {
            $query->where('user_id', Auth::id());
        }

        $this->scopeToActiveBranchUsers($query);

        $shifts = $query->paginate(15);

        $pendingHandoverShift = Shift::latestClosedAwaitingHandover(Auth::id(), $businessId);

        $recentShortages = collect();
        if ($this->actsAsBusinessWideViewer()) {
            $recentShortages = ShiftStockCheck::query()
                ->whereHas('shift', function ($q) use ($businessId) {
                    $q->where('business_id', $businessId);
                    $this->scopeToActiveBranchUsers($q);
                })
                ->shortages()
                ->with(['shift.user', 'item'])
                ->latest('recorded_at')
                ->limit(5)
                ->get();
        }

        return view('shifts.index', [
            'openShift' => $openShift,
            'shifts' => $shifts,
            'pendingHandoverShift' => $pendingHandoverShift,
            'recentShortages' => $recentShortages,
            'shiftOpenCheck' => $shiftPolicy->canOpenShift($business),
            'shiftOpenWindowLabel' => $shiftPolicy->openWindowLabel($business),
            'shiftOverdueStatus' => $openShift ? $shiftPolicy->shiftOverdueStatus($openShift, $business) : null,
        ]);
    }

    public function variances(Request $request)
    {
        $this->authorizeOwnerOrViewer();

        $businessId = Auth::user()->business_id;

        $query = ShiftStockCheck::query()
            ->whereHas('shift', function ($q) use ($businessId) {
                $q->where('business_id', $businessId);
                $this->scopeToActiveBranchUsers($q);
            })
            ->shortages()
            ->with(['shift.user', 'item.category', 'recorder', 'verifier'])
            ->latest('recorded_at');

        if ($request->status === 'pending') {
            $query->pendingVerification();
        } elseif ($request->status === 'verified') {
            $query->whereNotNull('verified_at');
        }

        if ($request->filled('check_type') && in_array($request->check_type, ['opening', 'closing'], true)) {
            $query->where('check_type', $request->check_type);
        }

        if ($request->filled('staff_id')) {
            $query->whereHas('shift', fn ($q) => $q->where('user_id', $request->staff_id));
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('notes', 'like', '%'.$term.'%')
                    ->orWhereHas('item', fn ($iq) => $iq->where('name', 'like', '%'.$term.'%'))
                    ->orWhereHas('shift.user', fn ($uq) => $uq->where('name', 'like', '%'.$term.'%'));
            });
        }

        $shortages = $query->paginate(20)->withQueryString();

        $stats = [
            'opening_shortages' => ShiftStockCheck::whereHas('shift', fn ($q) => $q->where('business_id', $businessId))
                ->where('check_type', 'opening')
                ->shortages()
                ->count(),
            'closing_shortages' => ShiftStockCheck::whereHas('shift', fn ($q) => $q->where('business_id', $businessId))
                ->where('check_type', 'closing')
                ->shortages()
                ->count(),
            'open_shift_shortages' => ShiftStockCheck::whereHas('shift', fn ($q) => $q->where('business_id', $businessId)->where('status', 'open'))
                ->where('check_type', 'opening')
                ->shortages()
                ->count(),
            'pending_verification' => ShiftStockCheck::whereHas('shift', fn ($q) => $q->where('business_id', $businessId))
                ->shortages()
                ->pendingVerification()
                ->count(),
        ];

        $staffMembers = \App\Models\User::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('shifts.variances', compact('shortages', 'stats', 'staffMembers'));
    }

    public function verifyShortage(Request $request, ShiftStockCheck $check)
    {
        $this->authorizeOwnerOrViewer();

        $check->load('shift');

        if ($check->shift->business_id !== Auth::user()->business_id) {
            abort(403);
        }

        if (! $check->isShort()) {
            return redirect()->back()->with('error', 'Only stock shortages can be verified.');
        }

        if ($check->isVerified()) {
            return redirect()->back()->with('info', 'This shortage was already verified.');
        }

        $request->validate([
            'owner_notes' => 'nullable|string|max:500',
        ]);

        $check->update([
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'owner_notes' => $request->owner_notes,
        ]);

        return redirect()->back()->with('success', 'Stock shortage marked as reviewed.');
    }

    public function create(ShiftPolicyService $shiftPolicy)
    {
        $this->authorizeAny(['open_shift', 'process_sales']);

        $businessId = Auth::user()->business_id;
        $business = Auth::user()->business;

        if (Shift::openForUser(Auth::id(), $businessId)) {
            return redirect()->route('shifts.index')
                ->with('info', 'You already have an open shift.');
        }

        $openCheck = $shiftPolicy->canOpenShift($business);
        if (! $openCheck['allowed']) {
            return redirect()->route('shifts.index')
                ->with('error', $openCheck['message']);
        }

        $items = Item::where('business_id', $businessId)
            ->where('current_stock', '>', 0)
            ->with(['category', 'packagings.packagingType', 'receivingPackaging'])
            ->orderBy('name')
            ->get();

        return view('shifts.open', compact('items'));
    }

    public function store(Request $request, ShiftPolicyService $shiftPolicy)
    {
        $this->authorizeAny(['open_shift', 'process_sales']);

        $businessId = Auth::user()->business_id;
        $business = Auth::user()->business;

        if (Shift::openForUser(Auth::id(), $businessId)) {
            return redirect()->route('shifts.index')
                ->with('error', 'You already have an open shift.');
        }

        $openCheck = $shiftPolicy->canOpenShift($business);
        if (! $openCheck['allowed']) {
            return redirect()->back()->with('error', $openCheck['message'])->withInput();
        }

        $items = Item::where('business_id', $businessId)
            ->where('current_stock', '>', 0)
            ->get()
            ->keyBy('id');

        $request->validate([
            'opening_notes' => 'nullable|string|max:2000',
            'counts' => 'required|array|min:1',
            'counts.*' => 'nullable|numeric|min:0',
            'notes' => 'nullable|array',
            'notes.*' => 'nullable|string|max:500',
        ]);

        if ($items->isEmpty()) {
            return redirect()->back()->with('error', 'No items with stock to count. Receive stock or add items first.')->withInput();
        }

        foreach ($items as $item) {
            $counted = $request->counts[$item->id] ?? null;
            if ($counted === null || $counted === '') {
                return redirect()->back()
                    ->with('error', "Physical count is required for {$item->name}.")
                    ->withInput();
            }

            $system = (float) $item->current_stock;
            $countedStock = (float) $counted;

            if ($countedStock < $system - 0.0001) {
                $note = trim((string) ($request->notes[$item->id] ?? ''));
                if ($note === '') {
                    return redirect()->back()
                        ->with('error', "Reason is required for {$item->name} — physical count is lower than system stock.")
                        ->withInput();
                }
            }
        }

        DB::beginTransaction();

        try {
            $shift = Shift::create([
                'business_id' => $businessId,
                'user_id' => Auth::id(),
                'opened_at' => now(),
                'status' => 'open',
                'opening_notes' => $request->opening_notes,
            ]);

            $varianceCount = 0;
            $now = now();

            foreach ($items as $item) {
                $countedStock = (float) $request->counts[$item->id];
                $system = (float) $item->current_stock;
                $variance = $countedStock - $system;

                if (abs($variance) > 0.0001) {
                    $varianceCount++;
                }

                ShiftStockCheck::create([
                    'shift_id' => $shift->id,
                    'item_id' => $item->id,
                    'check_type' => 'opening',
                    'system_stock' => $system,
                    'counted_stock' => $countedStock,
                    'variance' => $variance,
                    'notes' => $request->notes[$item->id] ?? null,
                    'recorded_by' => Auth::id(),
                    'recorded_at' => $now,
                ]);

                // Align system stock with verified physical count so POS sells the correct quantity
                if (abs($variance) > 0.0001) {
                    $item->update(['current_stock' => $countedStock]);
                }
            }

            $shift->update(['opening_variance_count' => $varianceCount]);

            DB::commit();

            return redirect()->route('sales.create')
                ->with('success', 'Shift opened. Physical stock check saved — you can now sell on POS.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Failed to open shift: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Shift $shift)
    {
        $this->authorizeShiftAccess($shift);

        $shift->load([
            'user',
            'openingChecks.item.category',
            'openingChecks.recorder',
            'openingShortages.item.category',
            'openingShortages.recorder',
            'closingChecks.item.category',
            'closingChecks.recorder',
            'sales' => fn ($q) => $q->where('payment_status', '!=', 'cancelled')->latest(),
        ]);

        $shift->refreshTotals();

        return view('shifts.show', compact('shift'));
    }

    public function closeForm(Shift $shift)
    {
        $this->authorizeShiftAccess($shift);

        if (! $shift->isOpen()) {
            return redirect()->route('shifts.show', $shift)->with('error', 'This shift is already closed.');
        }

        if ($shift->user_id !== Auth::id() && ! $this->actsAsBusinessWideViewer()) {
            abort(403, 'Only the shift owner can close this shift.');
        }

        return redirect()->route('day-closing.index', ['shift' => $shift->id]);
    }

    public function close(Request $request, Shift $shift)
    {
        $this->authorizeShiftAccess($shift);

        if (! $shift->isOpen()) {
            return redirect()->route('shifts.show', $shift)->with('error', 'This shift is already closed.');
        }

        if ($shift->user_id !== Auth::id() && ! $this->actsAsBusinessWideViewer()) {
            abort(403, 'Only the shift owner can close this shift.');
        }

        return redirect()->route('day-closing.index', ['shift' => $shift->id]);
    }

    private function authorizeShiftAccess(Shift $shift): void
    {
        $this->authorizeAny(['open_shift', 'process_sales', 'view_all_shifts']);

        if ($shift->business_id != Auth::user()->business_id) {
            abort(403);
        }

        if (! $this->actsAsBusinessWideViewer() && ! Auth::user()->can('view_all_shifts') && $shift->user_id !== Auth::id()) {
            abort(403, 'You can only view your own shifts.');
        }
    }

    private function authorizeOwnerOrViewer(): void
    {
        $this->authorizeAny(['verify_stock_shortages', 'view_reports']);
    }
}

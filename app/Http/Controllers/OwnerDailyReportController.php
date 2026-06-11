<?php

namespace App\Http\Controllers;

use App\Models\BusinessOwnerExpense;
use App\Models\DayClosing;
use App\Models\OwnerDailyReport;
use App\Services\OwnerDailyReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OwnerDailyReportController extends Controller
{
    public function __construct(private OwnerDailyReportService $reportService)
    {
    }

    public function index(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize('view_reports');

        $business = $this->currentBusiness() ?? Auth::user()->business;
        $businessId = $this->currentBusinessId();

        $query = DayClosing::where('business_id', $businessId)
            ->where('status', 'verified')
            ->with(['user', 'verifier', 'expenses']);

        $this->scopeDayClosingsForActiveBranch($query);

        if ($request->filled('start_date')) {
            $query->whereDate('closing_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('closing_date', '<=', $request->end_date);
        }

        $closings = $query->orderByDesc('closing_date')
            ->orderByDesc('verified_at')
            ->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $ledgers = $closings->getCollection()->map(
            fn (DayClosing $closing) => $this->reportService->buildMasterSheetRow($business, $closing)
        );

        $businessTypes = $this->reportService->businessTypesForMasterSheet($business);
        $multiBusiness = count($businessTypes) > 1;
        $ledgers = $this->reportService->expandMasterSheetLedgersByBusinessType($ledgers, $businessTypes);

        $activeBusinessType = $request->get('business_type');
        if ($activeBusinessType) {
            $ledgers = $ledgers->filter(function ($ledger) use ($activeBusinessType) {
                if ($ledger['is_placeholder'] ?? false) {
                    return false;
                }

                return ($ledger['business_type_key'] ?? null) === $activeBusinessType;
            })->values();
        }

        if ($closings->currentPage() === 1 && ! $request->filled('start_date') && ! $request->filled('end_date')) {
            foreach ($this->reportService->buildOpenDayRows($business) as $openingDayRow) {
                $ledgers = $ledgers->prepend($openingDayRow);
            }
        }

        $pendingClosings = DayClosing::where('business_id', $businessId)
            ->where('status', 'submitted')
            ->with('user');

        $this->scopeDayClosingsForActiveBranch($pendingClosings);

        $pendingClosings = $pendingClosings->latest('closing_date')->get();

        return view('owner-reports.index', compact(
            'closings',
            'ledgers',
            'business',
            'pendingClosings',
            'businessTypes',
            'multiBusiness',
            'activeBusinessType',
        ));
    }

    public function show(Request $request, string $date)
    {
        \Illuminate\Support\Facades\Gate::authorize('view_reports');

        $parsedDate = Carbon::parse($date)->toDateString();

        return redirect()->route('owner-reports.index', [
            'start_date' => $parsedDate,
            'end_date' => $parsedDate,
            'highlight_date' => $parsedDate,
        ]);
    }

    public function storeExpense(Request $request, string $date)
    {
        \Illuminate\Support\Facades\Gate::authorize('view_reports');

        if (Auth::user()->role !== 'owner' && Auth::user()->role !== 'super_admin') {
            abort(403, 'Only the business owner can record restock expenses.');
        }

        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'category' => 'nullable|in:restock,payment,salary,operational,other',
            'fund_source' => 'nullable|in:circulation,profit',
        ]);

        $business = $this->currentBusiness() ?? Auth::user()->business;
        $parsedDate = Carbon::parse($date)->toDateString();

        $report = OwnerDailyReport::where('business_id', $business->id)
            ->whereDate('report_date', $parsedDate)
            ->first();

        if ($report && $report->status === 'finalized') {
            return redirect()->back()->with('error', 'This day is finalized. Cannot add expenses.');
        }

        DB::beginTransaction();
        try {
            $dayClosing = DayClosing::where('business_id', $business->id)->whereDate('closing_date', $parsedDate)->first();

            BusinessOwnerExpense::create([
                'business_id' => $business->id,
                'owner_daily_report_id' => $report?->id,
                'expense_date' => $parsedDate,
                'description' => $request->description,
                'amount' => $request->amount,
                'category' => $request->category ?? 'restock',
                'fund_source' => $request->fund_source ?? ($business->expense_deduct_from ?? 'circulation'),
                'recorded_by' => Auth::id(),
            ]);

            $this->reportService->syncReport($business, $parsedDate, $dayClosing);

            DB::commit();

            return redirect()->route('owner-reports.index', [
                'start_date' => $parsedDate,
                'end_date' => $parsedDate,
                'highlight_date' => $parsedDate,
            ])->with('success', 'Expense recorded. Circulation and profit updated.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Failed to record expense: ' . $e->getMessage());
        }
    }

    public function destroyExpense(string $date, BusinessOwnerExpense $expense)
    {
        \Illuminate\Support\Facades\Gate::authorize('view_reports');

        if (Auth::user()->role !== 'owner' && Auth::user()->role !== 'super_admin') {
            abort(403);
        }

        if ($expense->business_id != $this->currentBusinessId()) {
            abort(403);
        }

        $parsedDate = Carbon::parse($date)->toDateString();

        if ($expense->report && $expense->report->status === 'finalized') {
            return redirect()->back()->with('error', 'Cannot delete expense from a finalized report.');
        }

        $expense->delete();
        $business = $this->currentBusiness() ?? Auth::user()->business;
        $dayClosing = DayClosing::where('business_id', $business->id)->whereDate('closing_date', $parsedDate)->first();
        $this->reportService->syncReport($business, $parsedDate, $dayClosing);

        return redirect()->route('owner-reports.index', [
            'start_date' => $parsedDate,
            'end_date' => $parsedDate,
            'highlight_date' => $parsedDate,
        ])->with('success', 'Expense removed.');
    }

    public function finalize(Request $request, string $date)
    {
        $this->authorizeAny(['finalize_reports', 'view_reports']);

        if (Auth::user()->role !== 'owner' && Auth::user()->role !== 'super_admin') {
            abort(403, 'Only the business owner can finalize the daily report.');
        }

        $business = $this->currentBusiness() ?? Auth::user()->business;
        $parsedDate = Carbon::parse($date)->toDateString();

        $dayClosing = DayClosing::where('business_id', $business->id)
            ->whereDate('closing_date', $parsedDate)
            ->first();

        if (! $dayClosing) {
            return redirect()->back()->with('error', 'No reconciliation found for this date.');
        }

        $report = $this->reportService->syncReport($business, $parsedDate, $dayClosing);

        if ($report->status === 'finalized') {
            return redirect()->back()->with('error', 'This report is already finalized.');
        }

        $request->validate(['owner_notes' => 'nullable|string|max:2000']);

        DB::beginTransaction();
        try {
            if ($dayClosing->status === 'verified') {
                // Already verified on day-closing review page
            } elseif ($dayClosing->status === 'submitted') {
                $dayClosing->update([
                    'status' => 'verified',
                    'verified_by' => Auth::id(),
                    'verified_at' => now(),
                ]);
            }

            $report->update([
                'status' => 'finalized',
                'finalized_by' => Auth::id(),
                'finalized_at' => now(),
                'owner_notes' => $request->owner_notes,
            ]);

            $business->update([
                'circulation_balance' => $report->closing_circulation,
            ]);

            DB::commit();

            return redirect()->route('owner-reports.index')
                ->with('success', 'Daily report finalized. Circulation balance carried to the next day.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Failed to finalize: ' . $e->getMessage());
        }
    }

    private function enrichBreakdownLabels(array $breakdown, int $businessId, string $date): array
    {
        $labels = $this->reportService->buildPlatformBreakdown($businessId, $date);

        foreach ($breakdown as $key => &$item) {
            if (is_array($item)) {
                $item['label'] = $item['label'] ?? ($labels[$key]['label'] ?? ucwords(str_replace('_', ' ', $key)));
            }
        }

        return $breakdown;
    }
}

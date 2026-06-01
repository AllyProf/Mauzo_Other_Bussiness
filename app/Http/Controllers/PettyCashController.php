<?php

namespace App\Http\Controllers;

use App\Models\BusinessOwnerExpense;
use App\Models\DayClosing;
use App\Models\OwnerDailyReport;
use App\Models\User;
use App\Services\OwnerDailyReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PettyCashController extends Controller
{
    public function __construct(private OwnerDailyReportService $reportService)
    {
    }

    public function index(Request $request)
    {
        $this->ensureOwner();

        $business = Auth::user()->business;
        $selectedDate = $request->filled('date')
            ? Carbon::parse($request->date)->toDateString()
            : now()->toDateString();

        $balances = $this->reportService->getPettyCashBalances($business, $selectedDate);

        $staffMembers = User::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $expensesQuery = BusinessOwnerExpense::where('business_id', $business->id)
            ->with(['recorder', 'issuedTo', 'report'])
            ->latest('expense_date')
            ->latest('id');

        if ($request->filled('start_date')) {
            $expensesQuery->whereDate('expense_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $expensesQuery->whereDate('expense_date', '<=', $request->end_date);
        }
        if ($request->filled('fund_source') && in_array($request->fund_source, ['circulation', 'profit'], true)) {
            $expensesQuery->where('fund_source', $request->fund_source);
        }
        if ($request->filled('category') && array_key_exists($request->category, BusinessOwnerExpense::CATEGORIES)) {
            $expensesQuery->where('category', $request->category);
        }

        $expenses = $expensesQuery->paginate(20)->withQueryString();

        return view('petty-cash.index', compact(
            'business',
            'balances',
            'selectedDate',
            'staffMembers',
            'expenses'
        ));
    }

    public function balances(Request $request)
    {
        $this->ensureOwner();

        $request->validate([
            'date' => 'required|date',
        ]);

        $business = Auth::user()->business;
        $date = Carbon::parse($request->date)->toDateString();
        $balances = $this->reportService->getPettyCashBalances($business, $date);

        return response()->json([
            'date' => $date,
            'date_label' => Carbon::parse($date)->format('d M, Y'),
            'opening_circulation' => $balances['opening_circulation'],
            'opening_profit' => $balances['opening_profit'],
            'available_circulation' => $balances['available_circulation'],
            'available_profit' => $balances['available_profit'],
            'owner_circulation_spent' => $balances['owner_circulation_spent'],
            'owner_profit_spent' => $balances['owner_profit_spent'],
            'daily_net_profit' => $balances['daily_net_profit'],
            'is_finalized' => $balances['is_finalized'],
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureOwner();

        $business = Auth::user()->business;

        $request->validate([
            'expense_date' => 'required|date',
            'description' => 'required|string|max:1000',
            'amount' => 'required|numeric|min:0.01',
            'category' => 'required|in:restock,payment,salary,operational,other',
            'fund_source' => 'required|in:circulation,profit',
            'issued_to_user_id' => 'nullable|exists:users,id',
        ]);

        if ($request->filled('issued_to_user_id')) {
            $recipient = User::where('business_id', $business->id)
                ->where('is_active', true)
                ->find($request->issued_to_user_id);

            if (! $recipient) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Selected staff member is not valid for this business.');
            }
        }

        $parsedDate = Carbon::parse($request->expense_date)->toDateString();
        $amount = (float) $request->amount;
        $fundSource = $request->fund_source;

        $report = OwnerDailyReport::where('business_id', $business->id)
            ->whereDate('report_date', $parsedDate)
            ->first();

        if ($report && $report->status === 'finalized') {
            return redirect()->back()->with('error', 'That day is finalized. Petty cash cannot be changed for finalized days.');
        }

        $balances = $this->reportService->getPettyCashBalances($business, $parsedDate);
        $available = $fundSource === 'profit'
            ? $balances['available_profit']
            : $balances['available_circulation'];

        if ($amount > $available) {
            $label = $fundSource === 'profit' ? 'profit' : 'circulation money';

            return redirect()->back()
                ->withInput()
                ->with('error', 'Amount exceeds available '.$label.' on '.$parsedDate.' (TZS '.number_format($available, 0).' available).');
        }

        DB::beginTransaction();
        try {
            $dayClosing = DayClosing::where('business_id', $business->id)
                ->whereDate('closing_date', $parsedDate)
                ->first();

            BusinessOwnerExpense::create([
                'business_id' => $business->id,
                'owner_daily_report_id' => $report?->id,
                'expense_date' => $parsedDate,
                'description' => $request->description,
                'amount' => $amount,
                'category' => $request->category,
                'fund_source' => $fundSource,
                'recorded_by' => Auth::id(),
                'issued_to_user_id' => $request->issued_to_user_id,
            ]);

            $this->reportService->syncReport($business, $parsedDate, $dayClosing);

            DB::commit();

            return redirect()->route('petty-cash.index', ['date' => $parsedDate])
                ->with('success', 'Petty cash issued successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Failed to issue petty cash: '.$e->getMessage());
        }
    }

    public function destroy(BusinessOwnerExpense $expense)
    {
        $this->ensureOwner();

        if ($expense->business_id !== Auth::user()->business_id) {
            abort(403);
        }

        if ($expense->report && $expense->report->status === 'finalized') {
            return redirect()->back()->with('error', 'Cannot remove petty cash from a finalized day.');
        }

        $parsedDate = $expense->expense_date->toDateString();
        $expense->delete();

        $dayClosing = DayClosing::where('business_id', Auth::user()->business_id)
            ->whereDate('closing_date', $parsedDate)
            ->first();

        $this->reportService->syncReport(Auth::user()->business, $parsedDate, $dayClosing);

        return redirect()->back()->with('success', 'Petty cash entry removed.');
    }

    private function ensureOwner(): void
    {
        $this->authorizeAny(['manage_petty_cash', 'view_reports']);
    }
}

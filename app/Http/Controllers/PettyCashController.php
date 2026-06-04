<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BusinessOwnerExpense;
use App\Models\DayClosing;
use App\Models\OwnerDailyReport;
use App\Models\User;
use App\Services\OwnerDailyReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PettyCashController extends Controller
{
    public function __construct(private OwnerDailyReportService $reportService)
    {
    }

    public function index(Request $request)
    {
        $this->ensureOwner();

        $business = $this->currentBusiness() ?? Auth::user()->business;
        $branchFilterId = active_branch_id();
        $activeBranchName = $branchFilterId
            ? (active_branch()?->name ?? Branch::find($branchFilterId)?->name ?? 'Branch')
            : null;
        $viewingAllBranches = $this->actsAsBusinessWideViewer() && ! $branchFilterId;

        $businessTypes = $branchFilterId
            ? $business->branchPosBusinessTypesMeta($branchFilterId)
            : $business->posBusinessTypesMeta();
        $multiBusiness = count($businessTypes) > 1;
        $activeBusinessType = $request->get('business_type');

        $selectedDate = $request->filled('date')
            ? Carbon::parse($request->date)->toDateString()
            : now()->toDateString();

        $balances = $this->reportService->getPettyCashBalances(
            $business,
            $selectedDate,
            $activeBusinessType ?: null
        );

        $staffQuery = User::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('name');

        if ($branchFilterId) {
            $this->scopeStaffToActiveBranch($staffQuery);
        }

        $staffMembers = $staffQuery->get(['id', 'name', 'role']);

        $expensesQuery = BusinessOwnerExpense::where('business_id', $business->id)
            ->with(['recorder', 'issuedTo', 'report', 'branch'])
            ->latest('expense_date')
            ->latest('id');

        if ($branchFilterId) {
            $expensesQuery->where(function ($query) use ($branchFilterId) {
                $query->where('branch_id', $branchFilterId)->orWhereNull('branch_id');
            });
        }

        if ($activeBusinessType) {
            $expensesQuery->where('business_type_key', $activeBusinessType);
        }

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
            'expenses',
            'businessTypes',
            'multiBusiness',
            'activeBusinessType',
            'activeBranchName',
            'branchFilterId',
            'viewingAllBranches',
        ));
    }

    public function balances(Request $request)
    {
        $this->ensureOwner();

        $business = $this->currentBusiness() ?? Auth::user()->business;

        $request->validate([
            'date' => 'required|date',
            'business_type' => ['nullable', 'string', 'max:100'],
        ]);

        $date = Carbon::parse($request->date)->toDateString();
        $businessTypeKey = $request->filled('business_type') ? $request->business_type : null;
        $balances = $this->reportService->getPettyCashBalances($business, $date, $businessTypeKey);

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
            'business_type_key' => $balances['business_type_key'],
            'business_type_label' => $balances['business_type_label'],
            'scoped_to_business_type' => $balances['scoped_to_business_type'],
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureOwner();

        $business = $this->currentBusiness() ?? Auth::user()->business;
        $branchFilterId = active_branch_id();
        $businessTypes = $branchFilterId
            ? $business->branchPosBusinessTypesMeta($branchFilterId)
            : $business->posBusinessTypesMeta();
        $typeKeys = collect($businessTypes)->pluck('key')->filter()->values()->all();

        $request->validate([
            'expense_date' => 'required|date',
            'description' => 'required|string|max:1000',
            'amount' => 'required|numeric|min:0.01',
            'category' => 'required|in:restock,payment,salary,operational,other',
            'fund_source' => 'required|in:circulation,profit',
            'issued_to_user_id' => 'nullable|exists:users,id',
            'business_type_key' => [
                Rule::requiredIf(count($typeKeys) > 1),
                'nullable',
                'string',
                'max:100',
                Rule::in($typeKeys),
            ],
        ]);

        if ($request->filled('issued_to_user_id')) {
            $recipientQuery = User::where('business_id', $business->id)
                ->where('is_active', true)
                ->where('id', $request->issued_to_user_id);

            if ($branchFilterId) {
                $this->scopeStaffToActiveBranch($recipientQuery);
            }

            if (! $recipientQuery->exists()) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Selected staff member is not valid for this branch.');
            }
        }

        $parsedDate = Carbon::parse($request->expense_date)->toDateString();
        $amount = (float) $request->amount;
        $fundSource = $request->fund_source;
        $businessTypeKey = $request->business_type_key ?: null;

        $report = OwnerDailyReport::where('business_id', $business->id)
            ->whereDate('report_date', $parsedDate)
            ->first();

        if ($report && $report->status === 'finalized') {
            return redirect()->back()->with('error', 'That day is finalized. Petty cash cannot be changed for finalized days.');
        }

        $balances = $this->reportService->getPettyCashBalances($business, $parsedDate, $businessTypeKey);
        $available = $fundSource === 'profit'
            ? $balances['available_profit']
            : $balances['available_circulation'];

        if ($amount > $available) {
            $label = $fundSource === 'profit' ? 'profit' : 'circulation money';
            $scope = $businessTypeKey ? ' for '.$balances['business_type_label'] : '';

            return redirect()->back()
                ->withInput()
                ->with('error', 'Amount exceeds available '.$label.$scope.' on '.$parsedDate.' (TZS '.number_format($available, 0).' available).');
        }

        DB::beginTransaction();
        try {
            $dayClosing = DayClosing::where('business_id', $business->id)
                ->whereDate('closing_date', $parsedDate)
                ->first();

            BusinessOwnerExpense::create([
                'business_id' => $business->id,
                'branch_id' => $branchFilterId,
                'business_type_key' => $businessTypeKey,
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

            return redirect()->route('petty-cash.index', array_filter([
                'date' => $parsedDate,
                'business_type' => $businessTypeKey,
            ]))->with('success', 'Petty cash issued successfully.');
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

        if ($branchFilterId = active_branch_id()) {
            if ($expense->branch_id && (int) $expense->branch_id !== (int) $branchFilterId) {
                abort(403, 'This petty cash entry belongs to another branch.');
            }
        }

        if ($expense->report && $expense->report->status === 'finalized') {
            return redirect()->back()->with('error', 'Cannot remove petty cash from a finalized day.');
        }

        $parsedDate = $expense->expense_date->toDateString();
        $businessTypeKey = $expense->business_type_key;
        $expense->delete();

        $dayClosing = DayClosing::where('business_id', Auth::user()->business_id)
            ->whereDate('closing_date', $parsedDate)
            ->first();

        $this->reportService->syncReport(Auth::user()->business, $parsedDate, $dayClosing);

        return redirect()->route('petty-cash.index', array_filter([
            'date' => $parsedDate,
            'business_type' => $businessTypeKey,
        ]))->with('success', 'Petty cash entry removed.');
    }

    private function ensureOwner(): void
    {
        $this->authorizeAny(['manage_petty_cash', 'view_reports']);
    }
}

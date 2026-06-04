<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\SalesTarget;
use App\Models\User;
use App\Services\SalesTargetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SalesTargetController extends Controller
{
    public function __construct(private SalesTargetService $targets)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeAny(['manage_business_settings']);

        $business = Auth::user()->business;
        $filter = $this->branchBusinessFilterContext($request);
        extract($filter);
        $branches = Branch::where('business_id', $business->id)->where('is_active', true)->orderBy('name')->get();
        $staffQuery = User::where('business_id', $business->id)
            ->where('role', '!=', 'owner')
            ->where('is_active', true)
            ->orderBy('name');
        $this->scopeStaffToActiveBranch($staffQuery);
        $staff = $staffQuery->get(['id', 'name', 'branch_id']);

        $editTarget = null;
        if ($request->filled('edit')) {
            $editTarget = SalesTarget::where('business_id', $business->id)
                ->find($request->integer('edit'));
        }

        $targetsQuery = SalesTarget::where('business_id', $business->id)
            ->with(['branch', 'user', 'creator'])
            ->orderByDesc('period_start')
            ->orderBy('period_type');

        if ($branchFilterId) {
            $targetsQuery->where(function ($query) use ($branchFilterId) {
                $query->where('branch_id', $branchFilterId)->orWhereNull('branch_id');
            });
        }

        if ($activeBusinessType) {
            $targetsQuery->where(function ($query) use ($activeBusinessType) {
                $query->where('business_type_key', $activeBusinessType)->orWhereNull('business_type_key');
            });
        }

        $targets = $targetsQuery->paginate(20)->withQueryString();

        $targets->getCollection()->transform(function (SalesTarget $target) use ($business) {
            $actual = $this->targets->actualRevenue($target);
            $targetAmount = max(0.01, (float) $target->target_amount);

            return [
                'model' => $target,
                'actual' => $actual,
                'progress' => (int) min(100, round(($actual / $targetAmount) * 100)),
                'scope_label' => $this->targets->scopeSummary($target, $business),
            ];
        });

        return view('sales-targets.index', compact(
            'business',
            'branches',
            'staff',
            'targets',
            'editTarget',
        ) + $filter);
    }

    public function store(Request $request)
    {
        $this->authorizeAny(['manage_business_settings']);

        $business = Auth::user()->business;
        $this->validateTarget($request, $business);
        $payload = $this->buildPayload($request, $business);

        $this->targets->saveTarget($business, $payload, Auth::id());

        return redirect()->route('sales-targets.index')
            ->with('success', 'Sales target saved successfully.');
    }

    public function update(Request $request, SalesTarget $salesTarget)
    {
        $this->authorizeAny(['manage_business_settings']);

        if ($salesTarget->business_id !== Auth::user()->business_id) {
            abort(403);
        }

        $business = Auth::user()->business;
        $this->validateTarget($request, $business);
        $payload = $this->buildPayload($request, $business);

        $this->targets->updateTarget($salesTarget, $business, $payload);

        return redirect()->route('sales-targets.index')
            ->with('success', 'Sales target updated successfully.');
    }

    public function destroy(SalesTarget $salesTarget)
    {
        $this->authorizeAny(['manage_business_settings']);

        if ($salesTarget->business_id !== Auth::user()->business_id) {
            abort(403);
        }

        $salesTarget->delete();

        return redirect()->route('sales-targets.index')
            ->with('success', 'Sales target removed.');
    }

    private function validateTarget(Request $request, $business): array
    {
        $allowedTypes = collect($business->posBusinessTypesMeta())->pluck('key')->push('other')->unique()->all();

        return $request->validate([
            'period_type' => 'required|in:daily,weekly,monthly',
            'period_date' => 'required|date',
            'target_amount' => 'required|numeric|min:1',
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('business_id', $business->id),
            ],
            'business_type_key' => ['nullable', 'string', Rule::in($allowedTypes)],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('business_id', $business->id),
            ],
            'notes' => 'nullable|string|max:255',
        ]);
    }

    private function buildPayload(Request $request, $business): array
    {
        $staffUser = $request->filled('user_id')
            ? User::where('business_id', $business->id)->find((int) $request->user_id)
            : null;

        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;
        if (! $branchId && $staffUser?->branch_id) {
            $branchId = (int) $staffUser->branch_id;
        }

        return [
            'period_type' => $request->period_type,
            'period_date' => $request->period_date,
            'target_amount' => $request->target_amount,
            'branch_id' => $branchId,
            'business_type_key' => $request->filled('business_type_key') ? $request->business_type_key : null,
            'user_id' => $staffUser?->id,
            'notes' => $request->notes,
        ];
    }
}

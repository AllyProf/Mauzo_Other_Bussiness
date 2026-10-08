<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\Concerns\UsesWebBranchContext;
use App\Models\DayClosing;
use App\Models\MoneyShortSettlement;
use App\Services\MoneyShortApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MoneyShortController extends ApiController
{
    use UsesWebBranchContext;

    public function __construct(private MoneyShortApiService $moneyShorts)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_money_shorts', 'verify_day_closing', 'view_reports'])) {
            return $deny;
        }

        $request->validate([
            'status' => 'nullable|in:all,outstanding,settled',
            'business_type' => 'nullable|string|max:100',
            'search' => 'nullable|string|max:255',
            'branch_id' => 'nullable|integer',
        ]);

        $this->bootWebBranchContext($request);
        $filter = $this->branchBusinessFilterContext($request);
        $business = $filter['business'] ?? $this->apiBusiness();

        if (! $business) {
            return $this->error('No active business.', 422);
        }

        $data = $this->moneyShorts->index(
            $request->user(),
            $business,
            $filter,
            $request,
            fn ($query) => $this->scopeDayClosingsForActiveBranch($query)
        );

        return $this->success($data);
    }

    public function recordPayment(Request $request, DayClosing $dayClosing): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_money_shorts', 'verify_day_closing', 'view_reports'])) {
            return $deny;
        }

        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No active business.', 422);
        }

        try {
            $data = $this->moneyShorts->recordPayment($request->user(), $business, $dayClosing, $request->all());

            $date = $data['settlement']['settlement_date'] ?? $request->input('settlement_date');

            return $this->success($data, 'Payment recorded and posted to the Master Sheet for '.$date.'.', 201);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to record payment: '.$e->getMessage(), 500);
        }
    }

    public function recordSalaryDeduction(Request $request, DayClosing $dayClosing): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_money_shorts', 'verify_day_closing', 'view_reports'])) {
            return $deny;
        }

        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No active business.', 422);
        }

        try {
            $data = $this->moneyShorts->recordSalaryDeduction($request->user(), $business, $dayClosing, $request->all());

            return $this->success($data, 'Salary deduction recorded.', 201);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to record salary deduction: '.$e->getMessage(), 500);
        }
    }

    public function undoSettlement(Request $request, MoneyShortSettlement $settlement): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_money_shorts', 'verify_day_closing', 'view_reports'])) {
            return $deny;
        }

        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No active business.', 422);
        }

        try {
            $data = $this->moneyShorts->undoSettlement($request->user(), $business, $settlement);
            $undone = $data['undone'];
            $message = $undone['type_label'].' of '.number_format($undone['amount'], 0).' undone.';
            if ($undone['was_cash_payment']) {
                $message .= ' Master Sheet for '.$undone['settlement_date'].' has been updated.';
            }
            if (($data['new_balance'] ?? 0) > 0) {
                $message .= ' Outstanding balance is now '.number_format($data['new_balance'], 0).'.';
            }

            return $this->success($data, $message);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to undo settlement: '.$e->getMessage(), 500);
        }
    }
}

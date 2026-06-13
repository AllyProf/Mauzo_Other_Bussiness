<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalePaymentRecorder
{
    public function __construct(private Sale $sale)
    {
    }

    public static function for(Sale $sale): self
    {
        return new self($sale);
    }

    public function applyFromRequest(Request $request, float $balanceDue): string
    {
        $business = $this->sale->business ?? Auth::user()->business;
        $method = $business->findPaymentMethod($request->input('payment_method'));

        if (! $method) {
            throw ValidationException::withMessages([
                'payment_method' => 'Selected payment method is not available.',
            ]);
        }

        $isCredit = ($method['type'] ?? '') === 'credit';
        $customerFields = $this->resolveCustomerFields($request);

        if ($isCredit) {
            return $this->applyCredit($request, $balanceDue, $customerFields, $method);
        }

        return $this->applyImmediate($request, $balanceDue, $customerFields, $method);
    }

    public static function validatePaymentRules(Request $request, array $enabledKeys, float $balanceDue): array
    {
        $rules = [
            'payment_method' => ['required', 'string', Rule::in($enabledKeys)],
        ];

        $business = Auth::user()->business;
        $method = $business->findPaymentMethod($request->input('payment_method'));

        if (! $method) {
            return $rules;
        }

        if (($method['type'] ?? '') === 'credit') {
            $rules['customer_name'] = 'required|string|max:255';
            $rules['customer_phone'] = 'nullable|string|max:50';
            $rules['due_date'] = 'required|date';
            $rules['amount_paid'] = 'nullable|numeric|min:0';

            return $rules;
        }

        $rules['amount_paid'] = 'required|numeric|min:0.01';

        $amountToPay = min((float) $request->input('amount_paid', 0), $balanceDue);
        $willBePartial = $amountToPay > 0 && $amountToPay < $balanceDue;

        if ($willBePartial) {
            $rules['customer_name'] = 'required|string|max:255';
            $rules['customer_phone'] = 'required|string|max:50';
            $rules['due_date'] = 'required|date';
        }

        if (! empty($method['requires_reference'])) {
            $rules['payment_provider'] = 'required|string|max:255';
            $rules['transaction_reference'] = 'required|string|max:255';
        }

        return $rules;
    }

    private function applyCredit(Request $request, float $balanceDue, array $customerFields, array $method): string
    {
        $amountToPay = min(max(0, (float) $request->input('amount_paid', 0)), $balanceDue);

        if ($amountToPay > 0) {
            $this->createPayment($request, $amountToPay, $method['key']);
        }

        $newAmountPaid = (float) $this->sale->amount_paid + $amountToPay;
        $status = $newAmountPaid > 0 ? 'partial' : 'debt';

        $this->sale->update([
            'payment_status' => $status,
            'payment_method' => $method['key'],
            'customer_id' => $customerFields['customer_id'],
            'customer_name' => $customerFields['customer_name'],
            'customer_phone' => $customerFields['customer_phone'],
            'due_date' => $request->due_date,
            'amount_paid' => $newAmountPaid,
            ...($this->sale->due_date != $request->due_date ? [
                'debt_due_soon_sms_sent_at' => null,
                'debt_due_soon_second_sms_sent_at' => null,
                'debt_due_today_sms_sent_at' => null,
                'debt_overdue_sms_sent_at' => null,
            ] : []),
        ]);

        app(SaleStockService::class)->deductIfPaid($this->sale->fresh());

        if ($amountToPay > 0) {
            return 'Invoice saved on credit with '.money($amountToPay).' collected now. Balance '.money($balanceDue - $amountToPay).' due '.$request->due_date.'.';
        }

        return 'Invoice saved as credit — '.money($balanceDue).' due '.$request->due_date.'.';
    }

    private function applyImmediate(Request $request, float $balanceDue, array $customerFields, array $method): string
    {
        $amountToPay = min((float) $request->amount_paid, $balanceDue);
        $newAmountPaid = (float) $this->sale->amount_paid + $amountToPay;
        $status = $newAmountPaid >= (float) $this->sale->total_amount ? 'paid' : 'partial';

        $this->createPayment($request, $amountToPay, $method['key']);

        $updateData = [
            'amount_paid' => $newAmountPaid,
            'payment_status' => $status,
            'payment_method' => $method['key'],
        ];

        if ($status === 'partial') {
            $updateData['customer_id'] = $customerFields['customer_id'];
            $updateData['customer_name'] = $customerFields['customer_name'];
            $updateData['customer_phone'] = $customerFields['customer_phone'];
            $updateData['due_date'] = $request->due_date;
            if ($this->sale->due_date != $request->due_date) {
                $updateData['debt_due_soon_sms_sent_at'] = null;
                $updateData['debt_due_soon_second_sms_sent_at'] = null;
                $updateData['debt_due_today_sms_sent_at'] = null;
                $updateData['debt_overdue_sms_sent_at'] = null;
            }
        } elseif ($status === 'paid') {
            $updateData['due_date'] = null;
            if ($request->filled('customer_id') || $request->filled('customer_name')) {
                $updateData['customer_id'] = $customerFields['customer_id'];
                $updateData['customer_name'] = $customerFields['customer_name'];
                $updateData['customer_phone'] = $customerFields['customer_phone'];
            }
        }

        $this->sale->update($updateData);

        app(SaleStockService::class)->deductIfPaid($this->sale->fresh());

        if ($status === 'partial') {
            $remaining = (float) $this->sale->total_amount - $newAmountPaid;

            return 'Payment of '.money($amountToPay).' recorded. Balance '.money($remaining).' due '.$request->due_date.'.';
        }

        return 'Payment of '.money($amountToPay).' recorded — invoice fully paid.';
    }

    private function createPayment(Request $request, float $amount, string $methodKey): void
    {
        SalePayment::create([
            'sale_id' => $this->sale->id,
            'user_id' => Auth::id(),
            'amount' => $amount,
            'payment_method' => $methodKey,
            'payment_provider' => $request->payment_provider,
            'transaction_reference' => $request->transaction_reference,
        ]);
    }

    private function resolveCustomerFields(Request $request): array
    {
        $businessId = Auth::user()->business_id;
        $customerId = $request->input('customer_id');

        if ($customerId) {
            $customer = Customer::where('business_id', $businessId)
                ->where('id', $customerId)
                ->where('is_active', true)
                ->first();

            if ($customer) {
                return [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'customer_phone' => $customer->phone,
                ];
            }
        }

        $phone = Customer::normalizePhone($request->customer_phone);

        return [
            'customer_id' => null,
            'customer_name' => $request->customer_name,
            'customer_phone' => $phone ?: $request->customer_phone,
        ];
    }

    public function refreshShiftTotals(): void
    {
        if ($this->sale->shift_id) {
            Shift::find($this->sale->shift_id)?->refreshTotals();
        }
    }
}

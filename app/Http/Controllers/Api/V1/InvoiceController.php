<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Sale;
use App\Services\InvoiceApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvoiceController extends ApiController
{
    public function __construct(private InvoiceApiService $invoices)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['view_invoices', 'view_sales_history', 'process_sales'])) {
            return $deny;
        }

        $user = $request->user();
        $branchFilterId = $this->invoices->branchFilterId($user, $this->tenantContext());

        return $this->success(
            $this->invoices->index(
                $user,
                $this->apiBusinessId(),
                $branchFilterId,
                $request->only(['date', 'payment_status', 'q', 'page', 'per_page'])
            )
        );
    }

    public function createForm(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['create_invoices', 'process_sales'])) {
            return $deny;
        }

        $user = $request->user();
        $business = $this->apiBusiness();
        $branchFilterId = $this->invoices->branchFilterId($user, $this->tenantContext());

        try {
            return $this->success(
                $this->invoices->createForm($user, $business, $branchFilterId)
            );
        } catch (ValidationException $e) {
            $errors = $e->errors();
            if (isset($errors['shift'])) {
                return $this->error($errors['shift'][0] ?? 'Shift required.', 422, ['code' => 'SHIFT_REQUIRED']);
            }

            return $this->error('Validation failed.', 422, $errors);
        }
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['create_invoices', 'process_sales'])) {
            return $deny;
        }

        $user = $request->user();
        $business = $this->apiBusiness();
        $branchFilterId = $this->invoices->branchFilterId($user, $this->tenantContext());

        try {
            $data = $this->invoices->store(
                $user,
                $business,
                $branchFilterId,
                $request->all()
            );

            $message = 'Invoice '.$data['invoice']['reference_no'].' created.';
            if ($data['notifications']['sms_sent'] ?? false) {
                $message .= ' SMS sent to customer.';
            } elseif (($data['notifications']['sms_error'] ?? null) && filled($data['invoice']['customer_phone'] ?? null)) {
                $message .= ' SMS could not be sent: '.$data['notifications']['sms_error'];
            }
            if ($data['notifications']['email_sent'] ?? false) {
                $message .= ' Invoice emailed with PDF attachment.';
            } elseif (($data['notifications']['email_error'] ?? null) && (
                filled($data['invoice']['customer_email'] ?? null) || filled($request->input('customer_email'))
            )) {
                $message .= ' Email could not be sent: '.$data['notifications']['email_error'];
            }

            return $this->success($data, $message, 201);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to create invoice: '.$e->getMessage(), 500);
        }
    }

    public function show(Request $request, Sale $invoice): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['view_invoices', 'view_sales_history', 'process_sales'])) {
            return $deny;
        }

        if ((int) $invoice->business_id !== $this->apiBusinessId()) {
            return $this->forbidden();
        }

        if ($invoice->sale_source !== 'invoice') {
            return $this->error('This sale is not an invoice.', 404);
        }

        if ($invoice->payment_status === 'cancelled') {
            return $this->error('This invoice was cancelled.', 422);
        }

        $user = $request->user();
        if (! $user->seesBusinessWideData() && (int) $invoice->user_id !== (int) $user->id) {
            return $this->forbidden('You can only access your own invoices.');
        }

        return $this->success($this->invoices->show($invoice));
    }
}

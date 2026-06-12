<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessNote;
use App\Models\DayClosing;
use App\Models\Receiving;
use App\Models\ReceivingItem;
use App\Models\Sale;
use App\Models\User;
use App\Models\Item;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BusinessStaffSmsService
{
    public function __construct(private BusinessSmsService $businessSms)
    {
    }

    public function isEnabled(Business $business): bool
    {
        $business->loadMissing('plan');

        if (! (bool) ($business->automationSettings()['sms_staff_enabled'] ?? true)) {
            return false;
        }

        return $business->plan === null || $business->plan->allowsSmsSending();
    }

    public function actionEnabled(Business $business, string $action): bool
    {
        if (! $this->isEnabled($business)) {
            return false;
        }

        $key = 'sms_staff_'.$action;

        return (bool) ($business->automationSettings()[$key] ?? true);
    }

    public function sendStaffWelcome(Business $business, User $sender, User $staff, string $password): bool
    {
        return $this->send(
            $business,
            $sender,
            $staff,
            $this->renderAutomationTemplate($business, 'sms_staff_template_welcome', [
                '{staff_name}' => $staff->name,
                '{email}' => $staff->email,
                '{password}' => $password,
            ]),
            'staff_welcome',
            'welcome',
        );
    }

    public function sendPasswordReset(Business $business, User $sender, User $staff, string $password): bool
    {
        return $this->send(
            $business,
            $sender,
            $staff,
            $this->renderAutomationTemplate($business, 'sms_staff_template_password_reset', [
                '{staff_name}' => $staff->name,
                '{email}' => $staff->email,
                '{password}' => $password,
            ]),
            'staff_password_reset',
            'password_reset',
        );
    }

    public function sendAccountActivated(Business $business, User $sender, User $staff): bool
    {
        return $this->send(
            $business,
            $sender,
            $staff,
            $this->renderAutomationTemplate($business, 'sms_staff_template_activated', [
                '{staff_name}' => $staff->name,
                '{email}' => $staff->email,
            ]),
            'staff_activated',
            'activated',
        );
    }

    public function sendAccountDeactivated(Business $business, User $sender, User $staff): bool
    {
        return $this->send(
            $business,
            $sender,
            $staff,
            $this->renderAutomationTemplate($business, 'sms_staff_template_deactivated', [
                '{staff_name}' => $staff->name,
            ]),
            'staff_deactivated',
            'deactivated',
        );
    }

    public function notifyHandoverSubmitted(Business $business, User $submitter, DayClosing $closing): void
    {
        $closing->loadMissing(['shift']);
        $vars = $this->buildHandoverSubmittedVars($business, $submitter, $closing);
        $notifiedPhones = [];

        $owner = $business->resolveOwner();
        $ownerPhone = filled($owner?->phone) ? $owner->phone : $business->phone;
        $ownerName = $owner?->name ?? $business->contact_person ?? $business->name;

        if (filled($ownerPhone)) {
            $message = $this->renderAutomationTemplate($business, 'sms_staff_template_handover_submitted_owner', array_merge($vars, [
                '{owner}' => $ownerName,
            ]));

            if ($this->sendInternal(
                $business,
                $submitter,
                $ownerPhone,
                $message,
                'handover_submitted_owner',
                'handover_submitted_owner',
                $ownerName,
                $owner?->id,
            )) {
                $notifiedPhones[] = $this->normalizePhoneKey($ownerPhone);
            }
        }

        foreach ($business->resolveManagers() as $manager) {
            if ($manager->id === $submitter->id) {
                continue;
            }

            $phone = $this->resolveUserPhone($business, $manager);
            if (! filled($phone)) {
                continue;
            }

            $phoneKey = $this->normalizePhoneKey($phone);
            if (in_array($phoneKey, $notifiedPhones, true)) {
                continue;
            }

            $message = $this->renderAutomationTemplate($business, 'sms_staff_template_handover_submitted_manager', array_merge($vars, [
                '{manager}' => $manager->name,
            ]));

            $sent = filled($manager->phone)
                ? $this->send($business, $submitter, $manager, $message, 'handover_submitted_manager', 'handover_submitted_manager')
                : $this->sendInternal(
                    $business,
                    $submitter,
                    $phone,
                    $message,
                    'handover_submitted_manager',
                    'handover_submitted_manager',
                    $manager->name,
                    $manager->id,
                );

            if ($sent) {
                $notifiedPhones[] = $phoneKey;
            }
        }
    }

    /** @deprecated Use notifyHandoverSubmitted() */
    public function notifyOwnerHandoverSubmitted(Business $business, User $submitter, DayClosing $closing): bool
    {
        $this->notifyHandoverSubmitted($business, $submitter, $closing);

        return true;
    }

    public function notifyStockReceived(Receiving $receiving): void
    {
        $receiving->loadMissing([
            'business.plan',
            'supplier',
            'user',
            'branch',
            'items.item.receivingPackaging',
        ]);

        $business = $receiving->business;
        $receiver = $receiving->user;

        if (! $business || ! $receiver) {
            return;
        }

        $vars = $this->buildStockReceivedVars($receiving);
        $notifiedPhones = [];

        $owner = $business->resolveOwner();
        $ownerPhone = filled($owner?->phone) ? $owner->phone : $business->phone;

        if (filled($ownerPhone)) {
            $message = $this->renderAutomationTemplate($business, 'sms_staff_template_stock_received_owner', $vars);
            if ($this->sendInternal(
                $business,
                $receiver,
                $ownerPhone,
                $message,
                'stock_received_owner',
                'stock_received_owner',
                $owner?->name ?? $business->contact_person ?? $business->name,
                $owner?->id,
            )) {
                $notifiedPhones[] = $this->normalizePhoneKey($ownerPhone);
            }
        }

        foreach ($business->resolveManagers() as $manager) {
            if ($manager->id === $receiver->id) {
                continue;
            }

            $phone = $this->resolveUserPhone($business, $manager);
            if (! filled($phone)) {
                continue;
            }

            $phoneKey = $this->normalizePhoneKey($phone);
            if (in_array($phoneKey, $notifiedPhones, true)) {
                continue;
            }

            $message = $this->renderAutomationTemplate($business, 'sms_staff_template_stock_received_manager', $vars);

            $sent = filled($manager->phone)
                ? $this->send($business, $receiver, $manager, $message, 'stock_received_manager', 'stock_received_manager')
                : $this->sendInternal(
                    $business,
                    $receiver,
                    $phone,
                    $message,
                    'stock_received_manager',
                    'stock_received_manager',
                    $manager->name,
                    $manager->id,
                );

            if ($sent) {
                $notifiedPhones[] = $phoneKey;
            }
        }
    }

    public function notifyStaffHandoverVerified(Business $business, User $verifier, DayClosing $closing): bool
    {
        $closing->loadMissing('user');
        $staff = $closing->user;

        if (! $staff) {
            return false;
        }

        $dateLabel = $closing->closing_date->format('d M Y');
        $moneyShort = (float) ($closing->money_short ?? 0);
        $moneyShortNote = $moneyShort > 0
            ? ' Note: money short of TZS '.number_format($moneyShort, 0).' recorded.'
            : '';

        return $this->send(
            $business,
            $verifier,
            $staff,
            $this->renderAutomationTemplate($business, 'sms_staff_template_handover_verified_staff', [
                '{staff_name}' => $staff->name,
                '{verifier}' => $verifier->name,
                '{date}' => $dateLabel,
                '{money_short}' => $moneyShort > 0 ? number_format($moneyShort, 0) : '',
                '{money_short_note}' => $moneyShortNote,
            ]),
            'handover_verified_staff',
            'handover_verified_staff',
        );
    }

    public function sendNoteReminder(Business $business, User $recipient, BusinessNote $note): bool
    {
        $phone = $this->resolveUserPhone($business, $recipient);

        if (! filled($phone)) {
            return false;
        }

        $title = $note->displayTitle();
        $preview = Str::limit(trim(strip_tags($note->body)), 100);
        $when = $note->remind_at?->format('d M Y g:i A') ?? 'now';
        $message = $this->renderAutomationTemplate($business, 'sms_staff_template_note_reminder', [
            '{staff_name}' => $recipient->name,
            '{title}' => $title,
            '{when}' => $when,
            '{preview}' => $preview,
        ]);

        if (filled($recipient->phone)) {
            return $this->send(
                $business,
                $recipient,
                $recipient,
                $message,
                'note_reminder',
                'note_reminder',
            );
        }

        return $this->sendInternal(
            $business,
            $recipient,
            $phone,
            $message,
            'note_reminder',
            'note_reminder',
            $recipient->name,
            $recipient->id,
        );
    }

    public function sendDebtDueSoonToCustomer(Business $business, User $sender, Sale $sale, float $balance, Carbon $dueDate, string $wave = 'first'): bool
    {
        $purpose = $wave === 'second' ? 'debt_reminder_due_soon_2' : 'debt_reminder_due_soon';

        return $this->sendDebtToCustomer($business, $sender, $sale, $balance, $dueDate, 'due_soon', $purpose, $wave);
    }

    public function sendDebtDueTodayToCustomer(Business $business, User $sender, Sale $sale, float $balance, Carbon $dueDate): bool
    {
        return $this->sendDebtToCustomer($business, $sender, $sale, $balance, $dueDate, 'due_today', 'debt_reminder_due');
    }

    public function sendDebtOverdueToCustomer(Business $business, User $sender, Sale $sale, float $balance, Carbon $dueDate): bool
    {
        return $this->sendDebtToCustomer($business, $sender, $sale, $balance, $dueDate, 'overdue', 'debt_reminder_overdue');
    }

    public function sendDebtDueSoonToStaff(Business $business, User $sender, Sale $sale, float $balance, Carbon $dueDate, string $wave = 'first'): bool
    {
        $purpose = $wave === 'second' ? 'debt_reminder_due_soon_2' : 'debt_reminder_due_soon';

        return $this->sendDebtToStaff($business, $sender, $sale, $balance, $dueDate, 'due_soon', $purpose, $wave);
    }

    public function sendDebtDueTodayToStaff(Business $business, User $sender, Sale $sale, float $balance, Carbon $dueDate): bool
    {
        return $this->sendDebtToStaff($business, $sender, $sale, $balance, $dueDate, 'due_today', 'debt_reminder_due');
    }

    public function sendDebtOverdueToStaff(Business $business, User $sender, Sale $sale, float $balance, Carbon $dueDate): bool
    {
        return $this->sendDebtToStaff($business, $sender, $sale, $balance, $dueDate, 'overdue', 'debt_reminder_overdue');
    }

    private function sendDebtToCustomer(
        Business $business,
        User $sender,
        Sale $sale,
        float $balance,
        Carbon $dueDate,
        string $phase,
        string $purpose,
        string $wave = 'first',
    ): bool {
        if (! $this->debtActionEnabled($business, $phase, 'customer')) {
            return false;
        }

        $result = $this->businessSms->sendDebtorReminderSms(
            $business,
            $sender,
            $sale,
            $this->debtCustomerMessage($business, $sale, $balance, $dueDate, $phase, $wave),
            $purpose,
        );

        return (bool) ($result['success'] ?? false);
    }

    private function sendDebtToStaff(
        Business $business,
        User $sender,
        Sale $sale,
        float $balance,
        Carbon $dueDate,
        string $phase,
        string $purpose,
        string $wave = 'first',
    ): bool {
        $staff = $sale->user;
        if (! $staff) {
            return false;
        }

        $phone = $this->resolveUserPhone($business, $staff);
        if (! filled($phone)) {
            return false;
        }

        if (! $this->debtActionEnabled($business, $phase, 'staff')) {
            return false;
        }

        $message = $this->debtStaffMessage($business, $sale, $balance, $dueDate, $phase, $wave);

        if (filled($staff->phone)) {
            $result = $this->businessSms->sendSmsToStaff($business, $sender, $staff, $message, $purpose);

            return (bool) ($result['success'] ?? false);
        }

        $result = $this->businessSms->sendInternalSms(
            $business,
            $sender,
            $phone,
            $message,
            $purpose,
            $staff->name,
            $staff->id,
        );

        return (bool) ($result['success'] ?? false);
    }

    private function debtActionEnabled(Business $business, string $phase, string $recipient): bool
    {
        if (! (bool) ($business->automationSettings()['sms_debt_enabled'] ?? true)) {
            return false;
        }

        $business->loadMissing('plan');

        if (! $business->plan || ! $business->plan->allowsSmsSending()) {
            return false;
        }

        $key = 'sms_debt_'.$phase.'_'.$recipient;

        return (bool) ($business->automationSettings()[$key] ?? true);
    }

    public function buildAutomationMessage(Business $business, string $templateKey, array $replacements): string
    {
        return $this->renderAutomationTemplate($business, $templateKey, $replacements);
    }

    public function buildDebtCustomerMessage(Business $business, Sale $sale, float $balance, Carbon $dueDate, string $phase, string $wave = 'first'): string
    {
        return $this->debtCustomerMessage($business, $sale, $balance, $dueDate, $phase, $wave);
    }

    public function buildDebtStaffMessage(Business $business, Sale $sale, float $balance, Carbon $dueDate, string $phase, string $wave = 'first'): string
    {
        return $this->debtStaffMessage($business, $sale, $balance, $dueDate, $phase, $wave);
    }

    private function debtCustomerMessage(Business $business, Sale $sale, float $balance, Carbon $dueDate, string $phase, string $wave = 'first'): string
    {
        return $this->renderDebtMessage($business, $sale, $balance, $dueDate, 'customer', $phase, $wave);
    }

    private function debtStaffMessage(Business $business, Sale $sale, float $balance, Carbon $dueDate, string $phase, string $wave = 'first'): string
    {
        return $this->renderDebtMessage($business, $sale, $balance, $dueDate, 'staff', $phase, $wave);
    }

    private function renderDebtMessage(
        Business $business,
        Sale $sale,
        float $balance,
        Carbon $dueDate,
        string $recipient,
        string $phase,
        string $wave = 'first',
    ): string {
        $daysBefore = max(0, (int) now()->startOfDay()->diffInDays($dueDate->copy()->startOfDay(), false));

        return $this->renderAutomationTemplate($business, $this->debtTemplateKey($recipient, $phase, $wave), [
            '{customer}' => $sale->customer_name ?: 'Customer',
            '{amount}' => number_format($balance, 0),
            '{reference}' => $sale->reference_no ?: ('#'.$sale->id),
            '{due_date}' => $dueDate->format('d M Y'),
            '{days_before}' => (string) $daysBefore,
        ]);
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function renderAutomationTemplate(Business $business, string $key, array $replacements): string
    {
        $settings = $business->automationSettings();
        $defaults = Business::defaultSmsTemplates();
        $template = trim((string) ($settings[$key] ?? $defaults[$key] ?? ''));

        if ($template === '') {
            $template = $defaults[$key] ?? '';
        }

        $all = array_merge(['{business}' => $business->name], $replacements);

        return str_replace(array_keys($all), array_values($all), $template);
    }

    private function debtTemplateKey(string $recipient, string $phase, string $wave): string
    {
        if ($phase === 'due_soon' && $wave === 'second') {
            return 'sms_debt_template_due_soon_2_'.$recipient;
        }

        return 'sms_debt_template_'.$phase.'_'.$recipient;
    }

    private function resolveUserPhone(Business $business, User $user): ?string
    {
        if (filled($user->phone)) {
            return $user->phone;
        }

        if ($user->role === 'owner' && filled($business->phone)) {
            return $business->phone;
        }

        return null;
    }

    private function sendInternal(
        Business $business,
        User $sender,
        string $phone,
        string $message,
        string $purpose,
        string $action,
        ?string $recipientName = null,
        ?int $recipientUserId = null,
    ): bool {
        if (! filled($phone)) {
            return false;
        }

        if (! $this->actionEnabled($business, $action)) {
            return false;
        }

        $result = $this->businessSms->sendInternalSms(
            $business,
            $sender,
            $phone,
            $message,
            $purpose,
            $recipientName,
            $recipientUserId,
        );

        return (bool) ($result['success'] ?? false);
    }

    /**
     * @return array<string, string>
     */
    /**
     * @return array<string, string>
     */
    public function buildHandoverSubmittedVars(Business $business, User $submitter, DayClosing $closing): array
    {
        $closing->loadMissing(['business', 'shift']);
        $finance = app(OwnerDailyReportService::class)->buildShiftHandoverReviewData($closing);

        $dateLabel = $closing->closing_date->format('d M Y');
        $salesCount = (int) ($closing->sales_count ?? 0);
        $grossSales = number_format((float) ($closing->gross_sales ?? 0), 0);
        $netAmount = number_format((float) ($closing->net_amount ?? 0), 0);
        $profit = number_format((float) ($finance['net_profit'] ?? 0), 0);
        $circulationReturn = number_format(
            (float) ($finance['circulation_refill'] ?? $finance['closing_circulation'] ?? 0),
            0
        );
        $cashReceived = number_format((float) ($closing->cash_received ?? 0), 0);
        $mobileReceived = number_format((float) ($closing->mobile_received ?? 0), 0);
        $bankReceived = number_format((float) ($closing->bank_received ?? 0), 0);
        $expenses = number_format((float) ($closing->total_expenses ?? 0), 0);
        $cancelledSales = (int) ($closing->cancelled_sales ?? 0);

        $paymentParts = [];
        if ((float) ($closing->cash_received ?? 0) > 0) {
            $paymentParts[] = 'Cash TZS '.$cashReceived;
        }
        if ((float) ($closing->mobile_received ?? 0) > 0) {
            $paymentParts[] = 'Mobile TZS '.$mobileReceived;
        }
        if ((float) ($closing->bank_received ?? 0) > 0) {
            $paymentParts[] = 'Bank TZS '.$bankReceived;
        }

        $paymentSummary = $paymentParts !== []
            ? implode(', ', $paymentParts)
            : '';

        $expenseNote = (float) ($closing->total_expenses ?? 0) > 0
            ? ' Expenses TZS '.$expenses.'.'
            : '';

        $cancelledNote = $cancelledSales > 0
            ? ' '.$cancelledSales.' cancelled.'
            : '';

        $paymentSuffix = $paymentSummary !== ''
            ? ' '.$paymentSummary.'.'
            : '';

        return [
            '{submitter}' => $submitter->name,
            '{date}' => $dateLabel,
            '{sales_count}' => (string) $salesCount,
            '{gross_sales}' => $grossSales,
            '{net_amount}' => $netAmount,
            '{amount}' => $netAmount,
            '{profit}' => $profit,
            '{circulation_return}' => $circulationReturn,
            '{cash_received}' => $cashReceived,
            '{mobile_received}' => $mobileReceived,
            '{bank_received}' => $bankReceived,
            '{expenses}' => $expenses,
            '{payment_summary}' => $paymentSummary,
            '{payment_suffix}' => $paymentSuffix,
            '{expense_note}' => $expenseNote,
            '{cancelled_sales}' => (string) $cancelledSales,
            '{cancelled_note}' => $cancelledNote,
        ];
    }

    private function buildStockReceivedVars(Receiving $receiving): array
    {
        $business = $receiving->business;
        $receiver = $receiving->user;
        $itemSummaries = [];
        $totalPieces = 0;

        foreach ($receiving->items as $line) {
            $item = $line->item;
            if (! $item) {
                continue;
            }

            $label = $line->receivedQuantityLabel($item);
            $itemSummaries[] = $item->name.' '.$label;
            $totalPieces += (int) $line->receivedPieces($item);
        }

        $itemCount = count($itemSummaries);
        $itemsSummary = $itemCount <= 3
            ? implode(', ', $itemSummaries)
            : implode(', ', array_slice($itemSummaries, 0, 2)).' +'.($itemCount - 2).' more';

        $branchSuffix = filled($receiving->branch?->name)
            ? ' · '.$receiving->branch->name
            : '';

        return [
            '{receiver}' => $receiver?->name ?? 'Staff',
            '{reference}' => $receiving->reference_no,
            '{supplier}' => $receiving->supplier?->name ?? 'Supplier',
            '{date}' => \Carbon\Carbon::parse($receiving->received_date)->format('d M Y'),
            '{item_count}' => (string) $itemCount,
            '{total_pieces}' => number_format($totalPieces, 0),
            '{total_cost}' => number_format((float) ($receiving->total_amount ?? 0), 0),
            '{items_summary}' => $itemsSummary,
            '{branch}' => $receiving->branch?->name ?? '',
            '{branch_suffix}' => $branchSuffix,
        ];
    }

    private function normalizePhoneKey(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? $phone;
    }

    private function send(
        Business $business,
        User $sender,
        User $staff,
        string $message,
        string $purpose,
        string $action,
    ): bool {
        if (! filled($staff->phone)) {
            return false;
        }

        if (! $this->actionEnabled($business, $action)) {
            return false;
        }

        $result = $this->businessSms->sendSmsToStaff($business, $sender, $staff, $message, $purpose);

        return (bool) ($result['success'] ?? false);
    }
}

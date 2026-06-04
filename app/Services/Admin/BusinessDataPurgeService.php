<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessNote;
use App\Models\BusinessOnboarding;
use App\Models\BusinessOwnerExpense;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerCommunicationCampaign;
use App\Models\CustomerSmsLog;
use App\Models\DayClosing;
use App\Models\DayClosingExpense;
use App\Models\Item;
use App\Models\ItemPackaging;
use App\Models\MoneyShortSettlement;
use App\Models\OwnerDailyReport;
use App\Models\Packaging;
use App\Models\PlatformBillingInvoice;
use App\Models\Receiving;
use App\Models\ReceivingItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SalesTarget;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Shift;
use App\Models\ShiftStockCheck;
use App\Models\StockLoss;
use App\Models\StockLossItem;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BusinessDataPurgeService
{
    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function scopes(): array
    {
        return [
            'sales' => [
                'label' => 'Sales & shifts',
                'description' => 'POS orders, service sales, invoices, payments, and shift records.',
            ],
            'inventory' => [
                'label' => 'Stock activity',
                'description' => 'Receivings and stock loss records (not the item catalog itself).',
            ],
            'catalog' => [
                'label' => 'Catalog (items & services)',
                'description' => 'Categories, items, packaging, services, and suppliers. Stock counts are removed with items.',
            ],
            'customers' => [
                'label' => 'Customers & messaging',
                'description' => 'Customer list, SMS/email logs, and communication campaigns.',
            ],
            'operations' => [
                'label' => 'Daily operations',
                'description' => 'Day closing, owner reports, notes, sales targets, and money-short records.',
            ],
            'support' => [
                'label' => 'Support tickets',
                'description' => 'Support tickets raised by this business.',
            ],
            'platform_billing' => [
                'label' => 'Platform subscription invoices',
                'description' => 'Monthly Mauzo Link billing invoices for this business (platform records only).',
            ],
            'audit_logs' => [
                'label' => 'Activity audit trail',
                'description' => 'Business-scoped audit log entries.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allScopeKeys(): array
    {
        return array_keys(self::scopes());
    }

    public static function totalPreviewCount(array $counts): int
    {
        return (int) collect($counts)
            ->except(['_sale_payments', '_shifts', '_receiving_lines', '_stock_loss_lines', '_item_packagings', '_day_closing_expenses'])
            ->sum();
    }

    /**
     * @param  list<string>  $scopes
     * @return list<string>
     */
    public static function resolveScopes(array $scopes, bool $purgeAll = false): array
    {
        if ($purgeAll || in_array('all', $scopes, true)) {
            return self::allScopeKeys();
        }

        return array_values(array_intersect($scopes, self::allScopeKeys()));
    }

    /**
     * @return array<string, int>
     */
    public function previewCounts(Business $business): array
    {
        $id = (int) $business->id;
        $saleIds = Sale::where('business_id', $id)->pluck('id');
        $shiftIds = Shift::where('business_id', $id)->pluck('id');
        $receivingIds = Receiving::where('business_id', $id)->pluck('id');
        $stockLossIds = StockLoss::where('business_id', $id)->pluck('id');
        $itemIds = Item::where('business_id', $id)->pluck('id');
        $dayClosingIds = DayClosing::where('business_id', $id)->pluck('id');

        return [
            'sales' => $saleIds->count(),
            'inventory' => Receiving::where('business_id', $id)->count() + StockLoss::where('business_id', $id)->count(),
            'catalog' => Category::where('business_id', $id)->count()
                + Item::where('business_id', $id)->count()
                + Packaging::where('business_id', $id)->count()
                + Service::where('business_id', $id)->count()
                + ServiceCategory::where('business_id', $id)->count()
                + Supplier::where('business_id', $id)->count(),
            'customers' => Customer::where('business_id', $id)->count()
                + CustomerSmsLog::where('business_id', $id)->count()
                + CustomerCommunicationCampaign::where('business_id', $id)->count(),
            'operations' => DayClosing::where('business_id', $id)->count()
                + OwnerDailyReport::where('business_id', $id)->count()
                + BusinessNote::where('business_id', $id)->count()
                + SalesTarget::where('business_id', $id)->count()
                + MoneyShortSettlement::where('business_id', $id)->count()
                + BusinessOwnerExpense::where('business_id', $id)->count(),
            'support' => Ticket::where('business_id', $id)->count(),
            'platform_billing' => PlatformBillingInvoice::where('business_id', $id)->count(),
            'audit_logs' => AuditLog::where('business_id', $id)->count(),
            '_sale_payments' => SalePayment::whereIn('sale_id', $saleIds)->count(),
            '_shifts' => $shiftIds->count(),
            '_receiving_lines' => ReceivingItem::whereIn('receiving_id', $receivingIds)->count(),
            '_stock_loss_lines' => StockLossItem::whereIn('stock_loss_id', $stockLossIds)->count(),
            '_item_packagings' => ItemPackaging::whereIn('item_id', $itemIds)->count(),
            '_day_closing_expenses' => DayClosingExpense::whereIn('day_closing_id', $dayClosingIds)->count(),
        ];
    }

    /**
     * @param  list<string>  $scopes
     * @return array<string, int>
     */
    public function purge(Business $business, array $scopes, User $actor, bool $purgeAll = false): array
    {
        $scopes = self::resolveScopes($scopes, $purgeAll);
        if ($scopes === []) {
            throw new \InvalidArgumentException('Select at least one data area to clear.');
        }

        $id = (int) $business->id;
        $deleted = [];

        DB::transaction(function () use ($business, $scopes, $id, &$deleted) {
            if (in_array('sales', $scopes, true)) {
                $deleted['sales'] = $this->purgeSales($id);
            }

            if (in_array('inventory', $scopes, true)) {
                $deleted['inventory'] = $this->purgeInventory($id);
            }

            if (in_array('catalog', $scopes, true)) {
                $deleted['catalog'] = $this->purgeCatalog($business, $id);
            }

            if (in_array('customers', $scopes, true)) {
                $deleted['customers'] = $this->purgeCustomers($id);
            }

            if (in_array('operations', $scopes, true)) {
                $deleted['operations'] = $this->purgeOperations($id);
            }

            if (in_array('support', $scopes, true)) {
                $deleted['support'] = Ticket::where('business_id', $id)->delete();
            }

            if (in_array('platform_billing', $scopes, true)) {
                $deleted['platform_billing'] = PlatformBillingInvoice::where('business_id', $id)->delete();
            }

            if (in_array('audit_logs', $scopes, true)) {
                $deleted['audit_logs'] = AuditLog::where('business_id', $id)->delete();
            }
        });

        $scopeLabels = collect($scopes)->map(fn ($s) => self::scopes()[$s]['label'] ?? $s)->implode(', ');
        $summary = $purgeAll ? "Platform admin cleared ALL operational data for {$business->name}" : "Platform admin cleared data for {$business->name}: {$scopeLabels}";
        AuditLog::create([
            'user_id' => $actor->id,
            'business_id' => $id,
            'action' => $purgeAll ? 'business_data_purge_all' : 'business_data_purge',
            'description' => $summary,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $deleted;
    }

    private function purgeSales(int $businessId): int
    {
        $count = 0;
        $saleIds = Sale::where('business_id', $businessId)->pluck('id');
        $count += SalePayment::whereIn('sale_id', $saleIds)->delete();
        $count += SaleItem::whereIn('sale_id', $saleIds)->delete();
        $count += Sale::where('business_id', $businessId)->delete();

        $shiftIds = Shift::where('business_id', $businessId)->pluck('id');
        $count += ShiftStockCheck::whereIn('shift_id', $shiftIds)->delete();
        $count += Shift::where('business_id', $businessId)->delete();

        return $count;
    }

    private function purgeInventory(int $businessId): int
    {
        $count = 0;
        $receivingIds = Receiving::where('business_id', $businessId)->pluck('id');
        $count += ReceivingItem::whereIn('receiving_id', $receivingIds)->delete();
        $count += Receiving::where('business_id', $businessId)->delete();

        $stockLossIds = StockLoss::where('business_id', $businessId)->pluck('id');
        $count += StockLossItem::whereIn('stock_loss_id', $stockLossIds)->delete();
        $count += StockLoss::where('business_id', $businessId)->delete();

        return $count;
    }

    private function purgeCatalog(Business $business, int $businessId): int
    {
        $count = 0;
        $itemIds = Item::where('business_id', $businessId)->pluck('id');
        $count += ItemPackaging::whereIn('item_id', $itemIds)->delete();
        $count += Item::where('business_id', $businessId)->delete();
        $count += Category::where('business_id', $businessId)->delete();
        $count += Packaging::where('business_id', $businessId)->delete();
        $count += Service::where('business_id', $businessId)->delete();
        $count += ServiceCategory::where('business_id', $businessId)->delete();
        $count += Supplier::where('business_id', $businessId)->delete();

        $business->update([
            'category_business_types' => null,
            'service_business_types' => null,
        ]);

        return $count;
    }

    private function purgeCustomers(int $businessId): int
    {
        $count = CustomerSmsLog::where('business_id', $businessId)->delete();
        $count += CustomerCommunicationCampaign::where('business_id', $businessId)->delete();
        $count += Customer::where('business_id', $businessId)->delete();

        return $count;
    }

    private function purgeOperations(int $businessId): int
    {
        $count = 0;
        $dayClosingIds = DayClosing::where('business_id', $businessId)->pluck('id');
        $count += DayClosingExpense::whereIn('day_closing_id', $dayClosingIds)->delete();
        $count += DayClosing::where('business_id', $businessId)->delete();
        $count += BusinessOwnerExpense::where('business_id', $businessId)->delete();
        $count += OwnerDailyReport::where('business_id', $businessId)->delete();
        $count += BusinessNote::where('business_id', $businessId)->delete();
        $count += SalesTarget::where('business_id', $businessId)->delete();
        $count += MoneyShortSettlement::where('business_id', $businessId)->delete();
        $count += BusinessOnboarding::where('business_id', $businessId)->delete();

        return $count;
    }
}

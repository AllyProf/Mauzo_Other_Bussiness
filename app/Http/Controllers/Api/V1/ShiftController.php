<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Item;
use App\Models\Shift;
use App\Models\ShiftStockCheck;
use App\Models\User;
use App\Services\ItemStockDisplayService;
use App\Services\ShiftPolicyService;
use App\Services\StockShortageImpactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftController extends ApiController
{
  public function current(Request $request): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['open_shift', 'process_sales', 'collect_payments', 'view_all_shifts'])) {
      return $deny;
    }

    $user = $request->user();
    $businessId = $this->apiBusinessId();
    $shift = Shift::openForUser($user->id, $businessId);
    $shiftMode = $user->isPaymentCashier() ? 'cashier' : 'sales';

    if (! $shift && $user->can('view_all_shifts')) {
      $shift = Shift::query()
        ->where('business_id', $businessId)
        ->where('status', 'open')
        ->latest('opened_at')
        ->first();
    }

    $openCheck = app(ShiftPolicyService::class)->canOpenShift($this->apiBusiness());

    return $this->success([
      'shift' => $shift ? $this->shiftPayload($shift) : null,
      'needs_shift_opened' => $user->needsShiftOpened(),
      'requires_open_shift' => $user->requiresOpenShift(),
      'can_open' => (bool) ($openCheck['allowed'] ?? false),
      'can_open_message' => $openCheck['message'] ?? '',
      'shift_mode' => $shiftMode,
      'stock_check_required' => $shiftMode === 'sales',
      'landing' => $shiftMode === 'cashier' ? 'cashier.queue' : 'pos',
    ]);
  }

  /**
   * Same as web /shifts/open: physical stock check items (counted in pieces) before selling.
   */
  public function openForm(Request $request, ShiftPolicyService $shiftPolicy): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['open_shift', 'process_sales', 'collect_payments'])) {
      return $deny;
    }

    $user = $request->user();
    $businessId = $this->apiBusinessId();

    if ($openShift = Shift::openForUser($user->id, $businessId)) {
      return $this->error('You already have an open shift.', 422, [
        'code' => 'SHIFT_ALREADY_OPEN',
        'shift' => $this->shiftPayload($openShift),
      ]);
    }

    $openCheck = $shiftPolicy->canOpenShift($this->apiBusiness());
    if (! $openCheck['allowed']) {
      return $this->error($openCheck['message'] ?: 'Cannot open shift now.', 422, ['code' => 'SHIFT_OPEN_NOT_ALLOWED']);
    }

    if ($user->isPaymentCashier()) {
      return $this->success([
        'shift_mode' => 'cashier',
        'stock_check_required' => false,
        'items' => [],
        'items_count' => 0,
        'has_bulk_items' => false,
        'scope' => [
          'branch_name' => $user->branch?->name,
          'business_label' => $user->displayBusinessTypeLabels(),
        ],
        'my_stock_shortages' => [],
        'my_shortage_stats' => null,
        'rules' => [
          'count_unit' => null,
          'reason_required_when_below_system' => false,
          'fields' => ['opening_notes'],
        ],
      ]);
    }

    $itemsQuery = Item::query()
      ->where('business_id', $businessId)
      ->where('current_stock', '>', 0)
      ->with(['category', 'packagings.packagingType', 'receivingPackaging']);
    $this->scopeItemsForStaffShift($itemsQuery, $user);

    $stockDisplay = app(ItemStockDisplayService::class);
    $items = $itemsQuery->orderBy('name')->get()->map(function (Item $item) use ($stockDisplay) {
      $info = $stockDisplay->format($item);
      $pieces = (float) ($info['pieces'] ?? $item->current_stock);
      $hasBulk = (bool) ($info['has_bulk_stock'] ?? false);

      return [
        'id' => $item->id,
        'name' => $item->name,
        'sku' => $item->sku,
        'brand' => $item->brand,
        'category_id' => $item->category_id,
        'category' => $item->category?->name,
        'system_stock' => $pieces,
        'stock_display' => $info['stock_display'] ?? (string) $pieces,
        'unit' => $info['unit_name'] ?? 'Unit',
        'has_bulk_stock' => $hasBulk,
        'pack_size' => $hasBulk ? (int) ($info['pack_size'] ?? 0) : null,
        'bulk_name' => $hasBulk ? ($info['bulk_name'] ?? null) : null,
        'count_step' => $hasBulk ? 1 : 0.01,
        'default_count' => $pieces,
      ];
    })->values();

    $shortageService = app(StockShortageImpactService::class);
    $myShortages = $shortageService->staffShortagesForUser($user);

    return $this->success([
      'shift_mode' => 'sales',
      'stock_check_required' => true,
      'items' => $items,
      'items_count' => $items->count(),
      'has_bulk_items' => $items->contains('has_bulk_stock', true),
      'scope' => [
        'branch_name' => $user->branch?->name,
        'business_label' => $user->displayBusinessTypeLabels(),
      ],
      'my_stock_shortages' => $myShortages->map(fn (ShiftStockCheck $check) => [
        'id' => $check->id,
        'item' => $check->item?->name,
        'category' => $check->item?->category?->name,
        'shift_id' => $check->shift_id,
        'shortage_qty' => abs((float) $check->variance),
        'cost_value' => (float) ($check->financial_impact['cost_value'] ?? 0),
        'notes' => $check->notes,
        'owner_decision' => $check->owner_decision,
        'is_verified' => $check->isVerified(),
        'recorded_at' => $check->recorded_at?->toIso8601String(),
      ])->values(),
      'my_shortage_stats' => $shortageService->staffShortageStats($myShortages),
      'rules' => [
        'count_unit' => 'pcs',
        'reason_required_when_below_system' => true,
      ],
    ]);
  }

  public function open(Request $request, ShiftPolicyService $shiftPolicy): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['open_shift', 'process_sales', 'collect_payments'])) {
      return $deny;
    }

    $user = $request->user();
    $businessId = $this->apiBusinessId();
    $business = $this->apiBusiness();

    if ($openShift = Shift::openForUser($user->id, $businessId)) {
      return $this->error('You already have an open shift.', 422, [
        'code' => 'SHIFT_ALREADY_OPEN',
        'shift' => $this->shiftPayload($openShift),
      ]);
    }

    $openCheck = $shiftPolicy->canOpenShift($business);
    if (! $openCheck['allowed']) {
      return $this->error($openCheck['message'] ?: 'Cannot open shift now.', 422, ['code' => 'SHIFT_OPEN_NOT_ALLOWED']);
    }

    if ($user->isPaymentCashier()) {
      $request->validate(['opening_notes' => 'nullable|string|max:2000']);

      $shift = Shift::create([
        'business_id' => $businessId,
        'user_id' => $user->id,
        'opened_at' => now(),
        'status' => 'open',
        'opening_notes' => $request->opening_notes,
        'opening_variance_count' => 0,
      ]);

      return $this->success([
        'shift' => $this->shiftPayload($shift->fresh()),
        'variance_count' => 0,
        'next' => 'cashier.queue',
      ], 'Cashier shift opened. You can now collect payments.', 201);
    }

    $request->validate([
      'opening_notes' => 'nullable|string|max:2000',
      'counts' => 'required|array|min:1',
      'counts.*' => 'nullable|numeric|min:0',
      'notes' => 'nullable|array',
      'notes.*' => 'nullable|string|max:500',
    ]);

    $itemsQuery = Item::query()
      ->where('business_id', $businessId)
      ->where('current_stock', '>', 0);
    $this->scopeItemsForStaffShift($itemsQuery, $user);
    $items = $itemsQuery->get()->keyBy('id');

    foreach (array_keys($request->counts ?? []) as $submittedItemId) {
      if (! $items->has((int) $submittedItemId)) {
        return $this->forbidden('One or more items are outside your assigned scope.');
      }
    }

    if ($items->isEmpty()) {
      return $this->error('No items with stock to count. Receive stock or add items first.', 422, ['code' => 'NO_ITEMS']);
    }

    foreach ($items as $item) {
      $counted = $request->counts[$item->id] ?? null;
      if ($counted === null || $counted === '') {
        return $this->error("Physical count is required for {$item->name}.", 422, [
          'code' => 'COUNT_REQUIRED',
          'item_id' => $item->id,
        ]);
      }

      $system = (float) $item->current_stock;
      $countedStock = (float) $counted;
      if ($countedStock < $system - 0.0001) {
        $note = trim((string) ($request->notes[$item->id] ?? ''));
        if ($note === '') {
          return $this->error("Reason is required for {$item->name} — physical count is lower than system stock.", 422, [
            'code' => 'REASON_REQUIRED',
            'item_id' => $item->id,
          ]);
        }
      }
    }

    DB::beginTransaction();
    try {
      $shift = Shift::create([
        'business_id' => $businessId,
        'user_id' => $user->id,
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
          'recorded_by' => $user->id,
          'recorded_at' => $now,
        ]);

        if (abs($variance) > 0.0001) {
          $item->update(['current_stock' => $countedStock]);
        }
      }

      $shift->update(['opening_variance_count' => $varianceCount]);
      DB::commit();

      return $this->success([
        'shift' => $this->shiftPayload($shift->fresh()),
        'variance_count' => $varianceCount,
        'next' => 'pos',
      ], 'Shift opened. Physical stock check saved — you can now sell on POS.', 201);
    } catch (\Throwable $e) {
      DB::rollBack();

      return $this->error('Failed to open shift: '.$e->getMessage(), 500);
    }
  }

  public function close(Request $request, Shift $shift): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['open_shift', 'process_sales'])) {
      return $deny;
    }

    if ($shift->business_id != $this->apiBusinessId()) {
      return $this->forbidden();
    }

    if (! $shift->isOpen()) {
      return $this->error('Shift is already closed.', 422);
    }

    if ((int) $shift->user_id !== (int) $request->user()->id && ! $request->user()->seesBusinessWideData()) {
      return $this->forbidden('Only the shift owner can close this shift.');
    }

    $request->validate([
      'closing_notes' => 'nullable|string|max:2000',
    ]);

    $shift->refreshTotals();
    $shift->update([
      'status' => 'closed',
      'closed_at' => now(),
      'closing_notes' => $request->closing_notes,
    ]);

    return $this->success(['shift' => $this->shiftPayload($shift->fresh())], 'Shift closed. Submit day handover from the web app or a future mobile update.');
  }

  public function index(Request $request): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['open_shift', 'process_sales', 'view_all_shifts'])) {
      return $deny;
    }

    $user = $request->user();
    $businessId = $this->apiBusinessId();

    $query = Shift::query()
      ->where('business_id', $businessId)
      ->with('user:id,name')
      ->latest('opened_at');

    if (! $user->seesBusinessWideData() && ! $user->can('view_all_shifts')) {
      $query->where('user_id', $user->id);
    }

    $shifts = $query->paginate(min(50, (int) $request->get('per_page', 20)));

    return $this->success([
      'shifts' => collect($shifts->items())->map(fn (Shift $s) => $this->shiftPayload($s))->values(),
      'meta' => [
        'current_page' => $shifts->currentPage(),
        'last_page' => $shifts->lastPage(),
        'per_page' => $shifts->perPage(),
        'total' => $shifts->total(),
      ],
    ]);
  }

  public function show(Request $request, Shift $shift): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['open_shift', 'process_sales', 'view_all_shifts'])) {
      return $deny;
    }

    if ($shift->business_id != $this->apiBusinessId()) {
      return $this->forbidden();
    }

    $user = $request->user();
    if (! $user->seesBusinessWideData() && ! $user->can('view_all_shifts') && (int) $shift->user_id !== (int) $user->id) {
      return $this->forbidden();
    }

    $shift->load(['user:id,name', 'openingChecks.item:id,name']);
    $shift->refreshTotals();

    return $this->success(['shift' => $this->shiftPayload($shift, true)]);
  }

  /**
   * @return array<string, mixed>
   */
  private function shiftPayload(Shift $shift, bool $detailed = false): array
  {
    $payload = [
      'id' => $shift->id,
      'status' => $shift->status,
      'opened_at' => $shift->opened_at?->toIso8601String(),
      'closed_at' => $shift->closed_at?->toIso8601String(),
      'sales_count' => (int) $shift->sales_count,
      'gross_sales' => (float) $shift->gross_sales,
      'amount_collected' => (float) $shift->amount_collected,
      'opening_variance_count' => (int) $shift->opening_variance_count,
      'user' => $shift->relationLoaded('user') ? ['id' => $shift->user?->id, 'name' => $shift->user?->name] : null,
    ];

    if ($detailed) {
      $payload['opening_notes'] = $shift->opening_notes;
      $payload['closing_notes'] = $shift->closing_notes;
      $payload['opening_checks'] = $shift->openingChecks?->map(fn ($c) => [
        'item' => $c->item?->name,
        'system_stock' => (float) $c->system_stock,
        'counted_stock' => (float) $c->counted_stock,
        'variance' => (float) $c->variance,
      ])->values();
    }

    return $payload;
  }
}

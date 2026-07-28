<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Item;
use App\Models\Shift;
use App\Models\ShiftStockCheck;
use App\Models\User;
use App\Services\ItemStockDisplayService;
use App\Services\ShiftPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftController extends ApiController
{
  public function current(Request $request): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['open_shift', 'process_sales', 'view_all_shifts'])) {
      return $deny;
    }

    $user = $request->user();
    $businessId = $this->apiBusinessId();
    $shift = Shift::openForUser($user->id, $businessId);

    if (! $shift && $user->can('view_all_shifts')) {
      $shift = Shift::query()
        ->where('business_id', $businessId)
        ->where('status', 'open')
        ->latest('opened_at')
        ->first();
    }

    return $this->success([
      'shift' => $shift ? $this->shiftPayload($shift) : null,
      'needs_shift_opened' => $user->needsShiftOpened(),
      'can_open' => app(ShiftPolicyService::class)->canOpenShift($this->apiBusiness())['allowed'] ?? false,
    ]);
  }

  public function openForm(Request $request): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['open_shift', 'process_sales'])) {
      return $deny;
    }

    $user = $request->user();
    $businessId = $this->apiBusinessId();

    if (Shift::openForUser($user->id, $businessId)) {
      return $this->error('You already have an open shift.', 422);
    }

    $itemsQuery = Item::query()
      ->where('business_id', $businessId)
      ->where('current_stock', '>', 0)
      ->with(['category', 'packagings.packagingType', 'receivingPackaging']);
    $this->scopeItemsForStaffShift($itemsQuery, $user);

    $stockDisplay = app(ItemStockDisplayService::class);
    $items = $itemsQuery->orderBy('name')->get()->map(function (Item $item) use ($stockDisplay) {
      $info = $stockDisplay->format($item);

      return [
        'id' => $item->id,
        'name' => $item->name,
        'sku' => $item->sku,
        'category' => $item->category?->name,
        'system_stock' => (float) $item->current_stock,
        'stock_display' => $info['stock_display'] ?? (string) $item->current_stock,
        'unit' => $info['unit_name'] ?? 'Unit',
      ];
    })->values();

    return $this->success(['items' => $items]);
  }

  public function open(Request $request, ShiftPolicyService $shiftPolicy): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['open_shift', 'process_sales'])) {
      return $deny;
    }

    $user = $request->user();
    $businessId = $this->apiBusinessId();
    $business = $this->apiBusiness();

    if (Shift::openForUser($user->id, $businessId)) {
      return $this->error('You already have an open shift.', 422);
    }

    $openCheck = $shiftPolicy->canOpenShift($business);
    if (! $openCheck['allowed']) {
      return $this->error($openCheck['message'] ?? 'Cannot open shift now.', 422);
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
      return $this->error('No items with stock to count.', 422);
    }

    foreach ($items as $item) {
      $counted = $request->counts[$item->id] ?? null;
      if ($counted === null || $counted === '') {
        return $this->error("Physical count is required for {$item->name}.", 422);
      }

      $system = (float) $item->current_stock;
      $countedStock = (float) $counted;
      if ($countedStock < $system - 0.0001) {
        $note = trim((string) ($request->notes[$item->id] ?? ''));
        if ($note === '') {
          return $this->error("Reason is required for {$item->name} — count is lower than system stock.", 422);
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

      return $this->success(['shift' => $this->shiftPayload($shift->fresh())], 'Shift opened', 201);
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

    if ($shift->user_id !== $request->user()->id && ! $request->user()->seesBusinessWideData()) {
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
    if (! $user->seesBusinessWideData() && ! $user->can('view_all_shifts') && $shift->user_id !== $user->id) {
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

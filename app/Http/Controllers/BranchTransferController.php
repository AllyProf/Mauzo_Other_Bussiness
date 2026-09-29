<?php

namespace App\Http\Controllers;

use App\Models\BranchTransfer;
use App\Services\BranchTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class BranchTransferController extends Controller
{
    public function __construct(private BranchTransferService $transfers)
    {
    }

    public function index()
    {
        $this->authorizeAny(['supply_to_branch', 'receive_branch_supply']);

        $user = Auth::user();
        $businessId = (int) $user->business_id;
        $seesAllHistory = $this->seesAllTransferHistory();

        if (! $this->transfers->businessHasBranches($businessId)
            && ! BranchTransfer::where('business_id', $businessId)->exists()) {
            return redirect($user->defaultLandingUrl())
                ->with('error', __('branch_transfers.need_other_branch'));
        }

        $query = BranchTransfer::query()
            ->where('business_id', $businessId)
            ->with(['fromBranch', 'toBranch', 'user'])
            ->latest();

        // Owner sees full history; staff see supplies they sent OR supplies to their branch.
        if (! $seesAllHistory) {
            $this->scopeTransfersForStaff($query, $user);
        }

        $transfers = $query->paginate(20);

        // Incoming pending stays branch-scoped so destination staff can receive supplies
        // sent by someone else (e.g. owner).
        $pendingQuery = BranchTransfer::query()
            ->where('business_id', $businessId)
            ->where('status', 'pending');
        if (! $seesAllHistory && $user->branch_id) {
            $pendingQuery->where('to_branch_id', (int) $user->branch_id);
        } elseif (! $seesAllHistory) {
            $pendingQuery->whereRaw('1 = 0');
        }
        $pendingIncoming = (clone $pendingQuery)
            ->with(['fromBranch', 'toBranch', 'user'])
            ->latest()
            ->get();

        $statsQuery = BranchTransfer::query()
            ->where('business_id', $businessId)
            ->where('status', 'completed');
        if (! $seesAllHistory) {
            $this->scopeTransfersForStaff($statsQuery, $user);
        }

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'pieces' => (float) (clone $statsQuery)->sum('total_pieces'),
            'pending' => $pendingIncoming->count(),
        ];

        $mainBranch = $this->transfers->mainBranch($businessId);
        $destinations = $mainBranch
            ? $this->transfers->destinationBranches($businessId, (int) $mainBranch->id)
            : collect();
        $canSend = $this->canSendFromMain($mainBranch);
        $canUndo = $canSend;

        return view('branch-transfers.index', compact(
            'transfers',
            'stats',
            'mainBranch',
            'destinations',
            'pendingIncoming',
            'canSend',
            'canUndo'
        ));
    }

    public function create(Request $request)
    {
        $this->authorizeAny(['supply_to_branch']);

        $business = Auth::user()->business;
        $mainBranch = $this->transfers->mainBranch((int) $business->id);
        if (! $mainBranch) {
            return redirect()->route('branch-transfers.index')
                ->with('error', __('branch_transfers.no_main_branch'));
        }

        if (! $this->canSendFromMain($mainBranch)) {
            return redirect()->route('branch-transfers.index')
                ->with('error', __('branch_transfers.dest_receive_only'));
        }

        $destinations = $this->transfers->destinationBranches((int) $business->id, (int) $mainBranch->id);
        if ($destinations->isEmpty()) {
            return redirect()->route('branch-transfers.index')
                ->with('error', __('branch_transfers.need_other_branch'));
        }

        $toBranchId = (int) $request->query('to_branch_id', $destinations->first()->id);
        $toBranch = $destinations->firstWhere('id', $toBranchId) ?? $destinations->first();
        $catalog = $this->transfers->catalog($business, $mainBranch, $toBranch);

        return view('branch-transfers.create', [
            'mainBranch' => $mainBranch,
            'destinations' => $destinations,
            'toBranch' => $toBranch,
            'catalog' => $catalog,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAny(['supply_to_branch']);

        $request->validate([
            'to_branch_id' => 'required|exists:branches,id',
            'transfer_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.from_item_id' => 'required|exists:items,id',
            'items.*.item_packaging_id' => 'nullable|integer',
            'items.*.qty' => 'nullable|numeric|min:0',
        ]);

        $business = Auth::user()->business;
        $mainBranch = $this->transfers->mainBranch((int) $business->id);
        if (! $mainBranch) {
            return back()->with('error', __('branch_transfers.no_main_branch'))->withInput();
        }
        if (! $this->canSendFromMain($mainBranch)) {
            return redirect()->route('branch-transfers.index')
                ->with('error', __('branch_transfers.dest_receive_only'));
        }

        try {
            $transfer = $this->transfers->transfer(
                Auth::user(),
                $business,
                (int) $mainBranch->id,
                (int) $request->to_branch_id,
                (string) $request->transfer_date,
                $request->items,
                $request->notes
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput()
                ->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('branch-transfers.show', $transfer)
            ->with('success', __('branch_transfers.saved', ['ref' => $transfer->reference_no]));
    }

    public function show(BranchTransfer $branchTransfer)
    {
        $this->authorizeAny(['supply_to_branch', 'receive_branch_supply']);
        $this->ensureAccess($branchTransfer);

        $branchTransfer->load([
            'fromBranch',
            'toBranch',
            'user',
            'receivedBy',
            'items.fromItem.packagings.packagingType',
            'items.fromItem.receivingPackaging',
            'items.toItem.packagings.packagingType',
            'items.toItem.receivingPackaging',
        ]);

        $stockDisplay = app(\App\Services\ItemStockDisplayService::class);
        $mainBranch = $this->transfers->mainBranch((int) Auth::user()->business_id);
        $viewItemIsDestination = $this->viewerUsesDestinationStock($branchTransfer);

        $lines = $branchTransfer->items->map(function ($line) use ($stockDisplay, $branchTransfer, $viewItemIsDestination) {
            $viewItem = $viewItemIsDestination ? $line->toItem : $line->fromItem;
            $currentPieces = $viewItem ? (float) $viewItem->current_stock : 0.0;
            $incoming = (float) $line->quantity;
            $afterPieces = $currentPieces;
            if ($branchTransfer->isPending() && $viewItemIsDestination) {
                $afterPieces = $currentPieces + $incoming;
            }

            return [
                'line' => $line,
                'name' => $line->fromItem?->name ?? $line->toItem?->name,
                'qty_label' => $line->quantityLabel(),
                'available_now' => $viewItem
                    ? $stockDisplay->allUnitsDisplay($viewItem, $currentPieces)
                    : '—',
                'available_after' => $viewItem
                    ? $stockDisplay->allUnitsDisplay($viewItem, $afterPieces)
                    : '—',
            ];
        });

        return view('branch-transfers.show', [
            'transfer' => $branchTransfer,
            'lines' => $lines,
            'canReceive' => $this->canReceive($branchTransfer),
            'canUndo' => $this->canUndo($branchTransfer, $mainBranch),
            'showAfterReceive' => $branchTransfer->isPending() && $viewItemIsDestination,
            'stockBranchName' => $viewItemIsDestination
                ? ($branchTransfer->toBranch?->name ?? __('branch_transfers.branch_stock'))
                : ($branchTransfer->fromBranch?->name ?? __('branch_transfers.main_stock')),
        ]);
    }

    public function receive(BranchTransfer $branchTransfer)
    {
        $this->authorizeAny(['receive_branch_supply']);
        $this->ensureAccess($branchTransfer);

        if (! $this->canReceive($branchTransfer)) {
            return back()->with('error', __('branch_transfers.receive_not_allowed'));
        }

        try {
            $this->transfers->receive($branchTransfer, Auth::user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('branch-transfers.show', $branchTransfer)
            ->with('success', __('branch_transfers.received'));
    }

    public function cancel(BranchTransfer $branchTransfer)
    {
        $this->authorizeAny(['supply_to_branch']);
        $this->ensureAccess($branchTransfer);

        $mainBranch = $this->transfers->mainBranch((int) Auth::user()->business_id);
        if (! $this->canUndo($branchTransfer, $mainBranch)) {
            return back()->with('error', __('branch_transfers.undo_not_allowed'));
        }

        try {
            $this->transfers->cancel($branchTransfer);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('branch-transfers.show', $branchTransfer)
            ->with('success', __('branch_transfers.cancelled'));
    }

    private function seesAllTransferHistory(): bool
    {
        return Auth::user()?->role === 'owner';
    }

    private function isTransferCreator(BranchTransfer $transfer): bool
    {
        return (int) $transfer->user_id === (int) Auth::id();
    }

    private function scopeTransfersForStaff($query, $user)
    {
        return $query->where(function ($scoped) use ($user) {
            $scoped->where('user_id', $user->id);
            if ($user->branch_id) {
                $scoped->orWhere('to_branch_id', (int) $user->branch_id);
            }
        });
    }

    private function isDestinationStaff(BranchTransfer $transfer): bool
    {
        $user = Auth::user();
        if (! $user?->branch_id) {
            return false;
        }

        return (int) $user->branch_id === (int) $transfer->to_branch_id;
    }

    private function canSendFromMain(?\App\Models\Branch $mainBranch): bool
    {
        if (! $mainBranch) {
            return false;
        }

        $user = Auth::user();
        if (! $user?->can('supply_to_branch')) {
            return false;
        }

        if ($this->seesAllTransferHistory() || ! $user->branch_id) {
            return true;
        }

        return (int) $user->branch_id === (int) $mainBranch->id;
    }

    private function canUndo(BranchTransfer $transfer, ?\App\Models\Branch $mainBranch): bool
    {
        // Undo only while awaiting receive — hide after destination has received.
        if (! $transfer->isPending()) {
            return false;
        }

        if (! $this->canSendFromMain($mainBranch)) {
            return false;
        }

        return $this->seesAllTransferHistory() || $this->isTransferCreator($transfer);
    }

    private function canReceive(BranchTransfer $transfer): bool
    {
        if (! $transfer->isPending()) {
            return false;
        }

        $user = Auth::user();
        if (! $user?->can('receive_branch_supply')) {
            return false;
        }

        if ($this->seesAllTransferHistory() || ! $user->branch_id) {
            return true;
        }

        return (int) $user->branch_id === (int) $transfer->to_branch_id;
    }

    private function viewerUsesDestinationStock(BranchTransfer $transfer): bool
    {
        if ($this->seesAllTransferHistory() || ! Auth::user()->branch_id) {
            return true;
        }

        return (int) Auth::user()->branch_id === (int) $transfer->to_branch_id;
    }

    private function ensureAccess(BranchTransfer $transfer): void
    {
        $user = Auth::user();

        if ((int) $transfer->business_id !== (int) $user->business_id) {
            abort(403);
        }

        if ($this->seesAllTransferHistory()) {
            return;
        }

        if ($this->isTransferCreator($transfer)) {
            return;
        }

        // Destination shop staff can open supplies sent to their branch (pending or history).
        if ($this->isDestinationStaff($transfer)) {
            return;
        }

        abort(403);
    }
}

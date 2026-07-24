<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index()
    {
        Gate::authorize('manage_suppliers');

        $businessId = Auth::user()->business_id;
        $branchFilterId = $this->resolveBranchFilterId();

        $query = Supplier::query()
            ->where('business_id', $businessId)
            ->with('branch')
            ->orderBy('name');

        if ($branchFilterId) {
            $query->where('branch_id', $branchFilterId);
        }

        $suppliers = $query->get();
        $viewingAllBranches = $this->actsAsBusinessWideViewer() && ! $branchFilterId;
        $activeBranchName = $branchFilterId
            ? (active_branch()?->name ?? Branch::find($branchFilterId)?->name ?? 'Branch')
            : null;
        $branches = $this->allBusinessBranches();
        $canMigrateFromBranch = $branches->count() > 1;

        return view('registration.suppliers.index', compact(
            'suppliers',
            'viewingAllBranches',
            'activeBranchName',
            'branchFilterId',
            'branches',
            'canMigrateFromBranch',
        ));
    }

    public function create()
    {
        Gate::authorize('manage_suppliers');

        return view('registration.suppliers.create', [
            'branches' => $this->writableBranches(),
            'defaultBranchId' => $this->defaultBranchIdForForm(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('manage_suppliers');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:20',
            'region' => 'nullable|string|max:100',
            'branch_id' => $this->branchValidationRule(),
        ]);

        $branchId = $this->resolveBranchIdFromRequest($request);
        if (! $branchId) {
            return redirect()->back()->withInput()->with('error', 'Select a branch for this supplier.');
        }

        Supplier::create([
            'business_id' => Auth::user()->business_id,
            'branch_id' => $branchId,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => '+255'.$request->phone,
            'region' => $request->region,
        ]);

        return redirect()->route('suppliers.index')->with('success', 'Supplier registered successfully.');
    }

    public function edit(Supplier $supplier)
    {
        Gate::authorize('manage_suppliers');
        $this->ensureAccess($supplier);

        return view('registration.suppliers.edit', [
            'supplier' => $supplier,
            'branches' => $this->writableBranches(),
            'defaultBranchId' => (int) ($supplier->branch_id ?: $this->defaultBranchIdForForm()),
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        Gate::authorize('manage_suppliers');
        $this->ensureAccess($supplier);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:20',
            'region' => 'nullable|string|max:100',
            'branch_id' => $this->branchValidationRule(),
        ]);

        $branchId = $this->resolveBranchIdFromRequest($request) ?: (int) $supplier->branch_id;
        if (! $branchId) {
            return redirect()->back()->withInput()->with('error', 'Select a branch for this supplier.');
        }

        $supplier->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => '+255'.$request->phone,
            'region' => $request->region,
            'branch_id' => $branchId,
        ]);

        return redirect()->route('suppliers.index')->with('success', 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier)
    {
        Gate::authorize('manage_suppliers');
        $this->ensureAccess($supplier);
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Supplier removed.');
    }

    public function listForBranch(Branch $branch)
    {
        Gate::authorize('manage_suppliers');
        $this->ensureBranchInBusiness($branch);

        $suppliers = Supplier::query()
            ->where('business_id', Auth::user()->business_id)
            ->where('branch_id', $branch->id)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email', 'region']);

        return response()->json([
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'suppliers' => $suppliers,
        ]);
    }

    public function migrateFromBranch(Request $request)
    {
        Gate::authorize('manage_suppliers');

        $branchIds = $this->allBusinessBranches()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $validated = $request->validate([
            'from_branch_id' => ['required', 'integer', Rule::in($branchIds)],
            'to_branch_id' => ['required', 'integer', Rule::in($branchIds)],
            'mode' => ['required', Rule::in(['copy', 'move'])],
            'supplier_ids' => ['nullable', 'array'],
            'supplier_ids.*' => ['integer'],
        ]);

        $fromId = (int) $validated['from_branch_id'];
        $toId = (int) $validated['to_branch_id'];

        if ($fromId === $toId) {
            return redirect()->back()->with('error', 'Choose a different destination branch.');
        }

        // Staff locked to one branch can only migrate into their branch.
        if (! $this->actsAsBusinessWideViewer() && Auth::user()->branch_id) {
            if ((int) Auth::user()->branch_id !== $toId) {
                return redirect()->back()->with('error', 'You can only migrate suppliers into your own branch.');
            }
        }

        $query = Supplier::query()
            ->where('business_id', Auth::user()->business_id)
            ->where('branch_id', $fromId);

        if (! empty($validated['supplier_ids'])) {
            $query->whereIn('id', $validated['supplier_ids']);
        }

        $sourceSuppliers = $query->get();
        if ($sourceSuppliers->isEmpty()) {
            return redirect()->back()->with('error', 'No suppliers found on the source branch.');
        }

        $existingPhones = Supplier::query()
            ->where('business_id', Auth::user()->business_id)
            ->where('branch_id', $toId)
            ->pluck('phone')
            ->filter()
            ->map(fn ($phone) => strtolower((string) $phone))
            ->all();

        $copied = 0;
        $moved = 0;
        $skipped = 0;

        foreach ($sourceSuppliers as $supplier) {
            $phoneKey = strtolower((string) ($supplier->phone ?? ''));
            $alreadyExists = $phoneKey !== '' && in_array($phoneKey, $existingPhones, true);

            if ($validated['mode'] === 'copy') {
                if ($alreadyExists) {
                    $skipped++;
                    continue;
                }

                Supplier::create([
                    'business_id' => $supplier->business_id,
                    'branch_id' => $toId,
                    'name' => $supplier->name,
                    'contact_person' => $supplier->contact_person,
                    'phone' => $supplier->phone,
                    'email' => $supplier->email,
                    'address' => $supplier->address,
                    'region' => $supplier->region,
                ]);
                if ($phoneKey !== '') {
                    $existingPhones[] = $phoneKey;
                }
                $copied++;
                continue;
            }

            // move
            if ($alreadyExists) {
                $skipped++;
                continue;
            }

            $supplier->update(['branch_id' => $toId]);
            if ($phoneKey !== '') {
                $existingPhones[] = $phoneKey;
            }
            $moved++;
        }

        $parts = [];
        if ($copied > 0) {
            $parts[] = "{$copied} copied";
        }
        if ($moved > 0) {
            $parts[] = "{$moved} moved";
        }
        if ($skipped > 0) {
            $parts[] = "{$skipped} skipped (already on destination)";
        }

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier migrate complete: '.implode(', ', $parts ?: ['nothing changed']).'.');
    }

    private function ensureAccess(Supplier $supplier): void
    {
        if ((int) $supplier->business_id !== (int) Auth::user()->business_id) {
            abort(403);
        }

        $branchFilterId = $this->resolveBranchFilterId();
        if ($branchFilterId && (int) $supplier->branch_id !== $branchFilterId) {
            abort(403);
        }
    }

    private function ensureBranchInBusiness(Branch $branch): void
    {
        if ((int) $branch->business_id !== (int) Auth::user()->business_id) {
            abort(403);
        }
    }

    private function allBusinessBranches()
    {
        return Branch::query()
            ->where('business_id', Auth::user()->business_id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    private function writableBranches()
    {
        $query = Branch::query()
            ->where('business_id', Auth::user()->business_id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name');

        if (! $this->actsAsBusinessWideViewer() && Auth::user()->branch_id) {
            $query->where('id', Auth::user()->branch_id);
        }

        return $query->get();
    }

    private function branchValidationRule(): array
    {
        $branches = $this->writableBranches();

        if ($this->actsAsBusinessWideViewer() && $branches->count() > 1) {
            return ['required', Rule::in($branches->pluck('id')->all())];
        }

        return ['nullable'];
    }

    private function resolveBranchIdFromRequest(Request $request): ?int
    {
        $branches = $this->writableBranches();
        if ($branches->isEmpty()) {
            return $this->resolveBranchFilterId();
        }

        if ($this->actsAsBusinessWideViewer() && $branches->count() > 1) {
            $branchId = (int) $request->input('branch_id');
            if (! $branches->contains(fn ($b) => (int) $b->id === $branchId)) {
                return null;
            }

            return $branchId;
        }

        return (int) ($this->resolveBranchFilterId() ?: $branches->first()->id);
    }

    private function defaultBranchIdForForm(): ?int
    {
        return $this->resolveBranchFilterId()
            ?: (int) ($this->writableBranches()->first()?->id ?: 0)
            ?: null;
    }
}

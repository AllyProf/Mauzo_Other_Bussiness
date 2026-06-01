<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Business;
use App\Services\ActiveBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BranchController extends Controller
{
    public function index()
    {
        $this->authorizeAny(['manage_branches']);

        $business = Business::with('plan')->findOrFail(Auth::user()->business_id);
        $branches = Branch::where('business_id', $business->id)->orderByDesc('is_default')->orderBy('name')->get();

        return view('branches.index', compact('business', 'branches'));
    }

    public function store(Request $request)
    {
        $this->authorizeAny(['manage_branches']);

        $business = Business::with('plan')->findOrFail(Auth::user()->business_id);
        $currentCount = Branch::where('business_id', $business->id)->count();
        $maxBranches = $business->maxBranchesAllowed();

        if ($maxBranches !== null && $currentCount >= $maxBranches) {
            return redirect()->back()->with(
                'error',
                "Your {$business->plan?->name} plan allows up to {$maxBranches} branch(es). Upgrade your plan to add more."
            );
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:1000',
            'location' => 'nullable|string|max:255',
            'leader_name' => 'nullable|string|max:255',
            'leader_phone' => 'nullable|string|max:50',
            'leader_email' => 'nullable|email|max:255',
        ]);

        $isFirst = $currentCount === 0;

        Branch::create([
            'business_id' => $business->id,
            'name' => $request->name,
            'address' => $request->address,
            'location' => $request->location,
            'leader_name' => $request->leader_name,
            'leader_phone' => $this->normalizePhone($request->leader_phone),
            'leader_email' => $request->leader_email,
            'is_active' => true,
            'is_default' => $isFirst,
        ]);

        return redirect()->back()->with('success', 'Branch registered successfully.');
    }

    public function update(Request $request, Branch $branch)
    {
        $this->authorizeAny(['manage_branches']);
        $this->ensureBranchAccess($branch);

        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:1000',
            'location' => 'nullable|string|max:255',
            'leader_name' => 'nullable|string|max:255',
            'leader_phone' => 'nullable|string|max:50',
            'leader_email' => 'nullable|email|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $branch->update([
            'name' => $request->name,
            'address' => $request->address,
            'location' => $request->location,
            'leader_name' => $request->leader_name,
            'leader_phone' => $this->normalizePhone($request->leader_phone),
            'leader_email' => $request->leader_email,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->back()->with('success', 'Branch updated successfully.');
    }

    public function switch(Request $request, ActiveBranchService $branchService)
    {
        $this->authorizeAny(['manage_branches']);

        $branchId = $request->input('branch_id');
        if ($branchId === '' || $branchId === 'all') {
            $branchId = null;
        } elseif ($branchId !== null) {
            $branchId = (int) $branchId;
        }

        $branchService->setActiveBranch($branchId);

        $message = $branchId
            ? 'Switched to '.$branchService->activeBranch()?->name.'.'
            : 'Now viewing all branches.';

        return redirect()->to($request->headers->get('referer', url('/home')))->with('success', $message);
    }

    public function destroy(Branch $branch)
    {
        $this->authorizeAny(['manage_branches']);
        $this->ensureBranchAccess($branch);

        if ($branch->is_default) {
            return redirect()->back()->with('error', 'The default branch cannot be deleted.');
        }

        if ($branch->users()->exists()) {
            return redirect()->back()->with('error', 'Move or remove employees from this branch before deleting it.');
        }

        $branch->delete();

        return redirect()->back()->with('success', 'Branch deleted successfully.');
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);
        $digits = ltrim($digits, '0');

        if (str_starts_with($digits, '255')) {
            $digits = substr($digits, 3);
        }

        return $digits ? '+255'.$digits : null;
    }

    private function authorizeOwner(): void
    {
        $this->authorizeAny(['manage_branches']);
    }

    private function ensureBranchAccess(Branch $branch): void
    {
        if ($branch->business_id != Auth::user()->business_id) {
            abort(403);
        }
    }
}

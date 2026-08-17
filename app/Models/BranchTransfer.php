<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchTransfer extends Model
{
    protected $fillable = [
        'business_id',
        'from_branch_id',
        'to_branch_id',
        'user_id',
        'reference_no',
        'transfer_date',
        'total_items',
        'total_pieces',
        'notes',
        'status',
        'received_by',
        'received_at',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'total_pieces' => 'float',
        'received_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function fromBranch()
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch()
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items()
    {
        return $this->hasMany(BranchTransferItem::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}

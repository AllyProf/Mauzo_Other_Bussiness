<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceMaterialReceipt extends Model
{
    protected $fillable = [
        'service_material_id',
        'user_id',
        'quantity',
        'total_cost',
        'received_date',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'total_cost' => 'decimal:2',
        'received_date' => 'date',
    ];

    public function material()
    {
        return $this->belongsTo(ServiceMaterial::class, 'service_material_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function costPerUnit(): float
    {
        $qty = (float) $this->quantity;

        return $qty > 0 ? round((float) $this->total_cost / $qty, 4) : 0;
    }
}

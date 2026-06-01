<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ['business_id', 'name', 'contact_person', 'phone', 'email', 'address', 'region'];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}

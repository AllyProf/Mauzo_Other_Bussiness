<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['business_id', 'name', 'source_business_type_key'];

    public function items()
    {
        return $this->hasMany(Item::class);
    }
}

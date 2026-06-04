<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Packaging extends Model
{
    protected $fillable = ['business_id', 'name', 'source_business_type_key'];
}

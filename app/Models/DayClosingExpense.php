<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DayClosingExpense extends Model
{
    protected $fillable = [
        'day_closing_id',
        'description',
        'amount',
        'payment_method',
    ];

    public function dayClosing()
    {
        return $this->belongsTo(DayClosing::class);
    }
}

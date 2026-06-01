<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'business_id', 'action', 'description', 'ip_address'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public static function log($action, $description)
    {
        self::create([
            'user_id' => \Illuminate\Support\Facades\Auth::id(),
            'business_id' => \Illuminate\Support\Facades\Auth::check() ? \Illuminate\Support\Facades\Auth::user()->business_id : null,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip()
        ]);
    }
}

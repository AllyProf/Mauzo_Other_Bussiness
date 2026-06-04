<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformSmsLog extends Model
{
    protected $fillable = [
        'business_id',
        'user_id',
        'phone',
        'recipient_name',
        'message',
        'purpose',
        'status',
        'provider_response',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purposeLabel(): string
    {
        return match ($this->purpose) {
            'registration_verification' => 'Registration Verification',
            'registration_approved' => 'Registration Approved',
            'registration_rejected' => 'Registration Rejected',
            'password_reset' => 'Password Reset',
            'account_suspended' => 'Account Suspended',
            'account_reactivated' => 'Account Reactivated',
            'expiry_reminder' => 'Expiry Reminder',
            'invoice_reminder' => 'Invoice Reminder',
            'invoice_issued' => 'Invoice Issued',
            'payment_confirmed' => 'Payment Confirmed',
            'ticket_new_admin' => 'New Ticket (Admin)',
            'ticket_reply_business' => 'Ticket Reply',
            'staff_welcome' => 'Staff Welcome',
            'demo_lead_admin' => 'Demo Lead (Admin)',
            'auto_suspend' => 'Auto Suspend',
            default => ucwords(str_replace('_', ' ', $this->purpose)),
        };
    }
}

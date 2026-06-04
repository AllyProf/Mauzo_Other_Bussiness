<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSmsLog extends Model
{
    protected $fillable = [
        'business_id',
        'user_id',
        'customer_id',
        'campaign_id',
        'phone',
        'recipient_email',
        'recipient_name',
        'message',
        'channel',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CustomerCommunicationCampaign::class, 'campaign_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'sent' => 'Sent',
            'failed' => 'Failed',
            default => 'Pending',
        };
    }

    public function channelLabel(): string
    {
        return match ($this->channel) {
            'sms' => 'SMS',
            'email' => 'Email',
            'email_sms' => 'Email SMS',
            default => strtoupper($this->channel),
        };
    }

    public function purposeLabel(): string
    {
        return match ($this->purpose) {
            'new_product' => 'New Product',
            'promotion' => 'Promotion',
            'debt_reminder' => 'Debt Reminder',
            'debt_reminder_due_soon' => 'Debt Due Soon',
            'debt_reminder_due_soon_2' => 'Debt Due Soon (2nd)',
            'debt_reminder_due' => 'Debt Due Today',
            'debt_reminder_overdue' => 'Debt Overdue',
            'staff_welcome' => 'Staff Welcome',
            'staff_password_reset' => 'Staff Password Reset',
            'staff_activated' => 'Staff Activated',
            'staff_deactivated' => 'Staff Deactivated',
            'handover_submitted_owner' => 'Handover Submitted (Owner)',
            'handover_verified_staff' => 'Handover Verified (Staff)',
            'note_reminder' => 'Note Reminder',
            default => 'General',
        };
    }

    public function recipientContact(): string
    {
        if ($this->channel === 'email' && $this->recipient_email) {
            return $this->recipient_email;
        }

        return $this->phone ?? '—';
    }
}


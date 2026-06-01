<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    public const BILLING_FIXED = 'fixed_monthly';

    public const BILLING_PROFIT_SHARE = 'profit_share';

    protected $fillable = [
        'name',
        'price',
        'billing_model',
        'profit_share_percent',
        'profit_share_basis',
        'minimum_monthly_fee',
        'duration_months',
        'max_items',
        'max_users',
        'max_business_types',
        'max_branches',
        'max_sms',
        'max_email_sms',
        'features',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'profit_share_percent' => 'decimal:2',
        'minimum_monthly_fee' => 'decimal:2',
        'duration_months' => 'integer',
        'max_items' => 'integer',
        'max_users' => 'integer',
        'max_business_types' => 'integer',
        'max_branches' => 'integer',
        'max_sms' => 'integer',
        'max_email_sms' => 'integer',
    ];

    public function formatLimit(?int $value): string
    {
        return ($value ?? 0) === 0 ? 'Unlimited' : number_format($value);
    }

    public function usesProfitShareBilling(): bool
    {
        return $this->billing_model === self::BILLING_PROFIT_SHARE;
    }

    public function billingModelLabel(): string
    {
        return $this->usesProfitShareBilling()
            ? 'Profit share'
            : 'Fixed fee';
    }

    public function billingSummary(): string
    {
        if ($this->usesProfitShareBilling()) {
            $basis = $this->profit_share_basis === 'gross_profit' ? 'gross' : 'net';

            return number_format((float) $this->profit_share_percent, 1).'% of '.$basis.' profit';
        }

        $duration = max(1, (int) $this->duration_months);

        return 'TZS '.number_format((float) $this->price, 0).' / '.$duration.' mo';
    }
}

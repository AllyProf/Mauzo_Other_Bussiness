<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InitialSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 0. Create Super Admin (Software Owner)
        \App\Models\User::create([
            'name' => 'Software Owner',
            'email' => 'admin@sp-pos.com',
            'password' => \Illuminate\Support\Facades\Hash::make('admin123'),
            'business_id' => null, // Platform owners don't belong to a single business
            'role' => 'super_admin'
        ]);

        // 1. Create Default Plans
        $basicPlan = \App\Models\Plan::create(['name' => 'Basic', 'price' => 20.00, 'duration_months' => 1]);
        $proPlan = \App\Models\Plan::create(['name' => 'Professional', 'price' => 50.00, 'duration_months' => 1]);
        $enterprisePlan = \App\Models\Plan::create(['name' => 'Enterprise', 'price' => 150.00, 'duration_months' => 1]);

        // 2. Create Default Business
        $business = \App\Models\Business::create([
            'name' => 'My Spare Parts Shop',
            'email' => 'shop@spareparts.com',
            'address' => 'Arusha, Tanzania',
            'phone' => '255123456789',
            'plan_id' => $proPlan->id,
            'expiry_date' => now()->addMonth()
        ]);

        \App\Models\Branch::createDefaultForBusiness($business);

        // 2. Create Business Owner
        \App\Models\User::create([
            'name' => 'Business Owner',
            'email' => 'owner@spareparts.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'business_id' => $business->id,
            'role' => 'owner'
        ]);

        // 3. Create Default Packaging Types for this business
        \App\Models\Packaging::create(['business_id' => $business->id, 'name' => 'Piece']);
        \App\Models\Packaging::create(['business_id' => $business->id, 'name' => 'Box']);
        \App\Models\Packaging::create(['business_id' => $business->id, 'name' => 'Set']);

        // 4. Create Default Categories
        \App\Models\Category::create(['business_id' => $business->id, 'name' => 'Engine']);
        \App\Models\Category::create(['business_id' => $business->id, 'name' => 'Suspension']);

        // 5. Initialize platform settings
        app(\App\Services\PlatformSettingsService::class)->update([
            'default_plan_id' => $proPlan->id,
            'support_email' => 'admin@sp-pos.com',
        ]);
    }
}

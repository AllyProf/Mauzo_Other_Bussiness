<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Support\PhoneCountries;
use Illuminate\Database\Seeder;

class MiglopSuppliersSeeder extends Seeder
{
    private const BUSINESS_ID = 28;

    private const BRANCH_ID = 35;

    public function run(): void
    {
        $rows = require database_path('data/miglop_suppliers.php');

        foreach ($rows as $row) {
            $phone = trim((string) ($row['phone'] ?? ''));
            $normalizedPhone = $phone !== '' ? PhoneCountries::combine('tz', $phone) : null;

            Supplier::updateOrCreate(
                [
                    'business_id' => self::BUSINESS_ID,
                    'branch_id' => self::BRANCH_ID,
                    'name' => $row['name'],
                ],
                [
                    'supplier_type' => $row['type'] ?? null,
                    'contact_person' => $row['contact_person'] ?? null,
                    'phone' => $normalizedPhone,
                    'email' => $row['email'] ?? null,
                    'region' => $row['city'] ?? null,
                    'is_active' => (bool) ($row['active'] ?? true),
                ]
            );
        }

        $count = Supplier::where('business_id', self::BUSINESS_ID)->count();
        $this->command?->info("MIGLOP suppliers: {$count} total on business ".self::BUSINESS_ID);
    }
}

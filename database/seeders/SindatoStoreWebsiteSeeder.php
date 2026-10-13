<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\BusinessWebsite;
use App\Services\BusinessWebsiteService;
use Illuminate\Database\Seeder;

class SindatoStoreWebsiteSeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::query()
            ->where('name', 'like', '%SINDATO%')
            ->orWhere('name', 'like', '%Sindato%')
            ->orderBy('id')
            ->first();

        if (! $business) {
            $this->command?->warn('No Sindato business found — skip website seed.');

            return;
        }

        $service = app(BusinessWebsiteService::class);
        $defaults = $service->defaultPages($business);
        $name = $business->name;

        $pages = array_merge($defaults, [
            'home' => [
                'hero_title' => 'Welcome to '.$name,
                'hero_subtitle' => 'Your neighborhood store for drinks, essentials, and everyday needs — fair prices, friendly service.',
                'hero_button_label' => 'View products',
                'hero_button_url' => 'products',
                'intro_title' => 'Products we offer',
                'intro_body' => '',
            ],
            'products' => [
                'title' => 'Our products',
                'body' => 'Prices below are from our live inventory. Availability updates as we sell during the day.',
                'show_prices' => true,
                'show_out_of_stock' => false,
            ],
            'about' => [
                'title' => 'About '.$name,
                'body' => $name.' is a retail business built on honest pricing and fast service. We use Mauzo Link to keep stock and accounts accurate so you always get what you pay for.',
                'highlight_1_title' => 'Quality products',
                'highlight_1_body' => 'Trusted brands and fresh stock from verified suppliers.',
                'highlight_2_title' => 'Fair prices',
                'highlight_2_body' => 'Clear shelf prices — the same data you see here.',
                'highlight_3_title' => 'Easy ordering',
                'highlight_3_body' => 'WhatsApp, phone, or walk in — your choice.',
            ],
            'contact' => [
                'title' => 'Visit or call',
                'body' => 'We are happy to help with product questions and bulk orders.',
                'hours' => 'Mon – Sat: 8:00 – 21:00 · Sun: 10:00 – 18:00',
                'show_contact_form' => true,
            ],
            'order' => [
                'title' => 'How to order',
                'body' => 'Send your list — we confirm total and availability before you pay.',
                'steps' => "1. Open Products and note what you need (size/unit).\n2. Message us on WhatsApp with your list and delivery or pickup.\n3. We confirm the amount and prepare your order.\n4. Pay and collect — or receive delivery where available.",
                'whatsapp_message' => 'Hello '.$name.', I would like to order: ',
            ],
        ]);

        BusinessWebsite::updateOrCreate(
            ['business_id' => $business->id],
            [
                'slug' => 'sindato-store',
                'is_published' => true,
                'tagline' => 'Retail · Liquor · Everyday essentials',
                'primary_color' => '#940000',
                'social_links' => [
                    'whatsapp' => preg_replace('/\D+/', '', (string) ($business->phone ?? '')),
                    'facebook' => '',
                    'instagram' => '',
                ],
                'pages' => $pages,
            ]
        );

        $this->command?->info("Published website for {$business->name}: ".url('/site/sindato-store'));
    }
}

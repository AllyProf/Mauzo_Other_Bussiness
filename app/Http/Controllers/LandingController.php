<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Support\LandingSeo;
use App\Services\PlatformSettingsService;
use App\Services\RegistrationFunnelService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class LandingController extends Controller
{
    public function index(Request $request, PlatformSettingsService $platformSettings, RegistrationFunnelService $funnel)
    {
        $funnel->track($request, 'landing_view');

        $settings = $platformSettings->all();

        $platformName = $settings['platform_name'] ?? config('app.name');
        $supportEmail = $settings['support_email'] ?? '';
        $supportPhone = $settings['support_phone'] ?? '';

        return view('landing.index', [
            'platformSettings' => $settings,
            'platformName' => $platformName,
            'supportEmail' => $supportEmail,
            'supportPhone' => $supportPhone,
            'landingSeo' => LandingSeo::build($platformName, $supportEmail, $supportPhone),
            'plans' => Plan::query()->orderBy('price')->get(),
            'registrationOpen' => $platformSettings->isRegistrationOpen(),
            'trialDays' => (int) ($settings['default_trial_days'] ?? 30),
        ]);
    }

    public function privacy(PlatformSettingsService $platformSettings)
    {
        $settings = $platformSettings->all();
        $platformName = $settings['platform_name'] ?? config('app.name');
        $supportEmail = $settings['support_email'] ?? '';
        $supportPhone = $settings['support_phone'] ?? '';
        $publicAddress = $settings['public_address'] ?? '';
        $companyName = (string) ($settings['invoice_company_name']
            ?? config('seo.organization.legal_name', 'EmCa Technologies LTD'));
        // Correct known historical misspelling in stored platform settings.
        $companyName = str_replace('Techonologies', 'Technologies', $companyName);

        $locale = app()->getLocale();
        $canonical = route('landing.privacy');
        $ogImagePath = (string) config('seo.og_image', 'mauzo_link.png');
        $ogImage = URL::to('/'.ltrim($ogImagePath, '/'));

        $landingSeo = [
            'title' => trim($platformName.' — '.__('privacy.seo.title')),
            'description' => __('privacy.seo.description'),
            'keywords' => __('privacy.seo.keywords'),
            'canonical' => $canonical,
            'robots' => 'index,follow',
            'og_locale' => $locale === 'sw' ? 'sw_TZ' : 'en_US',
            'og_image' => $ogImage,
            'json_ld' => [[
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => __('privacy.page_title'),
                'description' => __('privacy.seo.description'),
                'url' => $canonical,
                'inLanguage' => $locale === 'sw' ? 'sw-TZ' : 'en-TZ',
                'isPartOf' => [
                    '@type' => 'WebSite',
                    'name' => $platformName,
                    'url' => route('landing.index'),
                ],
            ]],
        ];

        return view('landing.privacy', [
            'platformSettings' => $settings,
            'platformName' => $platformName,
            'supportEmail' => $supportEmail,
            'supportPhone' => $supportPhone,
            'publicAddress' => $publicAddress,
            'companyName' => $companyName,
            'registrationOpen' => $platformSettings->isRegistrationOpen(),
            'landingSeo' => $landingSeo,
            'updatedAt' => Carbon::create(2026, 10, 26)->startOfDay(),
        ]);
    }
}

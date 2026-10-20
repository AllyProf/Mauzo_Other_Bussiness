<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

class LandingSeo
{
    /**
     * @return array{
     *   title: string,
     *   description: string,
     *   keywords: string,
     *   canonical: string,
     *   robots: string,
     *   og_locale: string,
     *   og_image: string,
     *   json_ld: array<int, array<string, mixed>>
     * }
     */
    public static function build(string $platformName, string $supportEmail = '', string $supportPhone = ''): array
    {
        $locale = app()->getLocale();
        $title = trim($platformName.' — '.__('landing.seo.title'));
        $description = __('landing.seo.description');
        $canonical = route('landing.index');

        $ogImagePath = (string) config('seo.og_image', 'mauzo_link.png');
        $ogImage = URL::to('/'.ltrim($ogImagePath, '/'));

        $orgName = (string) config('seo.organization.legal_name', $platformName);
        $areaServed = (string) config('seo.organization.area_served', 'TZ');

        $jsonLd = [
            self::organizationSchema($platformName, $orgName, $canonical, $ogImage, $supportEmail, $supportPhone, $areaServed),
            self::softwareSchema($platformName, $description, $canonical, $ogImage),
            self::webSiteSchema($platformName, $canonical),
        ];

        $faq = self::faqSchema();
        if ($faq !== null) {
            $jsonLd[] = $faq;
        }

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => __('landing.seo.keywords'),
            'canonical' => $canonical,
            'robots' => 'index,follow,max-image-preview:large',
            'og_locale' => $locale === 'sw' ? 'sw_TZ' : 'en_US',
            'og_image' => $ogImage,
            'json_ld' => $jsonLd,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function organizationSchema(
        string $platformName,
        string $orgName,
        string $url,
        string $logoUrl,
        string $email,
        string $phone,
        string $areaServed
    ): array {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $platformName,
            'legalName' => $orgName,
            'url' => $url,
            'logo' => $logoUrl,
            'areaServed' => $areaServed,
        ];

        if ($email !== '') {
            $schema['email'] = $email;
        }
        if ($phone !== '') {
            $schema['telephone'] = $phone;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private static function softwareSchema(string $name, string $description, string $url, string $image): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $name,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web, Android, iOS',
            'description' => $description,
            'url' => $url,
            'image' => $image,
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'TZS',
                'description' => __('landing.seo.trial_offer'),
            ],
            'inLanguage' => ['sw', 'en'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function webSiteSchema(string $name, string $url): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $name,
            'url' => $url,
            'inLanguage' => ['sw-TZ', 'en-TZ'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function faqSchema(): ?array
    {
        $items = __('landing.faq.items');
        if (! is_array($items) || $items === []) {
            return null;
        }

        $entities = [];
        foreach ($items as $item) {
            if (! is_array($item) || empty($item['q']) || empty($item['a'])) {
                continue;
            }
            $entities[] = [
                '@type' => 'Question',
                'name' => (string) $item['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => (string) $item['a'],
                ],
            ];
        }

        if ($entities === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }
}

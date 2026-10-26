<?php

return [
    /*
    | Public OG/social image (path under public/). Use at least 1200×630 when possible.
    */
    'og_image' => env('SEO_OG_IMAGE', 'gp-assets/img/about-pos.png'),

    'twitter_site' => env('SEO_TWITTER_SITE', ''),

    'organization' => [
        'legal_name' => env('SEO_ORG_NAME', 'EMCA Technologies'),
        'area_served' => 'TZ',
    ],

    'sitemap' => [
        ['route' => 'landing.index', 'changefreq' => 'weekly', 'priority' => '1.0'],
        ['route' => 'register.business', 'changefreq' => 'monthly', 'priority' => '0.8'],
        ['route' => 'landing.privacy', 'changefreq' => 'yearly', 'priority' => '0.4'],
    ],

    'robots_disallow' => [
        '/admin',
        '/home',
        '/login',
        '/locale/',
        '/api/',
    ],
];

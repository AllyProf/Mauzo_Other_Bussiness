<?php

/**
 * Service business templates: categories and default services with prices (TZS).
 * Imported per branch from Services → Import template.
 */
return [
    'stationery_print' => [
        'label' => 'Print & Copy Centre',
        'icon' => 'fa-print',
        'categories' => [
            [
                'name' => 'Printing',
                'services' => [
                    ['name' => 'A4 Black & White', 'unit_label' => 'per page', 'default_price' => 100],
                    ['name' => 'A4 Color', 'unit_label' => 'per page', 'default_price' => 300],
                    ['name' => 'A3 Black & White', 'unit_label' => 'per page', 'default_price' => 200],
                    ['name' => 'A3 Color', 'unit_label' => 'per page', 'default_price' => 500],
                    ['name' => 'Passport Photo Print', 'unit_label' => 'per sheet', 'default_price' => 2000],
                ],
            ],
            [
                'name' => 'Scanning',
                'services' => [
                    ['name' => 'Document Scan A4', 'unit_label' => 'per page', 'default_price' => 200],
                    ['name' => 'Document Scan A3', 'unit_label' => 'per page', 'default_price' => 400],
                ],
            ],
            [
                'name' => 'Binding & Finishing',
                'services' => [
                    ['name' => 'Spiral Binding', 'unit_label' => 'per book', 'default_price' => 3000],
                    ['name' => 'Lamination A4', 'unit_label' => 'per sheet', 'default_price' => 1000],
                    ['name' => 'Photocopy A4', 'unit_label' => 'per copy', 'default_price' => 100],
                ],
            ],
        ],
    ],
    'cyber_cafe' => [
        'label' => 'Cyber Cafe / ICT',
        'icon' => 'fa-desktop',
        'categories' => [
            [
                'name' => 'Internet & Computer',
                'services' => [
                    ['name' => 'Internet (1 hour)', 'unit_label' => 'per hour', 'default_price' => 1000],
                    ['name' => 'Typing', 'unit_label' => 'per page', 'default_price' => 500],
                    ['name' => 'CV / Document Formatting', 'unit_label' => 'per job', 'default_price' => 5000],
                ],
            ],
            [
                'name' => 'Mobile Services',
                'services' => [
                    ['name' => 'Phone Software Install', 'unit_label' => 'per device', 'default_price' => 10000],
                    ['name' => 'Data Transfer', 'unit_label' => 'per job', 'default_price' => 3000],
                ],
            ],
        ],
    ],
    'salon_services' => [
        'label' => 'Salon & Grooming',
        'icon' => 'fa-scissors',
        'categories' => [
            [
                'name' => 'Hair',
                'services' => [
                    ['name' => 'Haircut', 'unit_label' => 'per service', 'default_price' => 8000],
                    ['name' => 'Hair Wash', 'unit_label' => 'per service', 'default_price' => 5000],
                    ['name' => 'Braiding', 'unit_label' => 'per service', 'default_price' => 25000],
                ],
            ],
            [
                'name' => 'Nails & Beauty',
                'services' => [
                    ['name' => 'Manicure', 'unit_label' => 'per service', 'default_price' => 10000],
                    ['name' => 'Pedicure', 'unit_label' => 'per service', 'default_price' => 12000],
                ],
            ],
        ],
    ],
    'document_services' => [
        'label' => 'Document & Secretarial',
        'icon' => 'fa-file-text',
        'categories' => [
            [
                'name' => 'Documents',
                'services' => [
                    ['name' => 'Affidavit / Statutory', 'unit_label' => 'per document', 'default_price' => 15000],
                    ['name' => 'Letter Typing', 'unit_label' => 'per page', 'default_price' => 500],
                    ['name' => 'Certified Copy', 'unit_label' => 'per page', 'default_price' => 1000],
                ],
            ],
        ],
    ],
    'auto_garage_services' => [
        'label' => 'Garage Labour',
        'icon' => 'fa-wrench',
        'categories' => [
            [
                'name' => 'Labour',
                'services' => [
                    ['name' => 'Oil Change Service', 'unit_label' => 'per job', 'default_price' => 15000],
                    ['name' => 'Wheel Alignment', 'unit_label' => 'per job', 'default_price' => 25000],
                    ['name' => 'Diagnostic', 'unit_label' => 'per job', 'default_price' => 20000],
                ],
            ],
        ],
    ],
];

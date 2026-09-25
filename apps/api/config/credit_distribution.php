<?php

// Distribution policy data, not financial-engine constants. Dated, scoped rules
// recorded through platform administration can supersede these defaults.
return [
    'channels' => ['web', 'android', 'play_store', 'app_store', 'huawei_appgallery', 'whatsapp', 'ussd'],
    'aliases' => ['android' => 'play_store'],
    'store_channels' => ['android', 'play_store', 'app_store', 'huawei_appgallery'],
    'enabled_countries' => array_values(array_filter(explode(',', env('OPFIN_CREDIT_ENABLED_COUNTRIES', 'UG')))),
    'defaults' => [
        'play_store' => [
            'personal_loan' => ['availability' => 'available', 'min_duration_days' => 61, 'max_apr_percent' => null, 'version' => 'google-personal-loans-2026-09-25', 'source_url' => 'https://support.google.com/googleplay/android-developer/answer/9876821', 'reason' => 'Google Play personal-loan distribution requirements.'],
            '*' => ['availability' => 'review', 'reason' => 'Review this product classification against Google Play requirements.'],
        ],
        'app_store' => [
            'personal_loan' => ['availability' => 'available', 'min_duration_days' => 61, 'max_apr_percent' => 36, 'version' => 'apple-3.2.2-ix-2026-09-25', 'source_url' => 'https://developer.apple.com/app-store/review/guidelines/#business', 'reason' => 'Apple App Store personal-loan distribution requirements.'],
            '*' => ['availability' => 'review', 'reason' => 'Review this product classification against Apple App Store requirements.'],
        ],
        'huawei_appgallery' => [
            '*' => ['availability' => 'review', 'reason' => 'Record the applicable Huawei AppGallery product and market review before publication.'],
        ],
    ],
];

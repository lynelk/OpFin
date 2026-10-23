<?php

/**
 * Release candidate isolation: production rollout requires separate legal,
 * privacy, regulatory, funding, employer and operational acceptance.
 * A clean build is not an entitlement or licence to operate these modules.
 */
return [
    'enabled' => filter_var(
        env('OPFIN_CORE_RELEASE_ENABLED', env('APP_ENV', 'production') === 'testing'),
        FILTER_VALIDATE_BOOL
    ),
];

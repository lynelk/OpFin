<?php

return [
    // Supplied by the deployment platform, not a request or a mutable client header.
    'source_revision' => env('RAILWAY_GIT_COMMIT_SHA'),
];

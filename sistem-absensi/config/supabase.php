<?php

return [
    'url' => env('SUPABASE_URL', null),
    'key' => env('SUPABASE_KEY', null),
    'bucket' => env('SUPABASE_BUCKET', 'profile-images'),
    'assets_bucket' => env('SUPABASE_ASSETS_BUCKET', 'company-assets'),
    'ca_bundle' => env('SUPABASE_CA_BUNDLE'),
];

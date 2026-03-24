<?php

return [
    'default_slug' => env('TENANCY_DEFAULT_SLUG', 'liveequipamentos'),
    'allow_query_parameter' => (bool) env('TENANCY_ALLOW_QUERY_PARAMETER', false),
    'query_parameter' => env('TENANCY_QUERY_PARAMETER', 'tenant'),
];


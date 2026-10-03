<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Proxies whose X-Forwarded-* headers are honoured: "*" for the calling
    | proxy, or a comma separated list of addresses / CIDR ranges. Leave it
    | unset on Laravel Cloud, which the framework already trusts.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];

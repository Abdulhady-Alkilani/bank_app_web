<?php

use App\Providers\AppServiceProvider;
use App\Providers\CustomerPortalServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\ScoringServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    ScoringServiceProvider::class,
    CustomerPortalServiceProvider::class,
];

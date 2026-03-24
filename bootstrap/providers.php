<?php

use App\Modules\Auth\Providers\AuthServiceProvider;
use App\Modules\Dashboard\Providers\DashboardServiceProvider;
use App\Modules\Docs\Providers\DocsServiceProvider;
use App\Modules\Pharmacy\Providers\PharmacyServiceProvider;
use App\Modules\Reporting\Providers\ReportingServiceProvider;
use App\Modules\Sales\Providers\SalesServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    DocsServiceProvider::class,
    PharmacyServiceProvider::class,
    ReportingServiceProvider::class,
    SalesServiceProvider::class,
    DashboardServiceProvider::class,
];

<?php

namespace App\Modules\Pharmacy\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Pharmacy\Repositories\Interfaces\PharmacyRepositoryInterface;
use App\Modules\Pharmacy\Repositories\PharmacyRepo;

class PharmacyServiceProvider extends BaseModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PharmacyRepositoryInterface::class, PharmacyRepo::class);
    }

    public function boot(): void
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}

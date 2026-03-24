<?php

namespace App\Modules\Sales\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Sales\Repositories\Interfaces\SalesRepositoryInterface;
use App\Modules\Sales\Repositories\SalesRepo;

class SalesServiceProvider extends BaseModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SalesRepositoryInterface::class, SalesRepo::class);
    }

    public function boot(): void
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}

<?php

namespace App\Modules\Dashboard\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;

class DashboardServiceProvider extends BaseModuleServiceProvider
{
    public function boot(): void
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}

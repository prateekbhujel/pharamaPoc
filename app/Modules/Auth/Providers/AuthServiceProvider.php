<?php

namespace App\Modules\Auth\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;

class AuthServiceProvider extends BaseModuleServiceProvider
{
    public function boot(): void
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}

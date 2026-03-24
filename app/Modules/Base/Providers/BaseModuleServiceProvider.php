<?php

namespace App\Modules\Base\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

abstract class BaseModuleServiceProvider extends ServiceProvider
{
    protected function loadModuleRoutes(string $modulePath): void
    {
        if (file_exists($modulePath.'/Routes/api.php')) {
            Route::middleware('web')->group(function () use ($modulePath): void {
                $this->loadRoutesFrom($modulePath.'/Routes/api.php');
            });
        }

        if (file_exists($modulePath.'/Routes/web.php')) {
            $this->loadRoutesFrom($modulePath.'/Routes/web.php');
        }
    }
}

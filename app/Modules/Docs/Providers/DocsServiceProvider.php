<?php

namespace App\Modules\Docs\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Docs\Console\GenerateSwaggerCommand;

class DocsServiceProvider extends BaseModuleServiceProvider
{
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateSwaggerCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'docs');
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}

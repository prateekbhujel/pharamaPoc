<?php

namespace App\Modules\Reporting\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Reporting\Repositories\Interfaces\ReportExportRepositoryInterface;
use App\Modules\Reporting\Repositories\Interfaces\ReportingRepositoryInterface;
use App\Modules\Reporting\Repositories\ReportExportRepo;
use App\Modules\Reporting\Repositories\ReportingRepo;

class ReportingServiceProvider extends BaseModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReportExportRepositoryInterface::class, ReportExportRepo::class);
        $this->app->bind(ReportingRepositoryInterface::class, ReportingRepo::class);
    }

    public function boot(): void
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}

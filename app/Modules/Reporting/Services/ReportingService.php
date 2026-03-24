<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Auth\Services\UserScopeService;
use App\Modules\Reporting\Jobs\GenerateReportExport;
use App\Modules\Reporting\Models\ReportExport;
use App\Modules\Reporting\Repositories\Interfaces\ReportExportRepositoryInterface;
use App\Modules\Reporting\Repositories\Interfaces\ReportingRepositoryInterface;
use App\Modules\User\Models\User;
use App\Support\SalesReportFilters;
use Illuminate\Support\Facades\Storage;

class ReportingService
{
    public function __construct(
        private readonly ReportingRepositoryInterface $reportingRepository,
        private readonly ReportExportRepositoryInterface $reportExportRepository,
        private readonly UserScopeService $userScopeService,
    ) {}

    public function dashboardData(User $user): array
    {
        $scope = $this->userScopeService->dashboardScope($user);

        return [
            'filters' => $this->reportingRepository->getDashboardFilters($scope['organization_id'], $scope['hospital_id']),
            'stats' => $this->reportingRepository->getDashboardStats($scope['organization_id'], $scope['hospital_id']),
            'recent_exports' => $this->reportExportRepository
                ->recent(40)
                ->filter(fn (ReportExport $export): bool => $this->userScopeService->canViewExport($user, $export))
                ->take(8)
                ->values(),
        ];
    }

    public function preview(User $user, array $filters, array $pagination): array
    {
        return $this->reportingRepository->preview(
            $this->userScopeService->scopeReportFilters($user, $filters),
            $pagination
        );
    }

    public function countRows(User $user, array $filters): int
    {
        return $this->reportingRepository->countRows(
            $this->userScopeService->scopeReportFilters($user, $filters)
        );
    }

    public function scopedFilters(User $user, array $filters): array
    {
        return $this->userScopeService->scopeReportFilters($user, $filters);
    }

    public function exportCsvToPath(User $user, array $filters, string $absolutePath): void
    {
        $this->reportingRepository->exportCsvToPath(
            $this->userScopeService->scopeReportFilters($user, $filters),
            $absolutePath
        );
    }

    public function createOrReuseExport(User $user, array $filters, string $format): ReportExport
    {
        $filters = $this->userScopeService->scopeReportFilters($user, $filters);
        $hash = SalesReportFilters::hashForExport($filters, $format);

        $activeExport = $this->reportExportRepository->findActiveByHash($hash);

        if ($activeExport) {
            return $activeExport;
        }

        $cachedExport = $this->reportExportRepository->findCompletedByHashWithin(
            $hash,
            now()->subMinutes(config('reporting.cache_minutes'))
        );

        if (
            $cachedExport &&
            $cachedExport->file_path &&
            Storage::disk(config('reporting.exports_disk'))->exists($cachedExport->file_path)
        ) {
            return $cachedExport;
        }

        $export = $this->reportExportRepository->createPending($filters, $format, $hash);

        $totalRows = $this->reportingRepository->countRows($filters);

        if ($format === 'csv' && $totalRows <= (int) config('reporting.instant_csv_row_limit')) {
            GenerateReportExport::dispatchSync($export->id);

            return $this->reportExportRepository->findByIdOrFail($export->id);
        }

        GenerateReportExport::dispatch($export->id)
            ->onConnection(config('reporting.export_connection'))
            ->onQueue('exports');

        return $export;
    }

    public function findExportOrFail(User $user, string $publicId): ReportExport
    {
        $export = $this->reportExportRepository->findByPublicIdOrFail($publicId);

        abort_unless($this->userScopeService->canViewExport($user, $export), 404);

        return $export;
    }
}

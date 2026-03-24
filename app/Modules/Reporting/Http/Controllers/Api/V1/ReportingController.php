<?php

namespace App\Modules\Reporting\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Http\Requests\ExportReportRequest;
use App\Modules\Reporting\Http\Requests\PreviewReportRequest;
use App\Modules\Reporting\Http\Requests\StoreReportExportRequest;
use App\Modules\Reporting\Http\Resources\DashboardResource;
use App\Modules\Reporting\Http\Resources\PreviewResource;
use App\Modules\Reporting\Http\Resources\ReportExportResource;
use App\Modules\Reporting\Models\ReportExport;
use App\Modules\Reporting\Services\ReportingService;
use App\Modules\Reporting\Services\WorkbookService;
use App\Support\ExportFormatDecider;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportingController extends Controller
{
    public function __construct(
        private readonly ReportingService $reporting,
        private readonly WorkbookService $workbookService,
    ) {}

    public function options(): JsonResponse
    {
        return DashboardResource::make($this->reporting->dashboardData(request()->user()))->response();
    }

    public function preview(PreviewReportRequest $request): JsonResponse
    {
        return PreviewResource::make(
            $this->reporting->preview(
                $request->user(),
                $request->reportFilters(),
                $request->pagination()
            )
        )->response();
    }

    public function store(StoreReportExportRequest $request): JsonResponse
    {
        $export = $this->reporting->createOrReuseExport(
            $request->user(),
            $request->reportFilters(),
            $request->validated('format')
        );

        return ReportExportResource::make($export)
            ->response()
            ->setStatusCode($export->status === ReportExport::COMPLETED ? 200 : 202);
    }

    public function directDownload(ExportReportRequest $request): BinaryFileResponse|StreamedResponse
    {
        $scopedFilters = $this->reporting->scopedFilters($request->user(), $request->reportFilters());
        $requestedFormat = $request->validated('format');
        $rows = $this->reporting->countRows($request->user(), $request->reportFilters());
        $formatDecision = ExportFormatDecider::resolveDirectDownload($requestedFormat, $rows);
        $format = $formatDecision['actual_format'];
        $timestamp = now('Asia/Kathmandu')->format('Ymd-His');

        if ($format === 'xlsx') {
            $directory = storage_path('app/temp-exports');

            if (! is_dir($directory)) {
                File::ensureDirectoryExists($directory);
            }

            $csvPath = $directory.'/report-source-'.$timestamp.'-'.uniqid('', true).'.csv';
            $xlsxPath = $directory.'/report-'.$timestamp.'-'.uniqid('', true).'.xlsx';

            try {
                $this->reporting->exportCsvToPath($request->user(), $request->reportFilters(), $csvPath);
                $this->workbookService->convertCsvToXlsx($csvPath, $xlsxPath);
            } finally {
                if (is_file($csvPath)) {
                    @unlink($csvPath);
                }
            }

            return response()->download(
                $xlsxPath,
                "pharamaPOC-report-{$timestamp}.xlsx",
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'X-PharamaPOC-Requested-Format' => $requestedFormat,
                    'X-PharamaPOC-Actual-Format' => 'xlsx',
                ]
            )->deleteFileAfterSend(true);
        }

        $directory = storage_path('app/temp-exports');

        if (! is_dir($directory)) {
            File::ensureDirectoryExists($directory);
        }

        $absolutePath = $directory.'/report-'.$timestamp.'-'.uniqid('', true).'.csv';
        $this->reporting->exportCsvToPath($request->user(), $request->reportFilters(), $absolutePath);

        return response()->download(
            $absolutePath,
            "pharamaPOC-report-{$timestamp}.csv",
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'X-PharamaPOC-Requested-Format' => $requestedFormat,
                'X-PharamaPOC-Actual-Format' => 'csv',
            ]
        )->deleteFileAfterSend(true);
    }

    public function show(string $publicId): JsonResponse
    {
        return ReportExportResource::make(
            $this->reporting->findExportOrFail(request()->user(), $publicId)
        )->response();
    }

    public function download(string $publicId): StreamedResponse
    {
        $export = $this->reporting->findExportOrFail(request()->user(), $publicId);

        abort_unless($export->status === ReportExport::COMPLETED && $export->file_path, 409, 'This export is not ready to download yet.');

        $diskName = config('reporting.exports_disk');
        abort_unless(Storage::disk($diskName)->exists($export->file_path), 404, 'The generated export file could not be found.');

        return Storage::disk($diskName)->download(
            $export->file_path,
            $export->file_name,
            [
                'Content-Type' => $export->format === 'xlsx'
                    ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                    : 'text/csv; charset=UTF-8',
            ]
        );
    }
}

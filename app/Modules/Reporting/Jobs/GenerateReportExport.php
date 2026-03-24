<?php

namespace App\Modules\Reporting\Jobs;

use App\Modules\Reporting\Models\ReportExport;
use App\Modules\Reporting\Repositories\Interfaces\ReportExportRepositoryInterface;
use App\Modules\Reporting\Repositories\Interfaces\ReportingRepositoryInterface;
use App\Modules\Reporting\Services\WorkbookService;
use App\Support\SalesReportFilters;
use App\Support\SalesReportRowFormatter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateReportExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public readonly int $reportExportId,
    ) {}

    public function handle(
        ReportingRepositoryInterface $reportingRepository,
        ReportExportRepositoryInterface $reportExportRepository,
        WorkbookService $workbookService,
    ): void {
        $export = $reportExportRepository->findByIdOrFail($this->reportExportId);

        if (
            $export->status === ReportExport::COMPLETED &&
            $export->file_path &&
            Storage::disk(config('reporting.exports_disk'))->exists($export->file_path)
        ) {
            return;
        }

        $export->forceFill([
            'status' => ReportExport::PROCESSING,
            'progress' => 5,
            'started_at' => now(),
            'finished_at' => null,
            'error_message' => null,
        ]);
        $reportExportRepository->save($export);

        $filters = $export->filters ?? [];
        $totalRows = $reportingRepository->countRows($filters);

        $export->forceFill([
            'requested_rows' => $totalRows,
            'metrics' => [
                'strategy' => $export->format === 'xlsx' ? 'xlsx-stream-from-copy' : 'csv-postgres-copy',
                'uses_materialized_view' => true,
                'phase' => 'counted',
            ],
        ]);
        $reportExportRepository->save($export);

        if ($export->format === 'xlsx') {
            $this->storeXlsx($export, $filters, $totalRows, $reportingRepository, $reportExportRepository, $workbookService);

            return;
        }

        $this->storeCsv($export, $filters, $totalRows, $reportingRepository, $reportExportRepository);
    }

    public function failed(Throwable $exception): void
    {
        app(ReportExportRepositoryInterface::class)->markFailed($this->reportExportId, $exception->getMessage());
    }

    private function storeCsv(
        ReportExport $export,
        array $filters,
        int $totalRows,
        ReportingRepositoryInterface $reportingRepository,
        ReportExportRepositoryInterface $reportExportRepository,
    ): void {
        $diskName = config('reporting.exports_disk');
        $disk = Storage::disk($diskName);
        $directory = 'exports/'.now()->format('Y/m/d');
        $disk->makeDirectory($directory);

        $path = $directory.'/'.$export->public_id.'.csv';
        $absolutePath = $disk->path($path);
        $startedAt = microtime(true);

        try {
            $export->forceFill([
                'progress' => 40,
                'metrics' => array_merge($export->metrics ?? [], [
                    'phase' => 'extracting',
                ]),
            ]);
            $reportExportRepository->save($export);

            $reportingRepository->exportCsvToPath($filters, $absolutePath);
            clearstatcache(true, $absolutePath);

            $export->forceFill([
                'status' => ReportExport::COMPLETED,
                'progress' => 100,
                'exported_rows' => $totalRows,
                'file_path' => $path,
                'file_name' => 'pharamaPOC-report-'.$export->public_id.'.csv',
                'finished_at' => now(),
                'metrics' => array_merge($export->metrics ?? [], [
                    'strategy' => 'csv-postgres-copy',
                    'rows_written' => $totalRows,
                    'bytes' => filesize($absolutePath) ?: 0,
                    'disk' => $diskName,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'phase' => 'ready',
                ]),
            ]);
            $reportExportRepository->save($export);

            return;
        } catch (Throwable $exception) {
            report($exception);
        }

        $this->storeCsvFallback($export, $filters, $totalRows, $path, $absolutePath, $diskName, $startedAt, $reportExportRepository);
    }

    private function storeCsvFallback(
        ReportExport $export,
        array $filters,
        int $totalRows,
        string $path,
        string $absolutePath,
        string $diskName,
        float $startedAt,
        ReportExportRepositoryInterface $reportExportRepository,
    ): void {
        $handle = fopen($absolutePath, 'wb');

        if (! $handle) {
            throw new RuntimeException('Unable to open the CSV export file for writing.');
        }

        fputcsv($handle, SalesReportRowFormatter::headings());

        $processedRows = 0;
        $progressEvery = max(1, (int) config('reporting.progress_update_every'));

        $rows = SalesReportFilters::baseQuery($filters)
            ->orderBy('sale_item_id')
            ->lazyById(config('reporting.csv_chunk_size'), 'sale_item_id');

        foreach ($rows as $row) {
            fputcsv($handle, SalesReportRowFormatter::toArray($row));
            $processedRows++;

            if ($processedRows % $progressEvery === 0) {
                $export->forceFill([
                    'progress' => $this->progressFor($processedRows, $totalRows),
                    'exported_rows' => $processedRows,
                ]);
                $reportExportRepository->save($export);
            }
        }

        fclose($handle);
        clearstatcache(true, $absolutePath);

        $export->forceFill([
            'status' => ReportExport::COMPLETED,
            'progress' => 100,
            'exported_rows' => $processedRows,
            'file_path' => $path,
            'file_name' => 'pharamaPOC-report-'.$export->public_id.'.csv',
            'finished_at' => now(),
            'metrics' => array_merge($export->metrics ?? [], [
                'strategy' => 'csv-stream-fallback',
                'rows_written' => $processedRows,
                'bytes' => filesize($absolutePath) ?: 0,
                'disk' => $diskName,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'phase' => 'ready',
            ]),
        ]);
        $reportExportRepository->save($export);
    }

    private function storeXlsx(
        ReportExport $export,
        array $filters,
        int $totalRows,
        ReportingRepositoryInterface $reportingRepository,
        ReportExportRepositoryInterface $reportExportRepository,
        WorkbookService $workbookService,
    ): void {
        $diskName = config('reporting.exports_disk');
        $disk = Storage::disk($diskName);
        $directory = 'exports/'.now()->format('Y/m/d');
        $disk->makeDirectory($directory);
        $path = $directory.'/'.$export->public_id.'.xlsx';
        $absolutePath = $disk->path($path);
        $tempDirectory = storage_path('app/temp-exports');
        File::ensureDirectoryExists($tempDirectory);
        $tempCsvPath = $tempDirectory.'/report-'.$export->public_id.'.csv';
        $startedAt = microtime(true);

        $export->forceFill([
            'progress' => 12,
            'metrics' => array_merge($export->metrics ?? [], [
                'phase' => 'extracting',
            ]),
        ]);
        $reportExportRepository->save($export);

        try {
            $reportingRepository->exportCsvToPath($filters, $tempCsvPath);

            $export->forceFill([
                'progress' => 58,
                'metrics' => array_merge($export->metrics ?? [], [
                    'phase' => 'building-workbook',
                ]),
            ]);
            $reportExportRepository->save($export);

            $progressEvery = max(1, (int) config('reporting.progress_update_every'));

            $result = $workbookService->convertCsvToXlsx(
                $tempCsvPath,
                $absolutePath,
                function (int $processedRows) use ($export, $totalRows, $progressEvery, $reportExportRepository): void {
                    if ($processedRows % $progressEvery !== 0) {
                        return;
                    }

                    $export->forceFill([
                        'progress' => $this->xlsxProgressFor($processedRows, $totalRows),
                        'exported_rows' => $processedRows,
                        'metrics' => array_merge($export->metrics ?? [], [
                            'phase' => 'building-workbook',
                        ]),
                    ]);
                    $reportExportRepository->save($export);
                }
            );

            clearstatcache(true, $absolutePath);

            $export->forceFill([
                'status' => ReportExport::COMPLETED,
                'progress' => 100,
                'exported_rows' => $result['rows_written'],
                'file_path' => $path,
                'file_name' => 'pharamaPOC-report-'.$export->public_id.'.xlsx',
                'finished_at' => now(),
                'metrics' => array_merge($export->metrics ?? [], [
                    'strategy' => 'xlsx-stream-from-copy',
                    'rows_written' => $result['rows_written'],
                    'sheet_count' => $result['sheet_count'],
                    'bytes' => filesize($absolutePath) ?: 0,
                    'disk' => $diskName,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'phase' => 'ready',
                ]),
            ]);
            $reportExportRepository->save($export);
        } finally {
            if (is_file($tempCsvPath)) {
                @unlink($tempCsvPath);
            }
        }
    }

    private function progressFor(int $processedRows, int $totalRows): int
    {
        if ($totalRows === 0) {
            return 95;
        }

        return min(95, 10 + (int) floor(($processedRows / max(1, $totalRows)) * 85));
    }

    private function xlsxProgressFor(int $processedRows, int $totalRows): int
    {
        if ($totalRows === 0) {
            return 95;
        }

        return min(95, 58 + (int) floor(($processedRows / max(1, $totalRows)) * 37));
    }
}

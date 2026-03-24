<?php

namespace App\Console\Commands;

use App\Modules\Reporting\Repositories\Interfaces\ReportingRepositoryInterface;
use App\Modules\Reporting\Services\WorkbookService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BenchmarkReportWorkbook extends Command
{
    protected $signature = 'reporting:benchmark-workbook
        {--date-from=2011-01-01 : Start date for the benchmark window}
        {--date-to=2026-12-31 : End date for the benchmark window}
        {--tenant= : Filter by organization id}
        {--hospital= : Filter by hospital id}
        {--pharmacy= : Filter by pharmacy id}';

    protected $description = 'Benchmark the Postgres COPY + streaming XLSX report export pipeline';

    public function handle(
        ReportingRepositoryInterface $reportingRepository,
        WorkbookService $workbookService,
    ): int {
        $filters = array_filter([
            'date_from' => (string) $this->option('date-from'),
            'date_to' => (string) $this->option('date-to'),
            'tenant_id' => $this->option('tenant') !== null ? (int) $this->option('tenant') : null,
            'hospital_id' => $this->option('hospital') !== null ? (int) $this->option('hospital') : null,
            'pharmacy_id' => $this->option('pharmacy') !== null ? (int) $this->option('pharmacy') : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $directory = storage_path('app/benchmarks');
        File::ensureDirectoryExists($directory);

        $csvPath = $directory.'/report-source-'.uniqid('', true).'.csv';
        $xlsxPath = $directory.'/report-workbook-'.uniqid('', true).'.xlsx';
        $rows = $reportingRepository->countRows($filters);

        $copyStartedAt = microtime(true);
        $reportingRepository->exportCsvToPath($filters, $csvPath);
        $copyDurationMs = (int) round((microtime(true) - $copyStartedAt) * 1000);
        $csvBytes = filesize($csvPath) ?: 0;

        $xlsxStartedAt = microtime(true);
        $result = $workbookService->convertCsvToXlsx($csvPath, $xlsxPath);
        $xlsxDurationMs = (int) round((microtime(true) - $xlsxStartedAt) * 1000);
        $xlsxBytes = filesize($xlsxPath) ?: 0;

        File::delete([$csvPath, $xlsxPath]);

        $this->table(
            ['Stage', 'Rows', 'Bytes', 'Duration (ms)', 'Rows / sec', 'Sheets'],
            [
                [
                    'COPY CSV',
                    number_format($rows),
                    number_format($csvBytes),
                    number_format($copyDurationMs),
                    number_format($this->rowsPerSecond($rows, $copyDurationMs)),
                    '-',
                ],
                [
                    'CSV -> XLSX',
                    number_format($result['rows_written']),
                    number_format($xlsxBytes),
                    number_format($xlsxDurationMs),
                    number_format($this->rowsPerSecond($result['rows_written'], $xlsxDurationMs)),
                    number_format($result['sheet_count']),
                ],
            ]
        );

        return self::SUCCESS;
    }

    private function rowsPerSecond(int $rows, int $durationMs): int
    {
        if ($durationMs <= 0) {
            return 0;
        }

        return (int) round($rows / ($durationMs / 1000));
    }
}

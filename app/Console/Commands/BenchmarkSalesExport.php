<?php

namespace App\Console\Commands;

use App\Modules\Reporting\Repositories\Interfaces\ReportingRepositoryInterface;
use App\Modules\Sales\Repositories\Interfaces\SalesRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BenchmarkSalesExport extends Command
{
    protected $signature = 'sales:benchmark-export
        {--date-from=2011-01-01 : Start date for the benchmark window}
        {--date-to=2026-12-31 : End date for the benchmark window}
        {--tenant= : Filter by organization id}
        {--hospital= : Filter by hospital id}
        {--pharmacy= : Filter by pharmacy id}';

    protected $description = 'Benchmark the direct CSV export paths for report and sales data';

    public function handle(
        ReportingRepositoryInterface $reportingRepository,
        SalesRepositoryInterface $salesRepository,
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

        $reportPath = $directory.'/report-benchmark-'.uniqid('', true).'.csv';
        $salesPath = $directory.'/sales-benchmark-'.uniqid('', true).'.csv';

        $reportRows = $reportingRepository->countRows($filters);
        $salesRows = $salesRepository->countRows($filters);

        $reportStartedAt = microtime(true);
        $reportingRepository->exportCsvToPath($filters, $reportPath);
        $reportDurationMs = (int) round((microtime(true) - $reportStartedAt) * 1000);
        $reportBytes = filesize($reportPath) ?: 0;

        $salesStartedAt = microtime(true);
        $salesRepository->exportCsvToPath($filters, $salesPath);
        $salesDurationMs = (int) round((microtime(true) - $salesStartedAt) * 1000);
        $salesBytes = filesize($salesPath) ?: 0;

        File::delete([$reportPath, $salesPath]);

        $this->table(
            ['Lane', 'Rows', 'Bytes', 'Duration (ms)', 'Rows / sec'],
            [
                [
                    'Report CSV',
                    number_format($reportRows),
                    number_format($reportBytes),
                    number_format($reportDurationMs),
                    number_format($this->rowsPerSecond($reportRows, $reportDurationMs)),
                ],
                [
                    'Sales CSV',
                    number_format($salesRows),
                    number_format($salesBytes),
                    number_format($salesDurationMs),
                    number_format($this->rowsPerSecond($salesRows, $salesDurationMs)),
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

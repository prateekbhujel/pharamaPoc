<?php

namespace App\Modules\Sales\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Services\WorkbookService;
use App\Modules\Sales\Http\Requests\ExportSaleRequest;
use App\Modules\Sales\Http\Requests\IndexSaleRequest;
use App\Modules\Sales\Http\Requests\StoreSaleRequest;
use App\Modules\Sales\Http\Requests\UpdateSaleRequest;
use App\Modules\Sales\Http\Resources\SaleListResource;
use App\Modules\Sales\Http\Resources\SaleResource;
use App\Modules\Sales\Services\SalesService;
use App\Support\ExportFormatDecider;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesController extends Controller
{
    public function __construct(
        private readonly SalesService $sales,
        private readonly WorkbookService $workbookService,
    ) {}

    public function index(IndexSaleRequest $request): JsonResponse
    {
        return SaleListResource::make(
            $this->sales->paginate(
                $request->user(),
                $request->filters()
            )
        )->response();
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        return SaleResource::make(
            $this->sales->create(
                $request->user(),
                $request->validated()
            )
        )->response()->setStatusCode(201);
    }

    public function update(UpdateSaleRequest $request, int $saleItemId): JsonResponse
    {
        return SaleResource::make(
            $this->sales->update(
                $request->user(),
                $saleItemId,
                $request->validated()
            )
        )->response();
    }

    public function destroy(int $saleItemId): JsonResponse
    {
        $this->sales->delete(request()->user(), $saleItemId);

        return response()->json([
            'message' => 'The sale was deleted successfully.',
        ]);
    }

    public function export(ExportSaleRequest $request): BinaryFileResponse|StreamedResponse
    {
        $filters = $this->sales->scopedFilters($request->user(), $request->exportFilters());
        $requestedFormat = $request->validated('format');
        $rows = $this->sales->countRows($request->user(), $request->exportFilters());
        $formatDecision = ExportFormatDecider::resolveDirectDownload($requestedFormat, $rows);
        $format = $formatDecision['actual_format'];
        $timestamp = now('Asia/Kathmandu')->format('Ymd-His');

        if ($format === 'xlsx') {
            $directory = storage_path('app/temp-exports');

            if (! is_dir($directory)) {
                File::ensureDirectoryExists($directory);
            }

            $csvPath = $directory.'/sales-source-'.$timestamp.'-'.uniqid('', true).'.csv';
            $xlsxPath = $directory.'/sales-'.$timestamp.'-'.uniqid('', true).'.xlsx';

            try {
                $this->sales->exportCsvToPath($request->user(), $request->exportFilters(), $csvPath);
                $this->workbookService->convertCsvToXlsx($csvPath, $xlsxPath);
            } finally {
                if (is_file($csvPath)) {
                    @unlink($csvPath);
                }
            }

            return response()->download(
                $xlsxPath,
                "pharamaPOC-sales-{$timestamp}.xlsx",
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

        $absolutePath = $directory.'/sales-'.$timestamp.'-'.uniqid('', true).'.csv';
        $this->sales->exportCsvToPath($request->user(), $request->exportFilters(), $absolutePath);

        return response()->download(
            $absolutePath,
            "pharamaPOC-sales-{$timestamp}.csv",
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'X-PharamaPOC-Requested-Format' => $requestedFormat,
                'X-PharamaPOC-Actual-Format' => 'csv',
            ]
        )->deleteFileAfterSend(true);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'invoice_number',
                'sold_at',
                'pharmacy_id',
                'medicine_id',
                'payment_method',
                'payment_status',
                'batch_number',
                'quantity',
                'unit_price',
                'discount_amount',
                'tax_amount',
                'expires_at',
            ]);

            fputcsv($handle, [
                'INV-SAMPLE-1001',
                CarbonImmutable::now('Asia/Kathmandu')->format('Y-m-d H:i:s'),
                1,
                1,
                'cash',
                'paid',
                'B-SAMPLE-01',
                2,
                145.50,
                5.00,
                36.53,
                CarbonImmutable::now()->addMonths(12)->toDateString(),
            ]);

            fclose($handle);
        }, 'pharamaPOC-sales-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

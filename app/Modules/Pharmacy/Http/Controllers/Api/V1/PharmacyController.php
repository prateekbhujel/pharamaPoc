<?php

namespace App\Modules\Pharmacy\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Pharmacy\Http\Requests\ExportPharmacyRequest;
use App\Modules\Pharmacy\Http\Requests\IndexPharmacyRequest;
use App\Modules\Pharmacy\Http\Requests\StorePharmacyRequest;
use App\Modules\Pharmacy\Http\Requests\UpdatePharmacyRequest;
use App\Modules\Pharmacy\Http\Resources\PharmacyListResource;
use App\Modules\Pharmacy\Http\Resources\PharmacyResource;
use App\Modules\Pharmacy\Services\PharmacyService;
use App\Modules\Reporting\Services\WorkbookService;
use App\Support\ExportFormatDecider;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PharmacyController extends Controller
{
    public function __construct(
        private readonly PharmacyService $pharmacies,
        private readonly WorkbookService $workbookService,
    ) {}

    public function index(IndexPharmacyRequest $request): JsonResponse
    {
        return PharmacyListResource::make(
            $this->pharmacies->paginate(
                $request->user(),
                $request->filters()
            )
        )->response();
    }

    public function store(StorePharmacyRequest $request): JsonResponse
    {
        return PharmacyResource::make(
            $this->pharmacies->create(
                $request->user(),
                $request->validated()
            )
        )->response()->setStatusCode(201);
    }

    public function update(UpdatePharmacyRequest $request, int $pharmacyId): JsonResponse
    {
        return PharmacyResource::make(
            $this->pharmacies->update(
                $request->user(),
                $pharmacyId,
                $request->validated()
            )
        )->response();
    }

    public function export(ExportPharmacyRequest $request): BinaryFileResponse|StreamedResponse
    {
        $filters = $this->pharmacies->scopedFilters($request->user(), $request->exportFilters());
        $requestedFormat = $request->validated('format');
        $rows = $this->pharmacies->countRows($request->user(), $request->exportFilters());
        $formatDecision = ExportFormatDecider::resolveDirectDownload($requestedFormat, $rows);
        $format = $formatDecision['actual_format'];
        $timestamp = now('Asia/Kathmandu')->format('Ymd-His');

        if ($format === 'xlsx') {
            $directory = storage_path('app/temp-exports');

            if (! is_dir($directory)) {
                File::ensureDirectoryExists($directory);
            }

            $csvPath = $directory.'/pharmacies-source-'.$timestamp.'-'.uniqid('', true).'.csv';
            $xlsxPath = $directory.'/pharmacies-'.$timestamp.'-'.uniqid('', true).'.xlsx';

            try {
                $this->pharmacies->exportCsvToPath($request->user(), $request->exportFilters(), $csvPath);
                $this->workbookService->convertCsvToXlsx($csvPath, $xlsxPath);
            } finally {
                if (is_file($csvPath)) {
                    @unlink($csvPath);
                }
            }

            return response()->download(
                $xlsxPath,
                "pharamaPOC-pharmacies-{$timestamp}.xlsx",
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

        $absolutePath = $directory.'/pharmacies-'.$timestamp.'-'.uniqid('', true).'.csv';
        $this->pharmacies->exportCsvToPath($request->user(), $request->exportFilters(), $absolutePath);

        return response()->download(
            $absolutePath,
            "pharamaPOC-pharmacies-{$timestamp}.csv",
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'X-PharamaPOC-Requested-Format' => $requestedFormat,
                'X-PharamaPOC-Actual-Format' => 'csv',
            ]
        )->deleteFileAfterSend(true);
    }

    public function destroy(int $pharmacyId): JsonResponse
    {
        $this->pharmacies->delete(request()->user(), $pharmacyId);

        return response()->json([
            'message' => 'The pharmacy was deleted successfully.',
        ]);
    }
}

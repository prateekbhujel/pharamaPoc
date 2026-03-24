<?php

namespace App\Modules\Sales\Services;

use App\Modules\Auth\Services\UserScopeService;
use App\Modules\Reporting\Repositories\Interfaces\ReportingRepositoryInterface;
use App\Modules\Sales\Repositories\Interfaces\SalesRepositoryInterface;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesService
{
    public function __construct(
        private readonly SalesRepositoryInterface $salesRepository,
        private readonly ReportingRepositoryInterface $reportingRepository,
        private readonly UserScopeService $userScopeService,
    ) {}

    public function paginate(User $user, array $filters): array
    {
        return $this->salesRepository->paginate(
            $this->userScopeService->scopeReportFilters($user, $filters)
        );
    }

    public function countRows(User $user, array $filters): int
    {
        return $this->salesRepository->countRows(
            $this->userScopeService->scopeReportFilters($user, $filters)
        );
    }

    public function create(User $user, array $data): array
    {
        $this->userScopeService->assertWritablePharmacy($user, $data['pharmacy_id']);
        $payload = $this->preparePayload($data);

        return DB::transaction(function () use ($payload): array {
            $sale = $this->salesRepository->create($payload);
            $this->reportingRepository->upsertOverlayForSaleItem($sale['sale_item_id']);

            return $this->salesRepository->findByIdOrFail($sale['sale_item_id']);
        });
    }

    public function update(User $user, int $saleItemId, array $data): array
    {
        $this->userScopeService->assertVisibleSaleItem($user, $saleItemId);
        $this->userScopeService->assertWritablePharmacy($user, $data['pharmacy_id']);
        $payload = $this->preparePayload($data);

        return DB::transaction(function () use ($saleItemId, $payload): array {
            $sale = $this->salesRepository->update($saleItemId, $payload);

            foreach ($this->salesRepository->saleItemIdsForSale($sale['sale_id']) as $relatedItemId) {
                $this->reportingRepository->upsertOverlayForSaleItem($relatedItemId);
            }

            return $this->salesRepository->findByIdOrFail($saleItemId);
        });
    }

    public function delete(User $user, int $saleItemId): void
    {
        $this->userScopeService->assertVisibleSaleItem($user, $saleItemId);

        DB::transaction(function () use ($saleItemId): void {
            $this->salesRepository->delete($saleItemId);
            $this->reportingRepository->markOverlayDeleted($saleItemId);
        });
    }

    public function scopedFilters(User $user, array $filters): array
    {
        return $this->userScopeService->scopeReportFilters($user, $filters);
    }

    public function exportCsvToPath(User $user, array $filters, string $absolutePath): void
    {
        $this->salesRepository->exportCsvToPath(
            $this->userScopeService->scopeReportFilters($user, $filters),
            $absolutePath
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function preparePayload(array $data): array
    {
        $quantity = max(1, (int) $data['quantity']);
        $unitPrice = round((float) $data['unit_price'], 2);
        $grossAmount = round($quantity * $unitPrice, 2);
        $discountAmount = round((float) ($data['discount_amount'] ?? 0), 2);
        $taxAmount = array_key_exists('tax_amount', $data)
            ? round((float) $data['tax_amount'], 2)
            : round(max(0, $grossAmount - $discountAmount) * 0.13, 2);
        $netAmount = round(max(0, $grossAmount - $discountAmount + $taxAmount), 2);

        return [
            'pharmacy_id' => (int) $data['pharmacy_id'],
            'patient_id' => $data['patient_id'] ?? null,
            'prescriber_id' => $data['prescriber_id'] ?? null,
            'medicine_id' => (int) $data['medicine_id'],
            'invoice_number' => trim((string) ($data['invoice_number'] ?? '')) !== ''
                ? trim((string) $data['invoice_number'])
                : 'INV-'.now('Asia/Kathmandu')->format('YmdHis').'-'.Str::upper(Str::random(4)),
            'payment_method' => (string) $data['payment_method'],
            'payment_status' => (string) $data['payment_status'],
            'sold_at' => CarbonImmutable::parse($data['sold_at'], 'Asia/Kathmandu')->toDateTimeString(),
            'batch_number' => trim((string) ($data['batch_number'] ?? '')) !== ''
                ? trim((string) $data['batch_number'])
                : 'B-'.Str::upper(Str::random(8)),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'gross_amount' => $grossAmount,
            'net_amount' => $netAmount,
            'expires_at' => ! empty($data['expires_at'])
                ? CarbonImmutable::parse($data['expires_at'])->toDateString()
                : null,
        ];
    }
}

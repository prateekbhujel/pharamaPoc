<?php

namespace App\Modules\Pharmacy\Services;

use App\Modules\Auth\Services\UserScopeService;
use App\Modules\Pharmacy\Repositories\Interfaces\PharmacyRepositoryInterface;
use App\Modules\Reporting\Repositories\Interfaces\ReportingRepositoryInterface;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PharmacyService
{
    public function __construct(
        private readonly PharmacyRepositoryInterface $pharmacyRepository,
        private readonly ReportingRepositoryInterface $reportingRepository,
        private readonly UserScopeService $userScopeService,
    ) {}

    public function paginate(User $user, array $filters): array
    {
        return $this->pharmacyRepository->paginate(
            $this->userScopeService->scopePharmacyFilters($user, $filters)
        );
    }

    public function countRows(User $user, array $filters): int
    {
        return $this->pharmacyRepository->countRows(
            $this->userScopeService->scopePharmacyFilters($user, $filters)
        );
    }

    public function scopedFilters(User $user, array $filters): array
    {
        return $this->userScopeService->scopePharmacyFilters($user, $filters);
    }

    public function exportCsvToPath(User $user, array $filters, string $absolutePath): void
    {
        $this->pharmacyRepository->exportCsvToPath(
            $this->userScopeService->scopePharmacyFilters($user, $filters),
            $absolutePath
        );
    }

    public function create(User $user, array $data): array
    {
        $this->userScopeService->assertWritableHospital($user, $data['hospital_id']);

        return DB::transaction(function () use ($data): array {
            $pharmacy = $this->pharmacyRepository->create($data);

            if ($data['seed_demo_sale'] ?? false) {
                $saleItemId = $this->pharmacyRepository->createDemoSale($pharmacy['id']);
                $this->reportingRepository->upsertOverlayForSaleItem($saleItemId);
                $pharmacy = $this->pharmacyRepository->findByIdOrFail($pharmacy['id']);
            }

            return $pharmacy;
        });
    }

    public function update(User $user, int $id, array $data): array
    {
        $this->userScopeService->assertVisiblePharmacy($user, $id);
        $this->userScopeService->assertWritableHospital($user, $data['hospital_id']);

        if (
            $this->pharmacyRepository->salesCount($id) > 0 &&
            $this->pharmacyRepository->hospitalId($id) !== $data['hospital_id']
        ) {
            throw ValidationException::withMessages([
                'hospital_id' => 'I block hospital reassignment once sales exist, because historical rows should stay tied to the original hospital.',
            ]);
        }

        return DB::transaction(function () use ($id, $data): array {
            $pharmacy = $this->pharmacyRepository->update($id, $data);

            if ($data['seed_demo_sale'] ?? false) {
                $saleItemId = $this->pharmacyRepository->createDemoSale($pharmacy['id']);
                $this->reportingRepository->upsertOverlayForSaleItem($saleItemId);
                $pharmacy = $this->pharmacyRepository->findByIdOrFail($pharmacy['id']);
            }

            return $pharmacy;
        });
    }

    public function delete(User $user, int $id): void
    {
        $this->userScopeService->assertVisiblePharmacy($user, $id);

        if ($this->pharmacyRepository->salesCount($id) > 0) {
            throw ValidationException::withMessages([
                'pharmacy' => 'I do not delete pharmacies that already have sales. Update the pharmacy instead so historical exports stay trustworthy.',
            ]);
        }

        DB::transaction(function () use ($id): void {
            DB::delete('DELETE FROM pharmacy_sale_export_overlays WHERE pharmacy_id = ?', [$id]);
            $this->pharmacyRepository->delete($id);
        });
    }
}

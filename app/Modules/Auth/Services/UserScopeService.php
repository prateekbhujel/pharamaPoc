<?php

namespace App\Modules\Auth\Services;

use App\Modules\Reporting\Models\ReportExport;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserScopeService
{
    /**
     * @return array{organization_id: int|null, hospital_id: int|null}
     */
    public function dashboardScope(User $user): array
    {
        return [
            'organization_id' => $user->isPlatformAdmin() ? null : $user->tenant_id,
            'hospital_id' => $user->isHospitalAdmin() ? $user->hospital_id : null,
        ];
    }

    public function scopeReportFilters(User $user, array $filters): array
    {
        $organizationId = $this->resolveOrganizationId($user, $filters['tenant_id'] ?? null);
        $hospitalId = $this->resolveHospitalId($user, $organizationId, $filters['hospital_id'] ?? null);

        return [
            ...$filters,
            'tenant_id' => $organizationId,
            'hospital_id' => $hospitalId,
            'pharmacy_id' => $this->resolvePharmacyId(
                $organizationId,
                $hospitalId,
                $filters['pharmacy_id'] ?? null
            ),
        ];
    }

    public function scopePharmacyFilters(User $user, array $filters): array
    {
        $organizationId = $this->resolveOrganizationId($user, $filters['tenant_id'] ?? null);

        return [
            ...$filters,
            'tenant_id' => $organizationId,
            'hospital_id' => $this->resolveHospitalId($user, $organizationId, $filters['hospital_id'] ?? null),
        ];
    }

    public function assertWritableHospital(User $user, int $hospitalId): void
    {
        $this->resolveHospitalId($user, $this->resolveOrganizationId($user, null), $hospitalId);
    }

    public function assertVisiblePharmacy(User $user, int $pharmacyId): void
    {
        $organizationId = $this->resolveOrganizationId($user, null);
        $hospitalId = $user->isHospitalAdmin() ? $this->resolveHospitalId($user, $organizationId, $user->hospital_id) : null;

        $this->resolvePharmacyId($organizationId, $hospitalId, $pharmacyId);
    }

    public function assertWritablePharmacy(User $user, int $pharmacyId): void
    {
        $organizationId = $this->resolveOrganizationId($user, null);
        $hospitalId = $user->isHospitalAdmin() ? $this->resolveHospitalId($user, $organizationId, $user->hospital_id) : null;
        $resolvedPharmacyId = $this->resolvePharmacyId($organizationId, $hospitalId, $pharmacyId);

        $resolvedHospitalId = DB::table('pharmacies')
            ->where('id', $resolvedPharmacyId)
            ->value('hospital_id');

        if (! $resolvedHospitalId) {
            throw ValidationException::withMessages([
                'pharmacy_id' => 'That pharmacy could not be found in the current scope.',
            ]);
        }

        $this->assertWritableHospital($user, (int) $resolvedHospitalId);
    }

    public function assertVisibleSaleItem(User $user, int $saleItemId): void
    {
        $organizationId = $this->resolveOrganizationId($user, null);
        $hospitalId = $user->isHospitalAdmin() ? $this->resolveHospitalId($user, $organizationId, $user->hospital_id) : null;

        $exists = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('pharmacies', 'pharmacies.id', '=', 'sales.pharmacy_id')
            ->join('hospitals', 'hospitals.id', '=', 'pharmacies.hospital_id')
            ->when($organizationId !== null, fn ($query) => $query->where('hospitals.tenant_id', $organizationId))
            ->when($hospitalId !== null, fn ($query) => $query->where('hospitals.id', $hospitalId))
            ->where('sale_items.id', $saleItemId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'sale' => 'That sale is outside the organization scope of the signed-in account.',
            ]);
        }
    }

    public function canViewExport(User $user, ReportExport $export): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        $filters = $export->filters ?? [];

        if ($user->isTenantAdmin()) {
            return (int) ($filters['tenant_id'] ?? 0) === (int) $user->tenant_id;
        }

        return (int) ($filters['hospital_id'] ?? 0) === (int) $user->hospital_id;
    }

    private function resolveOrganizationId(User $user, ?int $organizationId): ?int
    {
        if ($user->isPlatformAdmin()) {
            return $organizationId;
        }

        return $user->tenant_id;
    }

    private function resolveHospitalId(User $user, ?int $organizationId, ?int $hospitalId): ?int
    {
        if ($user->isHospitalAdmin()) {
            return $user->hospital_id;
        }

        if ($hospitalId === null) {
            return null;
        }

        $exists = DB::table('hospitals')
            ->when($organizationId !== null, fn ($query) => $query->where('tenant_id', $organizationId))
            ->where('id', $hospitalId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'hospital_id' => 'That hospital is outside the organization scope of the signed-in account.',
            ]);
        }

        return $hospitalId;
    }

    private function resolvePharmacyId(?int $organizationId, ?int $hospitalId, ?int $pharmacyId): ?int
    {
        if ($pharmacyId === null) {
            return null;
        }

        $exists = DB::table('pharmacies')
            ->join('hospitals', 'hospitals.id', '=', 'pharmacies.hospital_id')
            ->when($organizationId !== null, fn ($query) => $query->where('hospitals.tenant_id', $organizationId))
            ->when($hospitalId !== null, fn ($query) => $query->where('hospitals.id', $hospitalId))
            ->where('pharmacies.id', $pharmacyId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'pharmacy_id' => 'That pharmacy is outside the organization scope of the signed-in account.',
            ]);
        }

        return $pharmacyId;
    }
}

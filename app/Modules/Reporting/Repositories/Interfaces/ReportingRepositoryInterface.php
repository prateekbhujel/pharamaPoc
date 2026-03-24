<?php

namespace App\Modules\Reporting\Repositories\Interfaces;

interface ReportingRepositoryInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getDashboardFilters(?int $organizationId = null, ?int $hospitalId = null): array;

    public function getDashboardStats(?int $organizationId = null, ?int $hospitalId = null): array;

    public function preview(array $filters, array $pagination): array;

    public function countRows(array $filters): int;

    public function exportCsvToPath(array $filters, string $absolutePath): void;

    public function upsertOverlayForSaleItem(int $saleItemId): void;

    public function markOverlayDeleted(int $saleItemId): void;
}

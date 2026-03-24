<?php

namespace App\Modules\Sales\Repositories\Interfaces;

interface SalesRepositoryInterface
{
    public function paginate(array $filters): array;

    public function countRows(array $filters): int;

    public function findByIdOrFail(int $saleItemId): array;

    public function create(array $data): array;

    public function update(int $saleItemId, array $data): array;

    public function delete(int $saleItemId): void;

    public function saleIdForItem(int $saleItemId): int;

    public function saleItemIdsForSale(int $saleId): array;

    public function exportCsvToPath(array $filters, string $absolutePath): void;
}

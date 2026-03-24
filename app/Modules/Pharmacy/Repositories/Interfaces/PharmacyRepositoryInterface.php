<?php

namespace App\Modules\Pharmacy\Repositories\Interfaces;

interface PharmacyRepositoryInterface
{
    public function paginate(array $filters): array;

    public function countRows(array $filters): int;

    public function findByIdOrFail(int $id): array;

    public function create(array $data): array;

    public function update(int $id, array $data): array;

    public function delete(int $id): void;

    public function salesCount(int $id): int;

    public function hospitalId(int $id): int;

    public function createDemoSale(int $pharmacyId): int;

    public function exportCsvToPath(array $filters, string $absolutePath): void;
}

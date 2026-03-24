<?php

namespace App\Modules\Reporting\Repositories\Interfaces;

use App\Modules\Reporting\Models\ReportExport;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface ReportExportRepositoryInterface
{
    public function recent(int $limit = 8): Collection;

    public function findByIdOrFail(int $id): ReportExport;

    public function findByPublicIdOrFail(string $publicId): ReportExport;

    public function findActiveByHash(string $hash): ?ReportExport;

    public function findCompletedByHashWithin(string $hash, CarbonInterface $cutoff): ?ReportExport;

    public function createPending(array $filters, string $format, string $filterHash): ReportExport;

    public function save(ReportExport $export): ReportExport;

    public function markFailed(int $id, string $message): void;
}

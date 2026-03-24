<?php

namespace App\Modules\Reporting\Repositories;

use App\Modules\Reporting\Models\ReportExport;
use App\Modules\Reporting\Repositories\Interfaces\ReportExportRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ReportExportRepo implements ReportExportRepositoryInterface
{
    public function recent(int $limit = 8): Collection
    {
        return ReportExport::query()
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function findByIdOrFail(int $id): ReportExport
    {
        return ReportExport::query()->findOrFail($id);
    }

    public function findByPublicIdOrFail(string $publicId): ReportExport
    {
        return ReportExport::query()
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    public function findActiveByHash(string $hash): ?ReportExport
    {
        return ReportExport::query()
            ->where('filter_hash', $hash)
            ->whereIn('status', [ReportExport::PENDING, ReportExport::PROCESSING])
            ->latest('id')
            ->first();
    }

    public function findCompletedByHashWithin(string $hash, CarbonInterface $cutoff): ?ReportExport
    {
        return ReportExport::query()
            ->where('filter_hash', $hash)
            ->where('status', ReportExport::COMPLETED)
            ->where('created_at', '>=', $cutoff)
            ->latest('id')
            ->first();
    }

    public function createPending(array $filters, string $format, string $filterHash): ReportExport
    {
        return ReportExport::query()->create([
            'status' => ReportExport::PENDING,
            'format' => $format,
            'filter_hash' => $filterHash,
            'filters' => $filters,
            'progress' => 0,
        ]);
    }

    public function save(ReportExport $export): ReportExport
    {
        $export->save();

        return $export;
    }

    public function markFailed(int $id, string $message): void
    {
        ReportExport::query()
            ->whereKey($id)
            ->update([
                'status' => ReportExport::FAILED,
                'progress' => 100,
                'error_message' => $message,
                'finished_at' => now(),
            ]);
    }
}

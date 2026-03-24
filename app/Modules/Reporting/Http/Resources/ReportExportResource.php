<?php

namespace App\Modules\Reporting\Http\Resources;

use App\Modules\Reporting\Models\ReportExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReportExport
 */
class ReportExportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'status' => $this->status,
            'format' => $this->format,
            'progress' => $this->progress,
            'phase' => $this->metrics['phase'] ?? null,
            'requested_rows' => $this->requested_rows,
            'exported_rows' => $this->exported_rows,
            'filters' => $this->filters ?? [],
            'file_name' => $this->file_name,
            'download_url' => $this->status === ReportExport::COMPLETED
                ? route('reporting.exports.download', $this->public_id)
                : null,
            'error_message' => $this->error_message,
            'metrics' => $this->metrics ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}

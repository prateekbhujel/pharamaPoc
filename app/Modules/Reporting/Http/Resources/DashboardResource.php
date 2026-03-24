<?php

namespace App\Modules\Reporting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'filters' => data_get($this->resource, 'filters', []),
            'stats' => data_get($this->resource, 'stats', []),
            'recent_exports' => new ReportExportCollection(data_get($this->resource, 'recent_exports', collect())),
        ];
    }
}

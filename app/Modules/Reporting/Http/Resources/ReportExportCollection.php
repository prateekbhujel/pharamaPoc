<?php

namespace App\Modules\Reporting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ReportExportCollection extends ResourceCollection
{
    public $collects = ReportExportResource::class;

    public function toArray(Request $request): array
    {
        return $this->collection->map(
            static fn (ReportExportResource $resource): array => $resource->toArray($request)
        )->all();
    }
}

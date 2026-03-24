<?php

namespace App\Modules\Reporting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PreviewRowCollection extends ResourceCollection
{
    public $collects = PreviewRowResource::class;

    public function toArray(Request $request): array
    {
        return $this->collection->map(
            static fn (PreviewRowResource $resource): array => $resource->toArray($request)
        )->all();
    }
}

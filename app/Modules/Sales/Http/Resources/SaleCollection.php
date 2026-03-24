<?php

namespace App\Modules\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SaleCollection extends ResourceCollection
{
    public $collects = SaleResource::class;

    public function toArray(Request $request): array
    {
        return $this->collection->map(
            static fn (SaleResource $resource): array => $resource->toArray($request)
        )->all();
    }
}

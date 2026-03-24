<?php

namespace App\Modules\Pharmacy\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PharmacyCollection extends ResourceCollection
{
    public $collects = PharmacyResource::class;

    public function toArray(Request $request): array
    {
        return $this->collection->map(
            static fn (PharmacyResource $resource): array => $resource->toArray($request)
        )->all();
    }
}

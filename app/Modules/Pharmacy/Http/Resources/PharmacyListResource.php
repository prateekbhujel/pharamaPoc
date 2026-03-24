<?php

namespace App\Modules\Pharmacy\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PharmacyListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'items' => new PharmacyCollection(collect(data_get($this->resource, 'items', []))),
            'pagination' => data_get($this->resource, 'pagination', []),
        ];
    }
}

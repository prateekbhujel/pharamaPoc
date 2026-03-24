<?php

namespace App\Modules\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'items' => new SaleCollection(collect(data_get($this->resource, 'items', []))),
            'summary' => data_get($this->resource, 'summary', []),
            'pagination' => data_get($this->resource, 'pagination', []),
        ];
    }
}

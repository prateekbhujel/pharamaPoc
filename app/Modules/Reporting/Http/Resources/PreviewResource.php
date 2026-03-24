<?php

namespace App\Modules\Reporting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'summary' => data_get($this->resource, 'summary', []),
            'rows' => new PreviewRowCollection(collect(data_get($this->resource, 'rows', []))),
            'pagination' => data_get($this->resource, 'pagination', []),
        ];
    }
}

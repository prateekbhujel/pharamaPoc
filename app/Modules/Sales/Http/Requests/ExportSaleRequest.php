<?php

namespace App\Modules\Sales\Http\Requests;

use Illuminate\Validation\Rule;

class ExportSaleRequest extends IndexSaleRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'format' => ['required', Rule::in(['csv', 'xlsx'])],
        ];
    }

    public function exportFilters(): array
    {
        $filters = $this->filters();
        unset($filters['page'], $filters['per_page']);

        return $filters;
    }
}

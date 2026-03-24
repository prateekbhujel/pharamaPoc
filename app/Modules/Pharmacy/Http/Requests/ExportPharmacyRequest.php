<?php

namespace App\Modules\Pharmacy\Http\Requests;

use Illuminate\Validation\Rule;

class ExportPharmacyRequest extends IndexPharmacyRequest
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

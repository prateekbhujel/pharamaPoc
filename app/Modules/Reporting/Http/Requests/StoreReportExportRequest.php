<?php

namespace App\Modules\Reporting\Http\Requests;

use App\Support\SalesReportFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(
            collect($this->all())->map(
                static fn (mixed $value): mixed => $value === '' ? null : $value
            )->all()
        );
    }

    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'hospital_id' => ['nullable', 'integer', 'exists:hospitals,id'],
            'pharmacy_id' => ['nullable', 'integer', 'exists:pharmacies,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'payment_status' => ['nullable', Rule::in(['paid', 'insurance', 'partial', 'void'])],
            'cold_chain' => ['nullable', 'boolean'],
            'format' => ['required', Rule::in(['csv', 'xlsx'])],
        ];
    }

    public function reportFilters(): array
    {
        return SalesReportFilters::normalize($this->validated());
    }
}

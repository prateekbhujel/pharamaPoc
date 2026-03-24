<?php

namespace App\Modules\Reporting\Http\Requests;

use App\Support\SalesReportFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewReportRequest extends FormRequest
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
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }

    public function reportFilters(): array
    {
        return SalesReportFilters::normalize($this->validated());
    }

    public function pagination(): array
    {
        return [
            'page' => max(1, (int) ($this->validated('page') ?? 1)),
            'per_page' => min(100, max(5, (int) ($this->validated('per_page') ?? 10))),
        ];
    }
}

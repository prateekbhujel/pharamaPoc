<?php

namespace App\Modules\Pharmacy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexPharmacyRequest extends FormRequest
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
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'search' => ['nullable', 'string', 'max:120'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'hospital_id' => ['nullable', 'integer', 'exists:hospitals,id'],
        ];
    }

    public function filters(): array
    {
        return [
            'page' => max(1, (int) ($this->validated('page') ?? 1)),
            'per_page' => min(100, max(5, (int) ($this->validated('per_page') ?? 8))),
            'search' => $this->validated('search'),
            'tenant_id' => $this->validated('tenant_id'),
            'hospital_id' => $this->validated('hospital_id'),
        ];
    }
}

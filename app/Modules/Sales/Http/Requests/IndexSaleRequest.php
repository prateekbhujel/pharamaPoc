<?php

namespace App\Modules\Sales\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = collect($this->all())->map(
            static fn (mixed $value): mixed => $value === '' ? null : $value
        )->all();

        $payload['date_from'] ??= CarbonImmutable::now('Asia/Kathmandu')->subDays(14)->toDateString();
        $payload['date_to'] ??= CarbonImmutable::now('Asia/Kathmandu')->toDateString();

        $this->merge($payload);
    }

    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'search' => ['nullable', 'string', 'max:120'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'hospital_id' => ['nullable', 'integer', 'exists:hospitals,id'],
            'pharmacy_id' => ['nullable', 'integer', 'exists:pharmacies,id'],
            'medicine_id' => ['nullable', 'integer', 'exists:medicines,id'],
            'payment_status' => ['nullable', Rule::in(['paid', 'insurance', 'partial', 'void'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'card', 'wallet', 'insurance-claim', 'card-plus-cash', 'wallet-plus-cash', 'reversal'])],
        ];
    }

    public function filters(): array
    {
        return [
            'date_from' => CarbonImmutable::parse($this->validated('date_from'))->toDateString(),
            'date_to' => CarbonImmutable::parse($this->validated('date_to'))->toDateString(),
            'page' => max(1, (int) ($this->validated('page') ?? 1)),
            'per_page' => min(100, max(5, (int) ($this->validated('per_page') ?? 10))),
            'search' => $this->validated('search'),
            'tenant_id' => $this->validated('tenant_id'),
            'hospital_id' => $this->validated('hospital_id'),
            'pharmacy_id' => $this->validated('pharmacy_id'),
            'medicine_id' => $this->validated('medicine_id'),
            'payment_status' => $this->validated('payment_status'),
            'payment_method' => $this->validated('payment_method'),
        ];
    }
}

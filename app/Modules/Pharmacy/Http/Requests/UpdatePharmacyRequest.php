<?php

namespace App\Modules\Pharmacy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePharmacyRequest extends FormRequest
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
        $pharmacyId = (int) $this->route('pharmacyId');

        return [
            'hospital_id' => ['required', 'integer', 'exists:hospitals,id'],
            'code' => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('pharmacies', 'code')->ignore($pharmacyId)],
            'name' => ['required', 'string', 'max:160'],
            'license_number' => ['required', 'string', 'max:80', Rule::unique('pharmacies', 'license_number')->ignore($pharmacyId)],
            'contact_email' => ['required', 'email', 'max:160'],
            'area' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'district' => ['required', 'string', 'max:120'],
            'province' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:30'],
            'email_domain' => ['nullable', 'string', 'max:120'],
            'seed_demo_sale' => ['nullable', 'boolean'],
        ];
    }
}

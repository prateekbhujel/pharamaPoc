<?php

namespace App\Modules\Reporting\Http\Requests;

use Illuminate\Validation\Rule;

class ExportReportRequest extends PreviewReportRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'format' => ['required', Rule::in(['csv', 'xlsx'])],
        ];
    }
}

<?php

namespace App\Support;

class ExportFormatDecider
{
    /**
     * @return array{requested_format: string, actual_format: string, fell_back_to_csv: bool}
     */
    public static function resolveDirectDownload(string $requestedFormat, int $rowCount): array
    {
        $fallbackLimit = (int) config('reporting.xlsx_fast_fallback_limit');
        $shouldFallback = $requestedFormat === 'xlsx'
            && $fallbackLimit > 0
            && $rowCount > $fallbackLimit;

        return [
            'requested_format' => $requestedFormat,
            'actual_format' => $shouldFallback ? 'csv' : $requestedFormat,
            'fell_back_to_csv' => $shouldFallback,
        ];
    }
}

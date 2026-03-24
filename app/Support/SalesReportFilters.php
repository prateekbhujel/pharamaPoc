<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SalesReportFilters
{
    public static function normalize(array $validated): array
    {
        $filters = [
            'date_from' => CarbonImmutable::parse($validated['date_from'])->toDateString(),
            'date_to' => CarbonImmutable::parse($validated['date_to'])->toDateString(),
            'tenant_id' => isset($validated['tenant_id']) ? (int) $validated['tenant_id'] : null,
            'hospital_id' => isset($validated['hospital_id']) ? (int) $validated['hospital_id'] : null,
            'pharmacy_id' => isset($validated['pharmacy_id']) ? (int) $validated['pharmacy_id'] : null,
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'supplier_id' => isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
            'payment_status' => $validated['payment_status'] ?? null,
            'cold_chain' => array_key_exists('cold_chain', $validated)
                ? filter_var($validated['cold_chain'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null,
        ];

        return collect($filters)
            ->reject(static fn (mixed $value): bool => $value === null || $value === '')
            ->sortKeys()
            ->all();
    }

    public static function hashForExport(array $filters, string $format): string
    {
        return hash('sha256', json_encode([
            'filters' => $filters,
            'format' => $format,
        ], JSON_THROW_ON_ERROR));
    }

    public static function baseQuery(array $filters): Builder
    {
        return self::apply(DB::query()->fromRaw(LiveReportSource::sql()), $filters);
    }

    public static function apply(Builder $query, array $filters): Builder
    {
        $query->whereBetween('sold_at', [
            CarbonImmutable::parse($filters['date_from'])->startOfDay(),
            CarbonImmutable::parse($filters['date_to'])->endOfDay(),
        ]);

        if (isset($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (isset($filters['hospital_id'])) {
            $query->where('hospital_id', $filters['hospital_id']);
        }

        if (isset($filters['pharmacy_id'])) {
            $query->where('pharmacy_id', $filters['pharmacy_id']);
        }

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (array_key_exists('cold_chain', $filters)) {
            $query->where('is_cold_chain', $filters['cold_chain']);
        }

        return $query;
    }
}

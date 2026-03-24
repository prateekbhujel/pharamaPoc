<?php

return [
    'demo_sales_target' => (int) env('PHARMACY_SALES_TARGET', 180_000),
    'seed_organization_count' => (int) env('PHARMAPOC_ORGANIZATIONS', 200),
    'seed_hospital_min' => (int) env('PHARMAPOC_HOSPITALS_MIN', 2),
    'seed_hospital_max' => (int) env('PHARMAPOC_HOSPITALS_MAX', 4),
    'seed_pharmacy_min' => (int) env('PHARMAPOC_PHARMACIES_MIN', 2),
    'seed_pharmacy_max' => (int) env('PHARMAPOC_PHARMACIES_MAX', 4),
    'exports_disk' => env('PHARMACY_EXPORT_DISK', 'local'),
    'export_connection' => env('PHARMACY_EXPORT_CONNECTION', 'background'),
    'cache_minutes' => (int) env('PHARMACY_EXPORT_CACHE_MINUTES', 240),
    'csv_chunk_size' => (int) env('PHARMACY_EXPORT_CHUNK', 2_000),
    'progress_update_every' => (int) env('PHARMACY_EXPORT_PROGRESS_EVERY', 2_000),
    'instant_csv_row_limit' => (int) env('PHARMACY_INSTANT_CSV_LIMIT', 250_000),
    'xlsx_fast_fallback_limit' => (int) env('PHARMACY_XLSX_FAST_FALLBACK_LIMIT', 0),
    'xlsx_row_limit' => (int) env('PHARMACY_XLSX_ROW_LIMIT', 75_000),
    'bulk_target_rows' => (int) env('PHARMACY_BULK_TARGET', 100_000_000),
    'bulk_batch_size' => (int) env('PHARMACY_BULK_BATCH', 500_000),
];

<?php

namespace App\Console\Commands;

use Database\Seeders\NepalPharmacyDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ScalePharmacyDataset extends Command
{
    protected $signature = 'reporting:seed-scale
        {rows? : Number of report rows / sale_items to generate}
        {--batch= : Rows to insert per SQL batch}
        {--skip-refresh : Skip refreshing the materialized reporting view at the end}';

    protected $description = 'Generate a very large multi-tenant pharmacy dataset using Postgres set-based inserts';

    public function handle(): int
    {
        $rows = max(1, (int) ($this->argument('rows') ?: config('reporting.bulk_target_rows')));
        $batchSize = max(1_000, (int) ($this->option('batch') ?: config('reporting.bulk_batch_size')));

        $this->components->info("Target report rows: {$rows}");
        $this->components->info("Batch size: {$batchSize}");

        $this->ensureReferenceData();

        DB::statement('SET statement_timeout TO 0');
        DB::statement('TRUNCATE TABLE report_exports, sale_items, sales RESTART IDENTITY CASCADE');

        $pharmacyCount = (int) DB::table('pharmacies')->count();
        $patientCount = max(1, (int) DB::table('patients')->count());
        $prescriberCount = max(1, (int) DB::table('prescribers')->count());
        $medicineCount = (int) DB::table('medicines')->count();

        if ($pharmacyCount === 0 || $medicineCount === 0) {
            $this->components->error('Pharmacy or medicine reference data is missing. The bulk load cannot continue.');

            return self::FAILURE;
        }

        $baseTimestamp = now()->startOfDay()->format('Y-m-d H:i:s');
        $startedAt = microtime(true);
        $processed = 0;

        foreach (range(1, (int) ceil($rows / $batchSize)) as $batchNumber) {
            $start = (($batchNumber - 1) * $batchSize) + 1;
            $end = min($rows, $batchNumber * $batchSize);

            DB::unprepared($this->seedBatchSql(
                start: $start,
                end: $end,
                baseTimestamp: $baseTimestamp,
                pharmacyCount: $pharmacyCount,
                patientCount: $patientCount,
                prescriberCount: $prescriberCount,
                medicineCount: $medicineCount,
            ));

            $processed = $end;
            $elapsed = max(1, (int) (microtime(true) - $startedAt));
            $rate = (int) floor($processed / $elapsed);

            $this->components->info("Loaded {$processed} / {$rows} report rows ({$rate} rows/sec).");
        }

        $this->syncSequences(['sales', 'sale_items']);

        if (! $this->option('skip-refresh')) {
            $this->components->info('Refreshing materialized report view...');
            DB::statement('REFRESH MATERIALIZED VIEW pharmacy_sale_export_rows');
        }

        $duration = number_format(microtime(true) - $startedAt, 2);
        $this->components->info("Bulk dataset generation completed in {$duration} seconds.");

        return self::SUCCESS;
    }

    private function ensureReferenceData(): void
    {
        if (
            DB::table('tenants')->count() > 0 &&
            DB::table('hospitals')->count() > 0 &&
            DB::table('pharmacies')->count() > 0 &&
            DB::table('medicines')->count() > 0
        ) {
            return;
        }

        $this->components->info('Reference data is missing. Seeding the Kathmandu demo foundation first...');
        $this->call('db:seed', ['--class' => NepalPharmacyDemoSeeder::class]);
    }

    private function seedBatchSql(
        int $start,
        int $end,
        string $baseTimestamp,
        int $pharmacyCount,
        int $patientCount,
        int $prescriberCount,
        int $medicineCount,
    ): string {
        return <<<SQL
            CREATE TEMP TABLE bulk_sales_seed_batch ON COMMIT DROP AS
            WITH batch AS (
                SELECT generate_series({$start}, {$end}) AS seq
            ),
            base AS (
                SELECT
                    seq AS row_id,
                    ((seq * 7) % {$pharmacyCount}) + 1 AS pharmacy_id,
                    CASE
                        WHEN seq % 7 = 0 THEN NULL
                        ELSE ((seq * 11) % {$patientCount}) + 1
                    END AS patient_id,
                    CASE
                        WHEN seq % 9 = 0 THEN NULL
                        ELSE ((seq * 13) % {$prescriberCount}) + 1
                    END AS prescriber_id,
                    ((seq * 5) % {$medicineCount}) + 1 AS medicine_id,
                    CASE
                        WHEN seq % 19 = 0 THEN 'void'
                        WHEN seq % 5 = 0 THEN 'insurance'
                        WHEN seq % 3 = 0 THEN 'partial'
                        ELSE 'paid'
                    END AS payment_status,
                    CASE
                        WHEN seq % 19 = 0 THEN 'reversal'
                        WHEN seq % 5 = 0 THEN 'insurance-claim'
                        WHEN seq % 3 = 0 THEN CASE
                            WHEN seq % 2 = 0 THEN 'card-plus-cash'
                            ELSE 'wallet-plus-cash'
                        END
                        WHEN seq % 3 = 1 THEN 'cash'
                        WHEN seq % 3 = 2 THEN 'card'
                        ELSE 'wallet'
                    END AS payment_method,
                    (
                        TIMESTAMP '{$baseTimestamp}'
                        - make_interval(
                            days => (seq % 180),
                            hours => (7 + (seq % 11)),
                            mins => ((seq * 13) % 60)
                        )
                    ) AS sold_at,
                    ((seq % 5) + 1)::integer AS quantity,
                    round((medicines.unit_price + (((seq % 4) * 0.85)::numeric)), 2) AS unit_price
                FROM batch
                INNER JOIN medicines ON medicines.id = ((seq * 5) % {$medicineCount}) + 1
            ),
            priced AS (
                SELECT
                    *,
                    round((quantity * unit_price)::numeric, 2) AS gross_amount
                FROM base
            ),
            discounted AS (
                SELECT
                    *,
                    CASE
                        WHEN payment_status = 'void' THEN gross_amount
                        WHEN row_id % 6 = 0 THEN round((gross_amount * 0.05)::numeric, 2)
                        ELSE 0::numeric
                    END AS discount_amount
                FROM priced
            )
            SELECT
                row_id,
                pharmacy_id,
                patient_id,
                prescriber_id,
                medicine_id,
                payment_status,
                payment_method,
                sold_at,
                quantity,
                unit_price,
                gross_amount,
                discount_amount,
                CASE
                    WHEN payment_status = 'void' THEN 0::numeric
                    ELSE round(((gross_amount - discount_amount) * 0.13)::numeric, 2)
                END AS tax_amount,
                CASE
                    WHEN payment_status = 'void' THEN 0::numeric
                    ELSE round((gross_amount - discount_amount) + round(((gross_amount - discount_amount) * 0.13)::numeric, 2), 2)
                END AS net_amount
            FROM discounted;

            INSERT INTO sales (
                id,
                pharmacy_id,
                patient_id,
                prescriber_id,
                invoice_number,
                payment_method,
                payment_status,
                sold_at,
                gross_amount,
                discount_amount,
                tax_amount,
                net_amount,
                created_at,
                updated_at
            )
            SELECT
                row_id,
                pharmacy_id,
                patient_id,
                prescriber_id,
                'INV-' || LPAD(row_id::text, 12, '0'),
                payment_method,
                payment_status,
                sold_at,
                gross_amount,
                discount_amount,
                tax_amount,
                net_amount,
                sold_at,
                sold_at
            FROM bulk_sales_seed_batch;

            INSERT INTO sale_items (
                id,
                sale_id,
                medicine_id,
                batch_number,
                quantity,
                unit_price,
                discount_amount,
                tax_amount,
                line_total,
                expires_at,
                created_at,
                updated_at
            )
            SELECT
                row_id,
                row_id,
                medicine_id,
                'B-' || LPAD(((row_id * 17) % 999999)::text, 6, '0'),
                quantity,
                unit_price,
                discount_amount,
                tax_amount,
                net_amount,
                (CURRENT_DATE + make_interval(months => 6 + (row_id % 24)))::date,
                sold_at,
                sold_at
            FROM bulk_sales_seed_batch;

            DROP TABLE bulk_sales_seed_batch;
        SQL;
    }

    private function syncSequences(array $tables): void
    {
        foreach ($tables as $table) {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), coalesce((SELECT max(id) FROM {$table}), 1), true)");
        }
    }
}

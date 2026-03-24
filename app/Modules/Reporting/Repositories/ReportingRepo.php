<?php

namespace App\Modules\Reporting\Repositories;

use App\Modules\Reporting\Repositories\Interfaces\ReportingRepositoryInterface;
use App\Support\LiveReportSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;

class ReportingRepo implements ReportingRepositoryInterface
{
    public function getDashboardFilters(?int $organizationId = null, ?int $hospitalId = null): array
    {
        return [
            'tenants' => $this->selectList(
                $organizationId === null
                    ? 'SELECT id, code, name FROM tenants ORDER BY name'
                    : 'SELECT id, code, name FROM tenants WHERE id = ? ORDER BY name',
                $organizationId === null ? [] : [$organizationId]
            ),
            'hospitals' => $this->selectList(
                'SELECT id, tenant_id, code, name FROM hospitals'
                .$this->sqlWhere([
                    $organizationId !== null ? 'tenant_id = ?' : null,
                    $hospitalId !== null ? 'id = ?' : null,
                ])
                .' ORDER BY name',
                array_values(array_filter([$organizationId, $hospitalId], static fn (mixed $value): bool => $value !== null))
            ),
            'pharmacies' => $this->selectList(
                <<<'SQL'
                    SELECT pharmacies.id, pharmacies.hospital_id, pharmacies.code, pharmacies.name
                    FROM pharmacies
                    INNER JOIN hospitals ON hospitals.id = pharmacies.hospital_id
                SQL
                .$this->sqlWhere([
                    $organizationId !== null ? 'hospitals.tenant_id = ?' : null,
                    $hospitalId !== null ? 'hospitals.id = ?' : null,
                ])
                .' ORDER BY pharmacies.name',
                array_values(array_filter([$organizationId, $hospitalId], static fn (mixed $value): bool => $value !== null))
            ),
            'categories' => $this->selectList('SELECT id, name FROM categories ORDER BY name'),
            'suppliers' => $this->selectList('SELECT id, name FROM suppliers ORDER BY name'),
            'medicines' => $this->selectList(
                'SELECT id, brand_name AS name, generic_name, unit_price FROM medicines ORDER BY brand_name'
            ),
            'payment_statuses' => collect(['paid', 'insurance', 'partial', 'void'])
                ->map(static fn (string $status): array => [
                    'value' => $status,
                    'label' => ucfirst($status),
                ])
                ->all(),
            'payment_methods' => collect(['cash', 'card', 'wallet', 'insurance-claim', 'card-plus-cash', 'wallet-plus-cash', 'reversal'])
                ->map(static fn (string $method): array => [
                    'value' => $method,
                    'label' => Str::of($method)->replace('-', ' ')->title()->toString(),
                ])
                ->all(),
            'formats' => [
                ['value' => 'csv', 'label' => 'Excel CSV (fastest for huge exports)'],
                ['value' => 'xlsx', 'label' => 'Workbook XLSX (slower, smaller slices)'],
            ],
        ];
    }

    public function getDashboardStats(?int $organizationId = null, ?int $hospitalId = null): array
    {
        $hospitalBindings = array_values(array_filter([$organizationId, $hospitalId], static fn (mixed $value): bool => $value !== null));

        $tenants = $organizationId === null
            ? (int) DB::scalar('SELECT COUNT(*) FROM tenants')
            : 1;

        $hospitals = (int) DB::scalar(
            'SELECT COUNT(*) FROM hospitals'
            .$this->sqlWhere([
                $organizationId !== null ? 'tenant_id = ?' : null,
                $hospitalId !== null ? 'id = ?' : null,
            ]),
            $hospitalBindings
        );

        $pharmacyScopeSql = <<<'SQL'
            SELECT COUNT(*)
            FROM pharmacies
            INNER JOIN hospitals ON hospitals.id = pharmacies.hospital_id
        SQL;

        $pharmacies = (int) DB::scalar(
            $pharmacyScopeSql
            .$this->sqlWhere([
                $organizationId !== null ? 'hospitals.tenant_id = ?' : null,
                $hospitalId !== null ? 'hospitals.id = ?' : null,
            ]),
            $hospitalBindings
        );

        $salesScopeSql = <<<'SQL'
            SELECT
                COUNT(DISTINCT sales.id) AS sales_count,
                COUNT(sale_items.id) AS sale_items_count,
                MAX(sales.sold_at) AS latest_sale_at
            FROM sale_items
            INNER JOIN sales ON sales.id = sale_items.sale_id
            INNER JOIN pharmacies ON pharmacies.id = sales.pharmacy_id
            INNER JOIN hospitals ON hospitals.id = pharmacies.hospital_id
        SQL;

        $salesRow = DB::selectOne(
            $salesScopeSql
            .$this->sqlWhere([
                $organizationId !== null ? 'hospitals.tenant_id = ?' : null,
                $hospitalId !== null ? 'hospitals.id = ?' : null,
            ]),
            $hospitalBindings
        );

        $medicines = (int) DB::scalar('SELECT COUNT(*) FROM medicines');

        return [
            'tenants' => $tenants,
            'hospitals' => $hospitals,
            'pharmacies' => $pharmacies,
            'medicines' => $medicines,
            'sales' => (int) ($salesRow->sales_count ?? 0),
            'sale_items' => (int) ($salesRow->sale_items_count ?? 0),
            'latest_sale_at' => $salesRow?->latest_sale_at
                ? CarbonImmutable::parse($salesRow->latest_sale_at)->toIso8601String()
                : null,
        ];
    }

    public function preview(array $filters, array $pagination): array
    {
        $summary = $this->summary($filters);
        $lastPage = max(1, (int) ceil($summary['total_rows'] / max(1, $pagination['per_page'])));
        $currentPage = min($pagination['page'], $lastPage);
        $offset = ($currentPage - 1) * $pagination['per_page'];

        $bindings = [];
        $whereClause = $this->whereClause($filters, $bindings);

        $rows = collect(DB::select(
            <<<'SQL'
                SELECT
                    report_rows.sale_item_id,
                    report_rows.invoice_number,
                    report_rows.sold_at,
                    tenants.name AS tenant_name,
                    hospitals.name AS hospital_name,
                    pharmacies.name AS pharmacy_name,
                    pharmacy_locations.district AS pharmacy_district,
                    report_rows.patient_name,
                    report_rows.brand_name,
                    report_rows.generic_name,
                    report_rows.category_name,
                    report_rows.supplier_name,
                    report_rows.payment_status,
                    report_rows.quantity,
                    report_rows.line_total,
                    report_rows.is_cold_chain
                FROM
            SQL
            .$this->sourceSql()
            .' INNER JOIN tenants ON tenants.id = report_rows.tenant_id'
            .' INNER JOIN hospitals ON hospitals.id = report_rows.hospital_id'
            .' INNER JOIN pharmacies ON pharmacies.id = report_rows.pharmacy_id'
            .' INNER JOIN location_clusters AS pharmacy_locations ON pharmacy_locations.id = pharmacies.location_cluster_id'
            .$whereClause
            .' ORDER BY report_rows.sold_at DESC, report_rows.sale_item_id DESC LIMIT ? OFFSET ?',
            [...$bindings, $pagination['per_page'], $offset]
        ))->map(static function (object $row): array {
            return [
                'sale_item_id' => (int) $row->sale_item_id,
                'invoice_number' => (string) $row->invoice_number,
                'sold_at' => CarbonImmutable::parse($row->sold_at)->toIso8601String(),
                'tenant_name' => (string) $row->tenant_name,
                'hospital_name' => (string) $row->hospital_name,
                'pharmacy_name' => (string) $row->pharmacy_name,
                'pharmacy_district' => (string) $row->pharmacy_district,
                'patient_name' => (string) $row->patient_name,
                'brand_name' => (string) $row->brand_name,
                'generic_name' => (string) $row->generic_name,
                'category_name' => (string) $row->category_name,
                'supplier_name' => (string) $row->supplier_name,
                'payment_status' => (string) $row->payment_status,
                'quantity' => (int) $row->quantity,
                'line_total' => number_format((float) $row->line_total, 2, '.', ''),
                'is_cold_chain' => (bool) $row->is_cold_chain,
            ];
        })->all();

        return [
            'summary' => $summary,
            'rows' => $rows,
            'pagination' => [
                'current_page' => $currentPage,
                'per_page' => $pagination['per_page'],
                'total' => $summary['total_rows'],
                'last_page' => $lastPage,
                'from' => $summary['total_rows'] === 0 ? 0 : $offset + 1,
                'to' => min($offset + $pagination['per_page'], $summary['total_rows']),
            ],
        ];
    }

    public function countRows(array $filters): int
    {
        return $this->summary($filters)['total_rows'];
    }

    public function exportCsvToPath(array $filters, string $absolutePath): void
    {
        $bindings = [];
        $whereClause = $this->whereClause($filters, $bindings);
        $pdo = DB::connection()->getPdo();

        $selectSql = <<<'SQL'
            SELECT
                to_char(timezone('Asia/Kathmandu', report_rows.sold_at), 'YYYY-MM-DD HH24:MI') AS "Sold At",
                tenants.name AS "Tenant",
                hospitals.name AS "Hospital",
                pharmacies.name AS "Pharmacy",
                pharmacy_locations.district AS "District",
                report_rows.invoice_number AS "Invoice",
                report_rows.patient_code AS "Patient Code",
                report_rows.patient_name AS "Patient Name",
                report_rows.prescriber_name AS "Prescriber",
                report_rows.category_name AS "Category",
                report_rows.brand_name AS "Medicine",
                report_rows.generic_name AS "Generic",
                report_rows.batch_number AS "Batch",
                report_rows.quantity AS "Quantity",
                to_char(report_rows.unit_price::numeric, 'FM999999999999990.00') AS "Unit Price",
                to_char(report_rows.item_discount_amount::numeric, 'FM999999999999990.00') AS "Discount",
                to_char(report_rows.item_tax_amount::numeric, 'FM999999999999990.00') AS "Tax",
                to_char(report_rows.line_total::numeric, 'FM999999999999990.00') AS "Line Total",
                report_rows.payment_status AS "Payment Status",
                report_rows.supplier_name AS "Supplier",
                CASE WHEN report_rows.is_cold_chain THEN 'Yes' ELSE 'No' END AS "Cold Chain"
            FROM
        SQL;

        $query = $this->interpolateQuery(
            $pdo,
            $selectSql
            .$this->sourceSql()
            .' INNER JOIN tenants ON tenants.id = report_rows.tenant_id'
            .' INNER JOIN hospitals ON hospitals.id = report_rows.hospital_id'
            .' INNER JOIN pharmacies ON pharmacies.id = report_rows.pharmacy_id'
            .' INNER JOIN location_clusters AS pharmacy_locations ON pharmacy_locations.id = pharmacies.location_cluster_id'
            .$whereClause
            .' ORDER BY report_rows.sale_item_id',
            $bindings
        );

        $path = $this->quoteValue($pdo, $absolutePath);
        DB::statement("COPY ({$query}) TO {$path} WITH (FORMAT CSV, HEADER TRUE)");
    }

    public function upsertOverlayForSaleItem(int $saleItemId): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO pharmacy_sale_export_overlays (
                sale_item_id,
                sale_id,
                invoice_number,
                sold_at,
                payment_method,
                payment_status,
                tenant_id,
                tenant_code,
                tenant_name,
                hospital_id,
                hospital_code,
                hospital_name,
                hospital_city,
                hospital_district,
                pharmacy_id,
                pharmacy_code,
                pharmacy_name,
                pharmacy_area,
                pharmacy_city,
                pharmacy_district,
                patient_id,
                patient_code,
                patient_name,
                patient_email,
                patient_city,
                insurance_provider,
                prescriber_id,
                prescriber_name,
                prescriber_specialty,
                medicine_id,
                sku,
                brand_name,
                generic_name,
                dosage_form,
                strength,
                pack_size,
                is_cold_chain,
                category_id,
                category_name,
                supplier_id,
                supplier_name,
                manufacturer_id,
                manufacturer_name,
                batch_number,
                quantity,
                unit_price,
                item_discount_amount,
                item_tax_amount,
                line_total,
                line_subtotal,
                is_deleted
            )
            SELECT
                sale_items.id AS sale_item_id,
                sales.id AS sale_id,
                sales.invoice_number,
                sales.sold_at,
                sales.payment_method,
                sales.payment_status,
                tenants.id AS tenant_id,
                tenants.code AS tenant_code,
                tenants.name AS tenant_name,
                hospitals.id AS hospital_id,
                hospitals.code AS hospital_code,
                hospitals.name AS hospital_name,
                hospital_locations.city AS hospital_city,
                hospital_locations.district AS hospital_district,
                pharmacies.id AS pharmacy_id,
                pharmacies.code AS pharmacy_code,
                pharmacies.name AS pharmacy_name,
                pharmacy_locations.area AS pharmacy_area,
                pharmacy_locations.city AS pharmacy_city,
                pharmacy_locations.district AS pharmacy_district,
                patients.id AS patient_id,
                COALESCE(patients.code, '') AS patient_code,
                COALESCE(patients.full_name, 'Walk-in Customer') AS patient_name,
                COALESCE(patients.contact_email, '') AS patient_email,
                COALESCE(patient_locations.city, '') AS patient_city,
                COALESCE(patients.insurance_provider, 'Self Pay') AS insurance_provider,
                prescribers.id AS prescriber_id,
                COALESCE(prescribers.full_name, 'OTC Counter') AS prescriber_name,
                COALESCE(prescribers.specialty, 'General') AS prescriber_specialty,
                medicines.id AS medicine_id,
                medicines.sku,
                medicines.brand_name,
                medicines.generic_name,
                medicines.dosage_form,
                medicines.strength,
                medicines.pack_size,
                medicines.is_cold_chain,
                categories.id AS category_id,
                categories.name AS category_name,
                suppliers.id AS supplier_id,
                suppliers.name AS supplier_name,
                manufacturers.id AS manufacturer_id,
                manufacturers.name AS manufacturer_name,
                sale_items.batch_number,
                sale_items.quantity,
                sale_items.unit_price,
                sale_items.discount_amount AS item_discount_amount,
                sale_items.tax_amount AS item_tax_amount,
                sale_items.line_total,
                (sale_items.quantity * sale_items.unit_price) AS line_subtotal,
                FALSE AS is_deleted
            FROM sale_items
            INNER JOIN sales ON sales.id = sale_items.sale_id
            INNER JOIN pharmacies ON pharmacies.id = sales.pharmacy_id
            INNER JOIN hospitals ON hospitals.id = pharmacies.hospital_id
            INNER JOIN tenants ON tenants.id = hospitals.tenant_id
            INNER JOIN location_clusters AS hospital_locations ON hospital_locations.id = hospitals.location_cluster_id
            INNER JOIN location_clusters AS pharmacy_locations ON pharmacy_locations.id = pharmacies.location_cluster_id
            LEFT JOIN patients ON patients.id = sales.patient_id
            LEFT JOIN location_clusters AS patient_locations ON patient_locations.id = patients.location_cluster_id
            LEFT JOIN prescribers ON prescribers.id = sales.prescriber_id
            INNER JOIN medicines ON medicines.id = sale_items.medicine_id
            INNER JOIN categories ON categories.id = medicines.category_id
            INNER JOIN suppliers ON suppliers.id = medicines.supplier_id
            INNER JOIN manufacturers ON manufacturers.id = medicines.manufacturer_id
            WHERE sale_items.id = ?
            ON CONFLICT (sale_item_id) DO UPDATE SET
                sale_id = EXCLUDED.sale_id,
                invoice_number = EXCLUDED.invoice_number,
                sold_at = EXCLUDED.sold_at,
                payment_method = EXCLUDED.payment_method,
                payment_status = EXCLUDED.payment_status,
                tenant_id = EXCLUDED.tenant_id,
                tenant_code = EXCLUDED.tenant_code,
                tenant_name = EXCLUDED.tenant_name,
                hospital_id = EXCLUDED.hospital_id,
                hospital_code = EXCLUDED.hospital_code,
                hospital_name = EXCLUDED.hospital_name,
                hospital_city = EXCLUDED.hospital_city,
                hospital_district = EXCLUDED.hospital_district,
                pharmacy_id = EXCLUDED.pharmacy_id,
                pharmacy_code = EXCLUDED.pharmacy_code,
                pharmacy_name = EXCLUDED.pharmacy_name,
                pharmacy_area = EXCLUDED.pharmacy_area,
                pharmacy_city = EXCLUDED.pharmacy_city,
                pharmacy_district = EXCLUDED.pharmacy_district,
                patient_id = EXCLUDED.patient_id,
                patient_code = EXCLUDED.patient_code,
                patient_name = EXCLUDED.patient_name,
                patient_email = EXCLUDED.patient_email,
                patient_city = EXCLUDED.patient_city,
                insurance_provider = EXCLUDED.insurance_provider,
                prescriber_id = EXCLUDED.prescriber_id,
                prescriber_name = EXCLUDED.prescriber_name,
                prescriber_specialty = EXCLUDED.prescriber_specialty,
                medicine_id = EXCLUDED.medicine_id,
                sku = EXCLUDED.sku,
                brand_name = EXCLUDED.brand_name,
                generic_name = EXCLUDED.generic_name,
                dosage_form = EXCLUDED.dosage_form,
                strength = EXCLUDED.strength,
                pack_size = EXCLUDED.pack_size,
                is_cold_chain = EXCLUDED.is_cold_chain,
                category_id = EXCLUDED.category_id,
                category_name = EXCLUDED.category_name,
                supplier_id = EXCLUDED.supplier_id,
                supplier_name = EXCLUDED.supplier_name,
                manufacturer_id = EXCLUDED.manufacturer_id,
                manufacturer_name = EXCLUDED.manufacturer_name,
                batch_number = EXCLUDED.batch_number,
                quantity = EXCLUDED.quantity,
                unit_price = EXCLUDED.unit_price,
                item_discount_amount = EXCLUDED.item_discount_amount,
                item_tax_amount = EXCLUDED.item_tax_amount,
                line_total = EXCLUDED.line_total,
                line_subtotal = EXCLUDED.line_subtotal,
                is_deleted = FALSE
        SQL, [$saleItemId]);
    }

    public function markOverlayDeleted(int $saleItemId): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO pharmacy_sale_export_overlays (
                sale_item_id,
                sale_id,
                invoice_number,
                sold_at,
                payment_method,
                payment_status,
                tenant_id,
                tenant_code,
                tenant_name,
                hospital_id,
                hospital_code,
                hospital_name,
                hospital_city,
                hospital_district,
                pharmacy_id,
                pharmacy_code,
                pharmacy_name,
                pharmacy_area,
                pharmacy_city,
                pharmacy_district,
                patient_id,
                patient_code,
                patient_name,
                patient_email,
                patient_city,
                insurance_provider,
                prescriber_id,
                prescriber_name,
                prescriber_specialty,
                medicine_id,
                sku,
                brand_name,
                generic_name,
                dosage_form,
                strength,
                pack_size,
                is_cold_chain,
                category_id,
                category_name,
                supplier_id,
                supplier_name,
                manufacturer_id,
                manufacturer_name,
                batch_number,
                quantity,
                unit_price,
                item_discount_amount,
                item_tax_amount,
                line_total,
                line_subtotal,
                is_deleted
            )
            SELECT
                base_rows.sale_item_id,
                base_rows.sale_id,
                base_rows.invoice_number,
                base_rows.sold_at,
                base_rows.payment_method,
                base_rows.payment_status,
                base_rows.tenant_id,
                base_rows.tenant_code,
                base_rows.tenant_name,
                base_rows.hospital_id,
                base_rows.hospital_code,
                base_rows.hospital_name,
                base_rows.hospital_city,
                base_rows.hospital_district,
                base_rows.pharmacy_id,
                base_rows.pharmacy_code,
                base_rows.pharmacy_name,
                base_rows.pharmacy_area,
                base_rows.pharmacy_city,
                base_rows.pharmacy_district,
                base_rows.patient_id,
                base_rows.patient_code,
                base_rows.patient_name,
                base_rows.patient_email,
                base_rows.patient_city,
                base_rows.insurance_provider,
                base_rows.prescriber_id,
                base_rows.prescriber_name,
                base_rows.prescriber_specialty,
                base_rows.medicine_id,
                base_rows.sku,
                base_rows.brand_name,
                base_rows.generic_name,
                base_rows.dosage_form,
                base_rows.strength,
                base_rows.pack_size,
                base_rows.is_cold_chain,
                base_rows.category_id,
                base_rows.category_name,
                base_rows.supplier_id,
                base_rows.supplier_name,
                base_rows.manufacturer_id,
                base_rows.manufacturer_name,
                base_rows.batch_number,
                base_rows.quantity,
                base_rows.unit_price,
                base_rows.item_discount_amount,
                base_rows.item_tax_amount,
                base_rows.line_total,
                base_rows.line_subtotal,
                TRUE AS is_deleted
            FROM pharmacy_sale_export_rows AS base_rows
            WHERE base_rows.sale_item_id = ?
            ON CONFLICT (sale_item_id) DO UPDATE SET
                is_deleted = TRUE
        SQL, [$saleItemId]);

        DB::delete(
            'DELETE FROM pharmacy_sale_export_overlays WHERE sale_item_id = ? AND is_deleted = FALSE AND NOT EXISTS (SELECT 1 FROM pharmacy_sale_export_rows WHERE sale_item_id = ?)',
            [$saleItemId, $saleItemId]
        );
    }

    private function summary(array $filters): array
    {
        $bindings = [];
        $whereClause = $this->whereClause($filters, $bindings);
        $row = DB::selectOne(
            <<<'SQL'
                SELECT
                    COUNT(*) AS total_rows,
                    COALESCE(SUM(report_rows.quantity), 0) AS total_units,
                    COALESCE(SUM(report_rows.line_total), 0) AS total_revenue
                FROM
            SQL
            .$this->sourceSql()
            .$whereClause,
            $bindings
        );

        return [
            'total_rows' => (int) ($row->total_rows ?? 0),
            'total_units' => (int) ($row->total_units ?? 0),
            'total_revenue' => number_format((float) ($row->total_revenue ?? 0), 2, '.', ''),
        ];
    }

    /**
     * @param  array<int, mixed>  $bindings
     */
    private function whereClause(array $filters, array &$bindings): string
    {
        $clauses = [
            'report_rows.sold_at BETWEEN ? AND ?',
        ];

        $bindings[] = CarbonImmutable::parse($filters['date_from'])->startOfDay()->toDateTimeString();
        $bindings[] = CarbonImmutable::parse($filters['date_to'])->endOfDay()->toDateTimeString();

        foreach ([
            'report_rows.tenant_id' => $filters['tenant_id'] ?? null,
            'report_rows.hospital_id' => $filters['hospital_id'] ?? null,
            'report_rows.pharmacy_id' => $filters['pharmacy_id'] ?? null,
            'report_rows.category_id' => $filters['category_id'] ?? null,
            'report_rows.supplier_id' => $filters['supplier_id'] ?? null,
        ] as $column => $value) {
            if ($value === null) {
                continue;
            }

            $clauses[] = "{$column} = ?";
            $bindings[] = $value;
        }

        if (($filters['payment_status'] ?? null) !== null) {
            $clauses[] = 'report_rows.payment_status = ?';
            $bindings[] = $filters['payment_status'];
        }

        if (($filters['cold_chain'] ?? null) !== null) {
            $clauses[] = 'report_rows.is_cold_chain = ?';
            $bindings[] = $filters['cold_chain'];
        }

        return ' WHERE '.implode(' AND ', $clauses);
    }

    private function sourceSql(): string
    {
        return LiveReportSource::sql();
    }

    /**
     * @param  array<int, mixed>  $bindings
     */
    private function interpolateQuery(PDO $pdo, string $sql, array $bindings): string
    {
        $quotedBindings = array_map(
            fn (mixed $binding): string => $this->quoteValue($pdo, $binding),
            $bindings
        );

        return Str::replaceArray('?', $quotedBindings, $sql);
    }

    private function quoteValue(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $pdo->quote((string) $value);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function selectList(string $sql, array $bindings = []): array
    {
        return array_map(
            static fn (object $row): array => (array) $row,
            DB::select($sql, $bindings)
        );
    }

    /**
     * @param  array<int, string|null>  $clauses
     */
    private function sqlWhere(array $clauses): string
    {
        $clauses = array_values(array_filter($clauses));

        if ($clauses === []) {
            return '';
        }

        return ' WHERE '.implode(' AND ', $clauses);
    }
}

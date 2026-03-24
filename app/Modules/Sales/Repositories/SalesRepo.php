<?php

namespace App\Modules\Sales\Repositories;

use App\Modules\Sales\Repositories\Interfaces\SalesRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;

class SalesRepo implements SalesRepositoryInterface
{
    public function paginate(array $filters): array
    {
        $bindings = [];
        $whereClause = $this->whereClause($filters, $bindings);
        $summary = $this->summary($filters);
        $lastPage = max(1, (int) ceil($summary['total_rows'] / max(1, $filters['per_page'])));
        $currentPage = min($filters['page'], $lastPage);
        $offset = ($currentPage - 1) * $filters['per_page'];

        $rows = DB::select(
            $this->baseSelectSql()
            .$whereClause
            .' ORDER BY sales.sold_at DESC, sale_items.id DESC LIMIT ? OFFSET ?',
            [...$bindings, $filters['per_page'], $offset]
        );

        return [
            'items' => array_map(fn (object $row): array => $this->hydrate($row), $rows),
            'summary' => $summary,
            'pagination' => [
                'current_page' => $currentPage,
                'per_page' => $filters['per_page'],
                'total' => $summary['total_rows'],
                'last_page' => $lastPage,
                'from' => $summary['total_rows'] === 0 ? 0 : $offset + 1,
                'to' => min($offset + $filters['per_page'], $summary['total_rows']),
            ],
        ];
    }

    public function countRows(array $filters): int
    {
        return $this->summary($filters)['total_rows'];
    }

    public function findByIdOrFail(int $saleItemId): array
    {
        $row = DB::selectOne($this->baseSelectSql().' WHERE sale_items.id = ?', [$saleItemId]);

        if (! $row) {
            throw (new ModelNotFoundException)->setModel('sale_items', [$saleItemId]);
        }

        return $this->hydrate($row);
    }

    public function create(array $data): array
    {
        $sale = DB::selectOne(
            <<<'SQL'
                INSERT INTO sales (
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
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                RETURNING id
            SQL,
            [
                $data['pharmacy_id'],
                $data['patient_id'],
                $data['prescriber_id'],
                $data['invoice_number'],
                $data['payment_method'],
                $data['payment_status'],
                $data['sold_at'],
                $data['gross_amount'],
                $data['discount_amount'],
                $data['tax_amount'],
                $data['net_amount'],
            ]
        );

        $saleItem = DB::selectOne(
            <<<'SQL'
                INSERT INTO sale_items (
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
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                RETURNING id
            SQL,
            [
                (int) $sale->id,
                $data['medicine_id'],
                $data['batch_number'],
                $data['quantity'],
                $data['unit_price'],
                $data['discount_amount'],
                $data['tax_amount'],
                $data['net_amount'],
                $data['expires_at'],
            ]
        );

        $this->syncSaleTotals((int) $sale->id);

        return $this->findByIdOrFail((int) $saleItem->id);
    }

    public function update(int $saleItemId, array $data): array
    {
        $saleId = $this->saleIdForItem($saleItemId);

        DB::update(
            <<<'SQL'
                UPDATE sales
                SET
                    pharmacy_id = ?,
                    patient_id = ?,
                    prescriber_id = ?,
                    invoice_number = ?,
                    payment_method = ?,
                    payment_status = ?,
                    sold_at = ?,
                    updated_at = NOW()
                WHERE id = ?
            SQL,
            [
                $data['pharmacy_id'],
                $data['patient_id'],
                $data['prescriber_id'],
                $data['invoice_number'],
                $data['payment_method'],
                $data['payment_status'],
                $data['sold_at'],
                $saleId,
            ]
        );

        DB::update(
            <<<'SQL'
                UPDATE sale_items
                SET
                    medicine_id = ?,
                    batch_number = ?,
                    quantity = ?,
                    unit_price = ?,
                    discount_amount = ?,
                    tax_amount = ?,
                    line_total = ?,
                    expires_at = ?,
                    updated_at = NOW()
                WHERE id = ?
            SQL,
            [
                $data['medicine_id'],
                $data['batch_number'],
                $data['quantity'],
                $data['unit_price'],
                $data['discount_amount'],
                $data['tax_amount'],
                $data['net_amount'],
                $data['expires_at'],
                $saleItemId,
            ]
        );

        $this->syncSaleTotals($saleId);

        return $this->findByIdOrFail($saleItemId);
    }

    public function delete(int $saleItemId): void
    {
        $saleId = $this->saleIdForItem($saleItemId);

        DB::delete('DELETE FROM sale_items WHERE id = ?', [$saleItemId]);

        $remainingItems = (int) DB::scalar('SELECT COUNT(*) FROM sale_items WHERE sale_id = ?', [$saleId]);

        if ($remainingItems === 0) {
            DB::delete('DELETE FROM sales WHERE id = ?', [$saleId]);

            return;
        }

        $this->syncSaleTotals($saleId);
    }

    public function saleIdForItem(int $saleItemId): int
    {
        $saleId = DB::scalar('SELECT sale_id FROM sale_items WHERE id = ?', [$saleItemId]);

        if (! $saleId) {
            throw (new ModelNotFoundException)->setModel('sale_items', [$saleItemId]);
        }

        return (int) $saleId;
    }

    public function saleItemIdsForSale(int $saleId): array
    {
        return DB::table('sale_items')
            ->where('sale_id', $saleId)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function exportCsvToPath(array $filters, string $absolutePath): void
    {
        $bindings = [];
        $whereClause = $this->whereClause($filters, $bindings);
        $pdo = DB::connection()->getPdo();

        $selectSql = <<<'SQL'
            SELECT
                to_char(timezone('Asia/Kathmandu', sales.sold_at), 'YYYY-MM-DD HH24:MI') AS "Sold At",
                sales.invoice_number AS "Invoice",
                tenants.name AS "Organization",
                hospitals.name AS "Hospital",
                pharmacies.name AS "Pharmacy",
                medicines.brand_name AS "Medicine",
                medicines.generic_name AS "Generic",
                COALESCE(patients.full_name, 'Walk-in Customer') AS "Patient",
                sales.payment_method AS "Payment Method",
                sales.payment_status AS "Payment Status",
                sale_items.batch_number AS "Batch",
                sale_items.quantity AS "Quantity",
                to_char(sale_items.unit_price::numeric, 'FM999999999999990.00') AS "Unit Price",
                to_char(sale_items.discount_amount::numeric, 'FM999999999999990.00') AS "Discount",
                to_char(sale_items.tax_amount::numeric, 'FM999999999999990.00') AS "Tax",
                to_char(sale_items.line_total::numeric, 'FM999999999999990.00') AS "Line Total",
                COALESCE(to_char(sale_items.expires_at, 'YYYY-MM-DD'), '') AS "Expires At"
            FROM sale_items
            INNER JOIN sales ON sales.id = sale_items.sale_id
            INNER JOIN pharmacies ON pharmacies.id = sales.pharmacy_id
            INNER JOIN hospitals ON hospitals.id = pharmacies.hospital_id
            INNER JOIN tenants ON tenants.id = hospitals.tenant_id
            INNER JOIN medicines ON medicines.id = sale_items.medicine_id
            LEFT JOIN patients ON patients.id = sales.patient_id
        SQL;

        $query = $this->interpolateQuery(
            $pdo,
            $selectSql.$whereClause.' ORDER BY sales.sold_at DESC, sale_items.id DESC',
            $bindings
        );

        $path = $this->quoteValue($pdo, $absolutePath);
        DB::statement("COPY ({$query}) TO {$path} WITH (FORMAT CSV, HEADER TRUE)");
    }

    private function summary(array $filters): array
    {
        $bindings = [];
        $whereClause = $this->whereClause($filters, $bindings);

        $row = DB::selectOne(
            <<<'SQL'
                SELECT
                    COUNT(*) AS total_rows,
                    COALESCE(SUM(sale_items.quantity), 0) AS total_quantity,
                    COALESCE(SUM(sale_items.line_total), 0) AS total_revenue,
                    MAX(sales.sold_at) AS latest_sale_at
                FROM sale_items
                INNER JOIN sales ON sales.id = sale_items.sale_id
                INNER JOIN pharmacies ON pharmacies.id = sales.pharmacy_id
                INNER JOIN hospitals ON hospitals.id = pharmacies.hospital_id
                INNER JOIN tenants ON tenants.id = hospitals.tenant_id
                INNER JOIN medicines ON medicines.id = sale_items.medicine_id
                LEFT JOIN patients ON patients.id = sales.patient_id
            SQL
            .$whereClause,
            $bindings
        );

        return [
            'total_rows' => (int) ($row->total_rows ?? 0),
            'total_quantity' => (int) ($row->total_quantity ?? 0),
            'total_revenue' => number_format((float) ($row->total_revenue ?? 0), 2, '.', ''),
            'latest_sale_at' => $row?->latest_sale_at
                ? CarbonImmutable::parse($row->latest_sale_at)->toIso8601String()
                : null,
        ];
    }

    private function hydrate(object $row): array
    {
        return [
            'sale_item_id' => (int) $row->sale_item_id,
            'sale_id' => (int) $row->sale_id,
            'tenant_id' => (int) $row->tenant_id,
            'tenant_name' => (string) $row->tenant_name,
            'hospital_id' => (int) $row->hospital_id,
            'hospital_name' => (string) $row->hospital_name,
            'pharmacy_id' => (int) $row->pharmacy_id,
            'pharmacy_name' => (string) $row->pharmacy_name,
            'medicine_id' => (int) $row->medicine_id,
            'brand_name' => (string) $row->brand_name,
            'generic_name' => (string) $row->generic_name,
            'invoice_number' => (string) $row->invoice_number,
            'payment_method' => (string) $row->payment_method,
            'payment_status' => (string) $row->payment_status,
            'patient_name' => (string) $row->patient_name,
            'prescriber_name' => (string) $row->prescriber_name,
            'batch_number' => (string) $row->batch_number,
            'quantity' => (int) $row->quantity,
            'unit_price' => number_format((float) $row->unit_price, 2, '.', ''),
            'discount_amount' => number_format((float) $row->discount_amount, 2, '.', ''),
            'tax_amount' => number_format((float) $row->tax_amount, 2, '.', ''),
            'line_total' => number_format((float) $row->line_total, 2, '.', ''),
            'sold_at' => CarbonImmutable::parse($row->sold_at)->toIso8601String(),
            'expires_at' => $row->expires_at ? CarbonImmutable::parse($row->expires_at)->toDateString() : null,
            'updated_at' => CarbonImmutable::parse($row->updated_at)->toIso8601String(),
        ];
    }

    private function baseSelectSql(): string
    {
        return <<<'SQL'
            SELECT
                sale_items.id AS sale_item_id,
                sales.id AS sale_id,
                tenants.id AS tenant_id,
                tenants.name AS tenant_name,
                hospitals.id AS hospital_id,
                hospitals.name AS hospital_name,
                pharmacies.id AS pharmacy_id,
                pharmacies.name AS pharmacy_name,
                medicines.id AS medicine_id,
                medicines.brand_name,
                medicines.generic_name,
                sales.invoice_number,
                sales.payment_method,
                sales.payment_status,
                COALESCE(patients.full_name, 'Walk-in Customer') AS patient_name,
                COALESCE(prescribers.full_name, 'OTC Counter') AS prescriber_name,
                sale_items.batch_number,
                sale_items.quantity,
                sale_items.unit_price,
                sale_items.discount_amount,
                sale_items.tax_amount,
                sale_items.line_total,
                sales.sold_at,
                sale_items.expires_at,
                sale_items.updated_at
            FROM sale_items
            INNER JOIN sales ON sales.id = sale_items.sale_id
            INNER JOIN pharmacies ON pharmacies.id = sales.pharmacy_id
            INNER JOIN hospitals ON hospitals.id = pharmacies.hospital_id
            INNER JOIN tenants ON tenants.id = hospitals.tenant_id
            INNER JOIN medicines ON medicines.id = sale_items.medicine_id
            LEFT JOIN patients ON patients.id = sales.patient_id
            LEFT JOIN prescribers ON prescribers.id = sales.prescriber_id
        SQL;
    }

    private function syncSaleTotals(int $saleId): void
    {
        DB::update(
            <<<'SQL'
                UPDATE sales
                SET
                    gross_amount = totals.gross_amount,
                    discount_amount = totals.discount_amount,
                    tax_amount = totals.tax_amount,
                    net_amount = totals.net_amount,
                    updated_at = NOW()
                FROM (
                    SELECT
                        sale_id,
                        COALESCE(SUM(quantity * unit_price), 0) AS gross_amount,
                        COALESCE(SUM(discount_amount), 0) AS discount_amount,
                        COALESCE(SUM(tax_amount), 0) AS tax_amount,
                        COALESCE(SUM(line_total), 0) AS net_amount
                    FROM sale_items
                    WHERE sale_id = ?
                    GROUP BY sale_id
                ) AS totals
                WHERE sales.id = totals.sale_id
            SQL,
            [$saleId]
        );
    }

    /**
     * @param  array<int, mixed>  $bindings
     */
    private function whereClause(array $filters, array &$bindings): string
    {
        $clauses = [
            'sales.sold_at BETWEEN ? AND ?',
        ];

        $bindings[] = CarbonImmutable::parse($filters['date_from'])->startOfDay()->toDateTimeString();
        $bindings[] = CarbonImmutable::parse($filters['date_to'])->endOfDay()->toDateTimeString();

        foreach ([
            'tenants.id' => $filters['tenant_id'] ?? null,
            'hospitals.id' => $filters['hospital_id'] ?? null,
            'pharmacies.id' => $filters['pharmacy_id'] ?? null,
            'medicines.id' => $filters['medicine_id'] ?? null,
        ] as $column => $value) {
            if ($value === null) {
                continue;
            }

            $clauses[] = "{$column} = ?";
            $bindings[] = $value;
        }

        if (($filters['payment_status'] ?? null) !== null) {
            $clauses[] = 'sales.payment_status = ?';
            $bindings[] = $filters['payment_status'];
        }

        if (($filters['payment_method'] ?? null) !== null) {
            $clauses[] = 'sales.payment_method = ?';
            $bindings[] = $filters['payment_method'];
        }

        if (($filters['search'] ?? null) !== null) {
            $search = '%'.$filters['search'].'%';
            $clauses[] = '(sales.invoice_number ILIKE ? OR pharmacies.name ILIKE ? OR medicines.brand_name ILIKE ? OR medicines.generic_name ILIKE ? OR COALESCE(patients.full_name, \'Walk-in Customer\') ILIKE ?)';
            array_push($bindings, $search, $search, $search, $search, $search);
        }

        return ' WHERE '.implode(' AND ', $clauses);
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
        return $value === null ? 'NULL' : $pdo->quote((string) $value);
    }
}

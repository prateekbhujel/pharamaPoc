<?php

namespace App\Modules\Pharmacy\Repositories;

use App\Modules\Pharmacy\Repositories\Interfaces\PharmacyRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Str as StringHelper;
use PDO;

class PharmacyRepo implements PharmacyRepositoryInterface
{
    public function paginate(array $filters): array
    {
        $bindings = [];
        $whereClause = $this->whereClause($filters, $bindings);
        $total = $this->countRows($filters);
        $lastPage = max(1, (int) ceil($total / max(1, $filters['per_page'])));
        $currentPage = min($filters['page'], $lastPage);
        $offset = ($currentPage - 1) * $filters['per_page'];

        $rows = DB::select(
            $this->baseSelectSql()
            .$whereClause
            .' ORDER BY pharmacies.created_at DESC, pharmacies.id DESC LIMIT ? OFFSET ?',
            [...$bindings, $filters['per_page'], $offset]
        );

        return [
            'items' => array_map(fn (object $row): array => $this->hydrate($row), $rows),
            'pagination' => [
                'current_page' => $currentPage,
                'per_page' => $filters['per_page'],
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total === 0 ? 0 : $offset + 1,
                'to' => min($offset + $filters['per_page'], $total),
            ],
        ];
    }

    public function countRows(array $filters): int
    {
        $bindings = [];
        $whereClause = $this->whereClause($filters, $bindings);

        return (int) DB::scalar(
            <<<'SQL'
                SELECT COUNT(*)
                FROM pharmacies
                INNER JOIN hospitals ON hospitals.id = pharmacies.hospital_id
                INNER JOIN tenants ON tenants.id = hospitals.tenant_id
            SQL
            .$whereClause,
            $bindings
        );
    }

    public function findByIdOrFail(int $id): array
    {
        $row = DB::selectOne($this->baseSelectSql().' WHERE pharmacies.id = ?', [$id]);

        if (! $row) {
            throw (new ModelNotFoundException)->setModel('pharmacies', [$id]);
        }

        return $this->hydrate($row);
    }

    public function create(array $data): array
    {
        $locationClusterId = $this->findOrCreateLocationCluster($data);

        $row = DB::selectOne(
            <<<'SQL'
                INSERT INTO pharmacies (
                    hospital_id,
                    location_cluster_id,
                    code,
                    name,
                    license_number,
                    contact_email,
                    created_at,
                    updated_at
                )
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                RETURNING id
            SQL,
            [
                $data['hospital_id'],
                $locationClusterId,
                $data['code'],
                $data['name'],
                $data['license_number'],
                $data['contact_email'],
            ]
        );

        return $this->findByIdOrFail((int) $row->id);
    }

    public function update(int $id, array $data): array
    {
        $locationClusterId = $this->findOrCreateLocationCluster($data);

        DB::update(
            <<<'SQL'
                UPDATE pharmacies
                SET
                    hospital_id = ?,
                    location_cluster_id = ?,
                    code = ?,
                    name = ?,
                    license_number = ?,
                    contact_email = ?,
                    updated_at = NOW()
                WHERE id = ?
            SQL,
            [
                $data['hospital_id'],
                $locationClusterId,
                $data['code'],
                $data['name'],
                $data['license_number'],
                $data['contact_email'],
                $id,
            ]
        );

        return $this->findByIdOrFail($id);
    }

    public function delete(int $id): void
    {
        DB::delete('DELETE FROM pharmacies WHERE id = ?', [$id]);
    }

    public function salesCount(int $id): int
    {
        return (int) DB::scalar('SELECT COUNT(*) FROM sales WHERE pharmacy_id = ?', [$id]);
    }

    public function hospitalId(int $id): int
    {
        return (int) DB::scalar('SELECT hospital_id FROM pharmacies WHERE id = ?', [$id]);
    }

    public function createDemoSale(int $pharmacyId): int
    {
        $hospitalId = $this->hospitalId($pharmacyId);
        $medicine = DB::selectOne('SELECT id, unit_price FROM medicines ORDER BY random() LIMIT 1');

        if (! $medicine) {
            throw new \RuntimeException('I could not seed a demo sale because no medicine records exist yet.');
        }

        $patientId = DB::scalar('SELECT id FROM patients ORDER BY random() LIMIT 1');
        $prescriberId = DB::scalar('SELECT id FROM prescribers WHERE hospital_id = ? ORDER BY random() LIMIT 1', [$hospitalId]);
        $quantity = random_int(1, 4);
        $unitPrice = (float) $medicine->unit_price;
        $grossAmount = round($quantity * $unitPrice, 2);
        $discountAmount = round($grossAmount * 0.05, 2);
        $taxAmount = round(($grossAmount - $discountAmount) * 0.13, 2);
        $netAmount = round($grossAmount - $discountAmount + $taxAmount, 2);
        $invoiceNumber = 'POC-'.Str::upper(Str::random(12));
        $soldAt = CarbonImmutable::now('Asia/Kathmandu')->toDateTimeString();
        $saleId = (int) DB::scalar('SELECT COALESCE(MAX(id), 0) + 1 FROM sales');
        $saleItemId = (int) DB::scalar('SELECT COALESCE(MAX(id), 0) + 1 FROM sale_items');

        DB::insert(
            <<<'SQL'
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
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            SQL,
            [
                $saleId,
                $pharmacyId,
                $patientId,
                $prescriberId,
                $invoiceNumber,
                'cash',
                'paid',
                $soldAt,
                $grossAmount,
                $discountAmount,
                $taxAmount,
                $netAmount,
            ]
        );

        DB::insert(
            <<<'SQL'
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
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            SQL,
            [
                $saleItemId,
                $saleId,
                (int) $medicine->id,
                'BATCH-'.Str::upper(Str::random(6)),
                $quantity,
                $unitPrice,
                $discountAmount,
                $taxAmount,
                $netAmount,
                CarbonImmutable::now()->addYear()->toDateString(),
            ]
        );

        return $saleItemId;
    }

    public function exportCsvToPath(array $filters, string $absolutePath): void
    {
        $bindings = [];
        $whereClause = $this->whereClause($filters, $bindings);
        $pdo = DB::connection()->getPdo();

        $selectSql = <<<'SQL'
            SELECT
                tenants.name AS "Organization",
                hospitals.name AS "Hospital",
                pharmacies.code AS "Code",
                pharmacies.name AS "Pharmacy",
                pharmacies.license_number AS "License Number",
                pharmacies.contact_email AS "Contact Email",
                location_clusters.area AS "Area",
                location_clusters.city AS "City",
                location_clusters.district AS "District",
                location_clusters.province AS "Province",
                location_clusters.postal_code AS "Postal Code",
                location_clusters.email_domain AS "Email Domain",
                (
                    SELECT COUNT(*)
                    FROM sales
                    WHERE sales.pharmacy_id = pharmacies.id
                ) AS "Sales Count",
                to_char(timezone('Asia/Kathmandu', pharmacies.updated_at), 'YYYY-MM-DD HH24:MI') AS "Updated At"
            FROM pharmacies
            INNER JOIN hospitals ON hospitals.id = pharmacies.hospital_id
            INNER JOIN tenants ON tenants.id = hospitals.tenant_id
            INNER JOIN location_clusters ON location_clusters.id = pharmacies.location_cluster_id
        SQL;

        $query = $this->interpolateQuery(
            $pdo,
            $selectSql.$whereClause.' ORDER BY pharmacies.created_at DESC, pharmacies.id DESC',
            $bindings
        );

        $path = $this->quoteValue($pdo, $absolutePath);
        DB::statement("COPY ({$query}) TO {$path} WITH (FORMAT CSV, HEADER TRUE)");
    }

    private function findOrCreateLocationCluster(array $data): int
    {
        $existingId = DB::scalar(
            <<<'SQL'
                SELECT id
                FROM location_clusters
                WHERE area = ?
                    AND city = ?
                    AND district = ?
                    AND province = ?
                    AND postal_code = ?
                    AND email_domain = ?
                LIMIT 1
            SQL,
            [
                $data['area'],
                $data['city'],
                $data['district'],
                $data['province'],
                $data['postal_code'],
                $data['email_domain'],
            ]
        );

        if ($existingId) {
            return (int) $existingId;
        }

        $row = DB::selectOne(
            <<<'SQL'
                INSERT INTO location_clusters (
                    area,
                    city,
                    district,
                    province,
                    postal_code,
                    email_domain,
                    created_at,
                    updated_at
                )
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                RETURNING id
            SQL,
            [
                $data['area'],
                $data['city'],
                $data['district'],
                $data['province'],
                $data['postal_code'],
                $data['email_domain'],
            ]
        );

        return (int) $row->id;
    }

    private function baseSelectSql(): string
    {
        return <<<'SQL'
            SELECT
                pharmacies.id,
                pharmacies.code,
                pharmacies.name,
                pharmacies.license_number,
                pharmacies.contact_email,
                tenants.id AS tenant_id,
                tenants.name AS tenant_name,
                hospitals.id AS hospital_id,
                hospitals.name AS hospital_name,
                location_clusters.area,
                location_clusters.city,
                location_clusters.district,
                location_clusters.province,
                location_clusters.postal_code,
                location_clusters.email_domain,
                (
                    SELECT COUNT(*)
                    FROM sales
                    WHERE sales.pharmacy_id = pharmacies.id
                ) AS sales_count,
                pharmacies.created_at,
                pharmacies.updated_at
            FROM pharmacies
            INNER JOIN hospitals ON hospitals.id = pharmacies.hospital_id
            INNER JOIN tenants ON tenants.id = hospitals.tenant_id
            INNER JOIN location_clusters ON location_clusters.id = pharmacies.location_cluster_id
        SQL;
    }

    /**
     * @param  array<int, mixed>  $bindings
     */
    private function whereClause(array $filters, array &$bindings): string
    {
        $clauses = [];

        if (($filters['tenant_id'] ?? null) !== null) {
            $clauses[] = 'tenants.id = ?';
            $bindings[] = $filters['tenant_id'];
        }

        if (($filters['hospital_id'] ?? null) !== null) {
            $clauses[] = 'hospitals.id = ?';
            $bindings[] = $filters['hospital_id'];
        }

        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $clauses[] = '(pharmacies.name ILIKE ? OR pharmacies.code ILIKE ? OR pharmacies.license_number ILIKE ?)';
            $search = '%'.$filters['search'].'%';
            $bindings[] = $search;
            $bindings[] = $search;
            $bindings[] = $search;
        }

        if ($clauses === []) {
            return '';
        }

        return ' WHERE '.implode(' AND ', $clauses);
    }

    private function hydrate(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'code' => (string) $row->code,
            'name' => (string) $row->name,
            'license_number' => (string) $row->license_number,
            'contact_email' => (string) $row->contact_email,
            'tenant_id' => (int) $row->tenant_id,
            'tenant_name' => (string) $row->tenant_name,
            'hospital_id' => (int) $row->hospital_id,
            'hospital_name' => (string) $row->hospital_name,
            'area' => (string) $row->area,
            'city' => (string) $row->city,
            'district' => (string) $row->district,
            'province' => (string) $row->province,
            'postal_code' => (string) $row->postal_code,
            'email_domain' => (string) $row->email_domain,
            'sales_count' => (int) ($row->sales_count ?? 0),
            'created_at' => isset($row->created_at) ? CarbonImmutable::parse($row->created_at)->toIso8601String() : null,
            'updated_at' => isset($row->updated_at) ? CarbonImmutable::parse($row->updated_at)->toIso8601String() : null,
        ];
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

        return StringHelper::replaceArray('?', $quotedBindings, $sql);
    }

    private function quoteValue(PDO $pdo, mixed $value): string
    {
        return $value === null ? 'NULL' : $pdo->quote((string) $value);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW pharmacy_sale_export_rows AS
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
                (sale_items.quantity * sale_items.unit_price) AS line_subtotal
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
            WITH DATA
        SQL);

        DB::statement('CREATE UNIQUE INDEX pharmacy_sale_export_rows_sale_item_id_unique ON pharmacy_sale_export_rows (sale_item_id)');
        DB::statement('CREATE INDEX pharmacy_sale_export_rows_sold_at_index ON pharmacy_sale_export_rows (sold_at)');
        DB::statement('CREATE INDEX pharmacy_sale_export_rows_tenant_sold_at_index ON pharmacy_sale_export_rows (tenant_id, sold_at)');
        DB::statement('CREATE INDEX pharmacy_sale_export_rows_hospital_sold_at_index ON pharmacy_sale_export_rows (hospital_id, sold_at)');
        DB::statement('CREATE INDEX pharmacy_sale_export_rows_pharmacy_sold_at_index ON pharmacy_sale_export_rows (pharmacy_id, sold_at)');
        DB::statement('CREATE INDEX pharmacy_sale_export_rows_category_sold_at_index ON pharmacy_sale_export_rows (category_id, sold_at)');
        DB::statement('CREATE INDEX pharmacy_sale_export_rows_supplier_sold_at_index ON pharmacy_sale_export_rows (supplier_id, sold_at)');
        DB::statement('CREATE INDEX pharmacy_sale_export_rows_payment_status_sold_at_index ON pharmacy_sale_export_rows (payment_status, sold_at)');
        DB::statement('CREATE INDEX pharmacy_sale_export_rows_cold_chain_sold_at_index ON pharmacy_sale_export_rows (is_cold_chain, sold_at)');
    }

    public function down(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS pharmacy_sale_export_rows');
    }
};

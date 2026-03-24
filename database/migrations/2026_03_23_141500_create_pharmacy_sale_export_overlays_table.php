<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE TABLE pharmacy_sale_export_overlays AS TABLE pharmacy_sale_export_rows WITH NO DATA');
        DB::statement('ALTER TABLE pharmacy_sale_export_overlays ADD PRIMARY KEY (sale_item_id)');
        DB::statement('CREATE INDEX pharmacy_sale_export_overlays_sold_at_index ON pharmacy_sale_export_overlays (sold_at)');
        DB::statement('CREATE INDEX pharmacy_sale_export_overlays_tenant_sold_at_index ON pharmacy_sale_export_overlays (tenant_id, sold_at)');
        DB::statement('CREATE INDEX pharmacy_sale_export_overlays_hospital_sold_at_index ON pharmacy_sale_export_overlays (hospital_id, sold_at)');
        DB::statement('CREATE INDEX pharmacy_sale_export_overlays_pharmacy_sold_at_index ON pharmacy_sale_export_overlays (pharmacy_id, sold_at)');
        DB::statement('CREATE INDEX pharmacy_sale_export_overlays_category_sold_at_index ON pharmacy_sale_export_overlays (category_id, sold_at)');
        DB::statement('CREATE INDEX pharmacy_sale_export_overlays_supplier_sold_at_index ON pharmacy_sale_export_overlays (supplier_id, sold_at)');
        DB::statement('CREATE INDEX pharmacy_sale_export_overlays_payment_status_sold_at_index ON pharmacy_sale_export_overlays (payment_status, sold_at)');
        DB::statement('CREATE INDEX pharmacy_sale_export_overlays_cold_chain_sold_at_index ON pharmacy_sale_export_overlays (is_cold_chain, sold_at)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS pharmacy_sale_export_overlays');
    }
};

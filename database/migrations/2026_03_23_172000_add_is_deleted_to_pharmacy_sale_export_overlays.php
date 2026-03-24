<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE pharmacy_sale_export_overlays ADD COLUMN IF NOT EXISTS is_deleted BOOLEAN NOT NULL DEFAULT FALSE');
        DB::statement('CREATE INDEX IF NOT EXISTS pharmacy_sale_export_overlays_deleted_index ON pharmacy_sale_export_overlays (is_deleted, sold_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS pharmacy_sale_export_overlays_deleted_index');
        DB::statement('ALTER TABLE pharmacy_sale_export_overlays DROP COLUMN IF EXISTS is_deleted');
    }
};

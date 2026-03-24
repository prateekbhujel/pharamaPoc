<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshPharmacySalesReportView extends Command
{
    protected $signature = 'reporting:refresh-sale-view {--concurrently : Use Postgres concurrent refresh}';

    protected $description = 'Refresh the materialized sales export view used by the reporting POC';

    public function handle(): int
    {
        $statement = $this->option('concurrently')
            ? 'REFRESH MATERIALIZED VIEW CONCURRENTLY pharmacy_sale_export_rows'
            : 'REFRESH MATERIALIZED VIEW pharmacy_sale_export_rows';

        $this->components->info('Refreshing pharmacy_sale_export_rows...');

        DB::statement($statement);

        $count = DB::table('pharmacy_sale_export_rows')->count();

        $this->components->info("Refresh complete. {$count} report rows are ready.");

        return self::SUCCESS;
    }
}

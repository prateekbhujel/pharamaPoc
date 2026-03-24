<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class PreparePharamaPoc extends Command
{
    protected $signature = 'prepare
        {--sales=45000 : Demo sales to seed before scaling}
        {--rows=1000000 : Final report row target after scaling}
        {--organizations=200 : Number of organizations to seed}
        {--hospitals-min=2 : Minimum hospitals per organization}
        {--hospitals-max=4 : Maximum hospitals per organization}
        {--pharmacies-min=2 : Minimum pharmacies per hospital}
        {--pharmacies-max=4 : Maximum pharmacies per hospital}
        {--batch=500000 : Batch size for bulk scale inserts}
        {--skip-build : Skip npm run build}
        {--skip-scale : Skip reporting:seed-scale}';

    protected $description = 'Prepare pharamaPOC in one command: migrate, seed, docs, scale, and build';

    public function handle(): int
    {
        $sales = max(1_000, (int) $this->option('sales'));
        $rows = max($sales, (int) $this->option('rows'));
        $batch = max(1_000, (int) $this->option('batch'));

        config([
            'reporting.demo_sales_target' => $sales,
            'reporting.seed_organization_count' => max(1, (int) $this->option('organizations')),
            'reporting.seed_hospital_min' => max(1, (int) $this->option('hospitals-min')),
            'reporting.seed_hospital_max' => max((int) $this->option('hospitals-min'), (int) $this->option('hospitals-max')),
            'reporting.seed_pharmacy_min' => max(1, (int) $this->option('pharmacies-min')),
            'reporting.seed_pharmacy_max' => max((int) $this->option('pharmacies-min'), (int) $this->option('pharmacies-max')),
            'reporting.bulk_target_rows' => $rows,
            'reporting.bulk_batch_size' => $batch,
        ]);

        $this->components->info('Preparing pharamaPOC...');
        $this->components->info("Demo sales target: {$sales}");
        $this->components->info("Final row target: {$rows}");

        $this->call('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ]);

        $this->call('docs:generate');

        if (! $this->option('skip-scale') && $rows > $sales) {
            $this->call('reporting:seed-scale', [
                'rows' => $rows,
                '--batch' => $batch,
            ]);
        }

        if (! $this->option('skip-build')) {
            $build = Process::path(base_path())->timeout(600)->run('npm run build');

            if ($build->failed()) {
                $this->components->error('npm run build failed.');
                $this->line($build->errorOutput());

                return self::FAILURE;
            }

            $this->line(trim($build->output()));
        }

        $this->newLine();
        $this->components->info('pharamaPOC is ready.');
        $this->line('Login: platform.admin / password');
        $this->line('Login: org001.admin / password');
        $this->line('Login: hospital001.admin / password');

        return self::SUCCESS;
    }
}

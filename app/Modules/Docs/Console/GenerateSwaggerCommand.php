<?php

namespace App\Modules\Docs\Console;

use App\Modules\Docs\Services\SwaggerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateSwaggerCommand extends Command
{
    protected $signature = 'docs:generate';

    protected $description = 'Generate the Swagger/OpenAPI file for API v1.';

    public function handle(SwaggerService $swaggerService): int
    {
        $directory = public_path('docs');
        $path = $directory.'/openapi-v1.json';

        File::ensureDirectoryExists($directory);
        File::put(
            $path,
            json_encode($swaggerService->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->components->info("Swagger spec generated at {$path}");

        return self::SUCCESS;
    }
}

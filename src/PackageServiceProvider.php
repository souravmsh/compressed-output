<?php

namespace Souravmsh\CompressedOutput;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use Souravmsh\CompressedOutput\Http\Middleware\CompressedOutputMiddleware;

class PackageServiceProvider extends ServiceProvider
{
    public function boot(Kernel $kernel)
    {
        // Optionally push middleware based on config
        if (config('compressed-output.enable', false)) {
            $kernel->pushMiddleware(CompressedOutputMiddleware::class);
        }

        // Publish configuration file
        $this->publishes([
            __DIR__ . '/config/compressed-output.php' => config_path('compressed-output.php'),
        ], 'compressed-output-config');
    }

    public function register()
    {
        // Merge configuration
        $this->mergeConfigFrom(__DIR__ . '/config/compressed-output.php', 'compressed-output');
    }
}

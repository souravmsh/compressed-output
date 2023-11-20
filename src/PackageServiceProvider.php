<?php 

namespace Souravmsh\CompressedOutput;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use Souravmsh\CompressedOutput\Http\Middleware\CompressedOutputMiddleware;


class PackageServiceProvider extends ServiceProvider
{
    public function boot(Kernel $kernel)
    {
        $kernel->pushMiddleware(CompressedOutputMiddleware::class);
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/config/compressed-output.php', 'compressed-output');
    } 
}
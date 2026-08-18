<?php

namespace Mkaram\Snap;

use Illuminate\Support\ServiceProvider;
use Mkaram\Snap\Commands\SnapInstallCommand;
use Mkaram\Snap\Commands\SnapListCommand;
use Mkaram\Snap\Commands\SnapMcpCommand;
use Mkaram\Snap\Commands\SnapPatternCommand;

class SnapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/snap.php',
            'snap'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SnapPatternCommand::class,
                SnapInstallCommand::class,
                SnapListCommand::class,
                SnapMcpCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/snap.php' => config_path('snap.php'),
            ], 'snap-config');
        }
    }
}
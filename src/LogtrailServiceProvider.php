<?php

namespace Xetreon\LaravelLog;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Xetreon\LaravelLog\Console\TestLoggerCommand;

class LogtrailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/logtrail.php', 'logtrail');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/logtrail.php' => config_path('logtrail.php'),
        ], 'config');

        // Register our custom log channel
        Log::extend('logtrail', function ($app, array $config) {
            return new LogtrailLogger($config);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([TestLoggerCommand::class]);
        }
    }
}

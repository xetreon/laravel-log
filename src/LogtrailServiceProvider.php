<?php

namespace Xetreon\LaravelLog;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Xetreon\LaravelLog\Console\TestLoggerCommand;
use Illuminate\Support\Facades\Blade;
class LogtrailServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/logtrail.php', 'logtrail');
        Blade::precompiler(function (string $value) {
            if (!config('logtrail.blade_sourcemap', true)) {
                return $value;
            }
            $value = str_replace(["\r\n", "\r"], "\n", $value);
            $lines = explode("\n", $value);

            foreach ($lines as $i => $line) {
                $lineNo = $i + 1;
                $lines[$i] = "<?php /*LT_LINE:{$lineNo}*/ ?>".$line;
            }

            return implode("\n", $lines);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([TestLoggerCommand::class]);
        }
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/logtrail.php' => config_path('logtrail.php'),
        ], 'config');

        Log::extend('logtrail', function ($app, array $config) {
            return new LogtrailLogger($config);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([TestLoggerCommand::class]);
        }
    }
}

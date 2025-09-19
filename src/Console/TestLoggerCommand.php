<?php

namespace Xetreon\LaravelLog\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestLoggerCommand extends Command
{
    protected $signature = 'logtrail:test';
    protected $description = 'Send a test log to Xetreon Logtrail';

    public function handle(): int
    {
        $this->info('Sending test log...');
        Log::channel('logtrail')->info('✅ Logtrail test message', ['test' => true]);
        $this->info('Done! Check Logtrail dashboard.');
        return self::SUCCESS;
    }
}

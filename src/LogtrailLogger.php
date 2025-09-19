<?php

namespace Xetreon\LaravelLog;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\AbstractLogger;
use Xetreon\LaravelLog\Http\LogReporter;
use Xetreon\LaravelLog\Formatter\LogtrailFormatter;
class LogtrailLogger extends AbstractLogger
{
    protected LogReporter $reporter;
    protected LogtrailFormatter $formatter;

    public function __construct(array $config)
    {
        $this->reporter = new LogReporter($config);
    }

    /**
     * @throws GuzzleException
     */
    public function log($level, $message, array $context = []): void
    {
        $formatted = $this->formatter->format($level, $message, $context);

        $authorization = config('logtrail.api_key').":".config('logtrail.api_secret').":".config('logtrail.environment');
        $authorization = rtrim(base64_encode($authorization), "=");

        $this->reporter->send($formatted, $authorization);
    }
}

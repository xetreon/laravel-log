<?php

namespace Xetreon\LaravelLog;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\AbstractLogger;
use Xetreon\LaravelLog\Http\LogReporter;
use Xetreon\LaravelLog\Formatter\LogtrailFormatter;
use Exception;
use Xetreon\LaravelLog\Support\LogtrailContext;
use Xetreon\LaravelLog\Support\SensitiveDataMasker;

class LogtrailLogger extends AbstractLogger
{
    protected LogReporter $reporter;
    protected LogtrailFormatter $formatter;

    public function __construct(array $config)
    {
        $this->reporter = new LogReporter($config);
        $this->formatter = new LogtrailFormatter();
    }

    /**
     * @throws GuzzleException
     */
    public function log($level, $message, array $context = []): void
    {
        $req = LogtrailContext::collectRequestContext();
        $version = LogtrailContext::getGitVersion();

        $requestHeader = SensitiveDataMasker::maskHeaders($req['request_header'] ?? []);
        $requestBody = SensitiveDataMasker::maskBody($req['request_body'] ?? []);

        $formatted = $this->formatter->format(
            (string) $level,
            (string) $message,
            $context,
            $requestHeader,
            $requestBody
        );
        $formatted['request'] = $req['request'] ?? [];
        $formatted['version'] = $version;

        $authorization = LogtrailContext::buildAuthorization();
        try {
            $this->reporter->send($formatted, $authorization);
        } catch (Exception) {
            // Dont do anything
        }
    }
}

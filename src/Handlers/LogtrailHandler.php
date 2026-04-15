<?php

namespace Xetreon\LaravelLog\Handlers;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Xetreon\LaravelLog\Formatter\LogtrailFormatter;
use Xetreon\LaravelLog\Http\LogReporter;
use Xetreon\LaravelLog\Support\LogtrailContext;
use Xetreon\LaravelLog\Support\SensitiveDataMasker;

class LogtrailHandler extends AbstractProcessingHandler
{
    protected LogReporter $reporter;
    protected LogtrailFormatter $payloadFormatter;
    protected array $config;

    public function __construct(array $config, int|Level $level = Level::Debug, bool $bubble = true)
    {
        $this->config = $config;
        $this->reporter = new LogReporter($config);
        $this->payloadFormatter = new LogtrailFormatter();

        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $req = LogtrailContext::collectRequestContext();
        $version = LogtrailContext::getGitVersion();

        $levelName = strtolower($record->level->getName());
        $message = (string) $record->message;
        $context = is_array($record->context) ? $record->context : [];

        $requestHeader = SensitiveDataMasker::maskHeaders($req['request_header'] ?? []);
        $requestBody = SensitiveDataMasker::maskBody($req['request_body'] ?? []);

        $formatted = $this->payloadFormatter->format(
            $levelName,
            $message,
            $context,
            $requestHeader,
            $requestBody
        );
        $formatted['request'] = $req['request'] ?? [];
        $formatted['version'] = $version;

        $authorization = LogtrailContext::buildAuthorization();

        try {
            $this->reporter->send($formatted, $authorization);
        } catch (GuzzleException|Exception) {
        }
    }
}

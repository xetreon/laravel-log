<?php

namespace Xetreon\LaravelLog\Handlers;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Xetreon\LaravelLog\Formatter\LogtrailFormatter;
use Xetreon\LaravelLog\Http\LogReporter;

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
        $requestData = [];
        $requestHeader = [];
        $requestBody = [];

        try {
            $request = app(Request::class);
            $route = $request->route();

            if (!empty($route)) {
                $action = $route->getActionName();

                $requestData = [
                    'method' => $request->getMethod(),
                    'action' => $action,
                    'url'    => $route->uri(),
                    'agent'  => $request->header('User-Agent'),
                    'ip'     => $request->ip(),
                ];
            }

            $requestHeader = $request->headers->all();
            $requestBody = $request->all();
        } catch (Exception) {
            $requestData = [];
        }

        $version = null;
        $basePath = base_path('.git');

        if (is_dir($basePath)) {
            try {
                $v = shell_exec('git rev-parse --short HEAD 2>/dev/null');
                $version = is_string($v) ? trim($v) : null;
                $version = $version !== '' ? $version : null;
            } catch (Exception) {
                $version = null;
            }
        }

        $levelName = strtolower($record->level->getName());
        $message = (string) $record->message;
        $context = is_array($record->context) ? $record->context : [];

        $formatted = $this->payloadFormatter->format($levelName, $message, $context, $requestHeader, $requestBody);
        $formatted['request'] = $requestData;
        $formatted['version'] = $version;


        $authorization = config('logtrail.api_key') . ':' . config('logtrail.api_secret') . ':' . config('logtrail.environment');
        $authorization = rtrim(base64_encode($authorization), '=');

        try {
            $this->reporter->send($formatted, $authorization);
        } catch (GuzzleException|Exception) {
        }
    }
}

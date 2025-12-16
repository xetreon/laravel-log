<?php

namespace Xetreon\LaravelLog;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\AbstractLogger;
use Xetreon\LaravelLog\Http\LogReporter;
use Xetreon\LaravelLog\Formatter\LogtrailFormatter;
use Illuminate\Http\Request;
use Exception;

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
        $requestData = [];
        $requestHeader = [];
        $requestBody = [];
        try {
            $request = app(Request::class);
            $route = $request->route();

            if(!empty($route)) {
                $action = $route->getActionName();
                $requestData = [
                    'method'       => $request->getMethod(),
                    'action'       => $action,
                    'url'          => $route->uri(),
                    'agent' => $request->header('User-Agent'),
                    'ip' => $request->ip(),
                ];
            }

            $requestHeader = $request->headers->all();
            $requestBody = $request->all();
        } catch (Exception $e)
        {
            $requestData = [];
        }

        $basePath = base_path('.git');
        $version = null;
        if (is_dir($basePath)) {
            try {
                $version = trim(shell_exec('git rev-parse --short HEAD 2>/dev/null'));
            } catch (Exception $e) {
                $version = null;
            }
        }

        $formatted = $this->formatter->format($level, $message, $context, $requestHeader, $requestBody);
        $formatted['request'] = $requestData;
        $formatted['version'] = $version;


        $authorization = config('logtrail.api_key').":".config('logtrail.api_secret').":".config('logtrail.environment');
        $authorization = rtrim(base64_encode($authorization), "=");
        try {
            $this->reporter->send($formatted, $authorization);
        } Catch (Exception $e) {
            // Dont do anything
        }
    }
}

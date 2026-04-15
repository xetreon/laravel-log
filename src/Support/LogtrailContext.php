<?php

namespace Xetreon\LaravelLog\Support;

use Exception;
use Illuminate\Http\Request;

class LogtrailContext
{
    private static ?string $gitVersion = null;
    private static bool $gitVersionLoaded = false;

    /**
     * @return array{request: array, request_header: array, request_body: array}
     */
    public static function collectRequestContext(): array
    {
        $requestData = [];
        $requestHeader = [];
        $requestBody = [];

        try {
            /** @var Request $request */
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
            // ignore: logging may be called without an active request/container
        }

        return [
            'request' => $requestData,
            'request_header' => $requestHeader,
            'request_body' => $requestBody,
        ];
    }

    public static function buildAuthorization(): string
    {
        $authorization = config('logtrail.api_key') . ':' . config('logtrail.api_secret') . ':' . config('logtrail.environment');
        return rtrim(base64_encode($authorization), '=');
    }

    public static function getGitVersion(): ?string
    {
        if (self::$gitVersionLoaded) {
            return self::$gitVersion;
        }

        self::$gitVersionLoaded = true;
        self::$gitVersion = null;

        try {
            $basePath = base_path('.git');
            if (!is_dir($basePath)) {
                return null;
            }

            $v = shell_exec('git rev-parse --short HEAD 2>/dev/null');
            $version = is_string($v) ? trim($v) : '';
            self::$gitVersion = $version !== '' ? $version : null;
        } catch (Exception) {
            self::$gitVersion = null;
        }

        return self::$gitVersion;
    }
}


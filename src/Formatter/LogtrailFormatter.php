<?php

namespace Xetreon\LaravelLog\Formatter;

use Illuminate\Database\QueryException;
use Throwable;

class LogtrailFormatter
{
    /**
     * Build and normalize the payload for Logtrail.
     */
    public function format(string $level, string $message, array $context = [], $requestHeader = [], $requestBody = []): array
    {
        $context = $this->normalizeContext($context);
        if(!empty($context)) {
            if(!empty($requestHeader)) {
                $context['request_header'] = $requestHeader;
            }
            if(!empty($requestBody)) {
                $context['request_body'] = $requestBody;
            }
        }
        $payload = [
            'level'     => $level,
            'message'   => $message,
            'context'   => $context,
            'timestamp' => now()->toIso8601String()
        ];

        $file = $line = "";
        if(!empty($context['exception'])) {
            $file = $context['exception']['file'];
            $line = $context['exception']['line'];
        }
        $sign = $level.$message.$file.$line;
        $payload['signature'] = hash_hmac('md5', $sign, config('logtrail.api_secret'));

        return $payload;
    }

    /**
     * Normalize context data (e.g., exception objects).
     */
    protected function normalizeContext(array $context): array
    {
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {

            $e = $context['exception'];

            $trace = $e->getTrace();
            $compressed = [];

            foreach ($trace as $frame) {
                $item = [];
                if (isset($frame['file'])) {
                    $item['f'] = $this->relativePath($frame['file']);

                    // Check if file is in vendor folder or any library folder
                    $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $frame['file']);
                    $basePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, base_path());

                    // If file is outside app path or inside vendor, mark as library
                    if (str_starts_with($normalizedPath, $basePath . DIRECTORY_SEPARATOR . 'vendor')) {
                        $item['lp'] = 1;
                    } else {
                        $item['lp'] = 0;
                    }
                } else {
                    $item['lp'] = 0;
                }

                if (isset($frame['line'])) $item['l'] = $frame['line'];
                if (isset($frame['function'])) $item['fn'] = $frame['function'];
                if (isset($frame['class'])) $item['cl'] = $frame['class'];
                if (!empty($frame['file']) && !empty($frame['line']) && is_readable($frame['file'])) {
                    $item['s'] = $this->getCodeSnippet($frame['file'], (int) $frame['line'], 8);
                }

                $compressed[] = $item;
            }

            $context['exception'] = [
                'class'   => get_class($e),
                'message' => $e->getMessage(),
                'file'    => $this->relativePath($e->getFile()),
                'line'    => $e->getLine(),
                'trace'   => $compressed,
            ];
            if ($e instanceof QueryException) {
                $context['exception']['sql_error'] = $e->getSql();       // The actual SQL
                $context['exception']['bindings'] = $e->getBindings(); // Bound parameters
            } elseif (str_contains($e->getMessage(), 'SQLSTATE')) {
                // Fallback: try to parse SQL from message string
                if (preg_match('/SQLSTATE\[[^\]]+\]: .*?: (select|insert|update|delete).*$/i', $e->getMessage(), $matches)) {
                    $context['exception']['sql_error'] = $matches[0];
                }
            }
        }
        if(!empty($context['exception']['sql_error'])) {
            if (str_contains($context['exception']['sql_error'], 'SQLSTATE') && preg_match('/SQL: (.+?)\)$/i', $context['exception']['sql_error'], $matchData)) {
                $context['exception']['sql'] = $matchData[1];
            }
        }
        return $context;
    }

    /**
     * @param string $path
     * @return string
     */
    protected function relativePath(string $path): string
    {
        $basePath = base_path();

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $normalizedBase = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $basePath);

        if (str_starts_with($normalized, $normalizedBase)) {
            return ltrim(substr($normalized, strlen($normalizedBase)), DIRECTORY_SEPARATOR);
        }

        return $path;
    }

    /**
     * @param string $filePath
     * @param int $errorLine
     * @param int $padding
     * @return array|null
     */
    protected function getCodeSnippet(string $filePath, int $errorLine, int $padding = 5): ?array
    {
        $lines = @file($filePath, FILE_IGNORE_NEW_LINES);
        if (!$lines) {
            return null;
        }

        $start = max(0, $errorLine - $padding - 1);
        $end   = min(count($lines), $errorLine + $padding);

        $snippet = [];
        for ($i = $start; $i < $end; $i++) {
            $snippet[] = [
                'l' => $i + 1,
                'c' => $lines[$i],
                'h' => ($i + 1) === $errorLine,
            ];
        }

        return $snippet;
    }
}
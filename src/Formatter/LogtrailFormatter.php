<?php

namespace Xetreon\LaravelLog\Formatter;

use Throwable;
class LogtrailFormatter
{
    /**
     * Build and normalize the payload for Logtrail.
     */
    public function format(string $level, string $message, array $context = []): array
    {
        $context = $this->normalizeContext($context);

        $payload = [
            'level'     => $level,
            'message'   => $message,
            'context'   => $context,
            'timestamp' => now()->toIso8601String(),
        ];

        // Add signature for authentication
        $payload['signature'] = hash_hmac(
            'sha256',
            json_encode([$payload['level'], $payload['message'], $payload['timestamp']]),
            config('logtrail.api_secret')
        );

        return $payload;
    }

    /**
     * Normalize context data (e.g., exception objects).
     */
    protected function normalizeContext(array $context): array
    {
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $e = $context['exception'];
            $context['exception'] = [
                'class'   => get_class($e),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ];
        }

        return $context;
    }
}

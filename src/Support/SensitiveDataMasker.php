<?php

namespace Xetreon\LaravelLog\Support;

class SensitiveDataMasker
{
    private const MASK = '[REDACTED]';

    /**
     * @param array $headers Typically Symfony HeaderBag::all() result (key => array<string>)
     * @return array
     */
    public static function maskHeaders(array $headers): array
    {
        $masked = [];

        foreach ($headers as $key => $value) {
            $k = is_string($key) ? strtolower($key) : (string) $key;

            if (
                $k === 'authorization' ||
                $k === 'cookie' ||
                $k === 'set-cookie' ||
                str_contains($k, 'token') ||
                str_contains($k, 'api-key') ||
                str_contains($k, 'apikey') ||
                str_contains($k, 'secret') ||
                str_contains($k, 'key')
            ) {
                $masked[$key] = self::maskHeaderValue($value);
                continue;
            }

            $masked[$key] = $value;
        }

        return $masked;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function maskHeaderValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn () => self::MASK, $value);
        }

        if (is_string($value) || is_numeric($value)) {
            return self::MASK;
        }

        return self::MASK;
    }

    /**
     * Recursively masks sensitive keys in arrays.
     * @param mixed $data
     * @return mixed
     */
    public static function maskBody(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $out = [];
        foreach ($data as $key => $value) {
            $k = is_string($key) ? strtolower($key) : (string) $key;

            if (self::isSensitiveKey($k)) {
                $out[$key] = self::MASK;
                continue;
            }

            $out[$key] = is_array($value) ? self::maskBody($value) : $value;
        }

        return $out;
    }

    private static function isSensitiveKey(string $key): bool
    {
        return in_array($key, [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'access_token',
            'refresh_token',
            'api_key',
            'api_secret',
            'secret',
            'authorization',
            'cookie',
        ], true);
    }
}


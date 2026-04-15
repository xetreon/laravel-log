<?php

namespace Xetreon\LaravelLog\Formatter;

use Illuminate\Database\QueryException;
use Throwable;

class LogtrailFormatter
{
    /**
     * @param string $level
     * @param string $message
     * @param array $context
     * @param array $requestHeader
     * @param array $requestBody
     * @return array
     */
    public function format(string $level, string $message, array $context = [], array $requestHeader = [], array $requestBody = []): array
    {
        $context = $this->normalizeContext($context);

        // Always attach request metadata when present, even if context is otherwise empty.
        if (!empty($requestHeader)) {
            $context['request_header'] = $requestHeader;
        }
        if (!empty($requestBody)) {
            $context['request_body'] = $requestBody;
        }

        $payload = [
            'level'     => $level,
            'message'   => $message,
            'context'   => $context,
            'timestamp' => now()->toIso8601String(),
        ];

        $file = $line = "";
        if (!empty($context['exception'])) {
            $file = $context['exception']['file'] ?? '';
            $line = $context['exception']['line'] ?? '';
        }

        $sign = $level . $message . $file . $line;
        $payload['signature'] = hash_hmac('md5', $sign, config('logtrail.api_secret'));

        return $payload;
    }

    /**
     * Normalize context data (e.g., exception objects).
     * @param array $context
     * @return array
     */
    protected function normalizeContext(array $context): array
    {
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $e = $context['exception'];

            $trace = $e->getTrace();
            $compressed = [];

            foreach ($trace as $frame) {
                $item = [];

                $frameFile = $frame['file'] ?? null;
                $frameLine = isset($frame['line']) ? (int) $frame['line'] : null;

                $sourceFile = $frameFile;
                $mappedLine = $frameLine;

                if ($frameFile && $frameLine && $this->isCompiledBladeView($frameFile)) {
                    $bladeFile = $this->resolveBladeSourceFromCompiled($frameFile);
                    if ($bladeFile) {
                        $sourceFile = $bladeFile;

                        $bladeLine = $this->mapCompiledLineToBladeLine($frameFile, $frameLine);
                        if ($bladeLine) {
                            $mappedLine = $bladeLine;
                        }
                    }
                }

                if ($sourceFile) {
                    $item['f'] = $this->relativePath($sourceFile);

                    $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sourceFile);
                    $basePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, base_path());

                    $item['lp'] = str_starts_with($normalizedPath, $basePath . DIRECTORY_SEPARATOR . 'vendor') ? 1 : 0;
                } else {
                    $item['lp'] = 0;
                }

                if ($mappedLine !== null) $item['l'] = $mappedLine;
                if (isset($frame['function'])) $item['fn'] = $frame['function'];
                if (isset($frame['class'])) $item['cl'] = $frame['class'];

                if ($sourceFile && $mappedLine && is_readable($sourceFile)) {
                    $item['s'] = $this->getCodeSnippetClamped($sourceFile, $mappedLine, 8);
                }

                $compressed[] = $item;
            }

            $absExcFile = $e->getFile();
            $excLine    = $e->getLine();

            if ($absExcFile && $excLine && $this->isCompiledBladeView($absExcFile)) {
                $bladeFile = $this->resolveBladeSourceFromCompiled($absExcFile);
                if ($bladeFile) {
                    // swap to real blade path
                    $absExcFile = $bladeFile;

                    // map compiled line to blade line using sourcemap markers
                    $bladeLine = $this->mapCompiledLineToBladeLine($e->getFile(), $excLine);
                    if ($bladeLine) {
                        $excLine = $bladeLine;
                    }
                }
            }

            $context['exception'] = [
                'class'   => get_class($e),
                'message' => $e->getMessage(),
                'file'    => $this->relativePath($absExcFile),
                'line'    => $excLine,
                'trace'   => $compressed,
            ];

            if ($absExcFile && is_readable($absExcFile)) {
                $primarySnippet = $this->getCodeSnippetClamped($absExcFile, $excLine, 8);

                $context['exception']['primary'] = [
                    'f'  => $this->relativePath($absExcFile),
                    'l'  => $excLine,
                    'lp' => 0,
                    's'  => $primarySnippet,
                ];

                // Insert a synthetic first trace frame so the UI picks it first
                array_unshift($context['exception']['trace'], [
                    'f'  => $this->relativePath($absExcFile),
                    'l'  => $excLine,
                    'fn' => 'view',
                    'cl' => 'blade',
                    'lp' => 0,
                    's'  => $primarySnippet,
                ]);
            }

            // ---- Optional SQL parsing (kept safe; only if QueryException exists in the app) ----
            if (class_exists(QueryException::class) && $e instanceof QueryException) {
                $context['exception']['sql_error'] = $e->getSql();
                $context['exception']['bindings']  = $e->getBindings();
            } elseif (str_contains($e->getMessage(), 'SQLSTATE')) {
                if (preg_match('/SQLSTATE\[[^]]+]: .*?: (select|insert|update|delete).*$/i', $e->getMessage(), $matches)) {
                    $context['exception']['sql_error'] = $matches[0];
                }
            }

            if (!empty($context['exception']['sql_error'])) {
                if (
                    str_contains($context['exception']['sql_error'], 'SQLSTATE') &&
                    preg_match('/SQL: (.+?)\)$/i', $context['exception']['sql_error'], $matchData)
                ) {
                    $context['exception']['sql'] = $matchData[1];
                }
            }
        }

        return $context;
    }

    /**
     * @param string $path
     * @return bool
     */
    protected function isCompiledBladeView(string $path): bool
    {
        $normalized = str_replace(['\\'], '/', $path);
        return str_contains($normalized, '/storage/framework/views/') && str_ends_with($normalized, '.php');
    }

    /**
     * Extract real .blade.php path from compiled view file.
     * @param string $compiledPath
     * @return string|null
     */
    protected function resolveBladeSourceFromCompiled(string $compiledPath): ?string
    {
        if (!is_readable($compiledPath)) return null;

        $tail = $this->readFileTail($compiledPath, 8192);
        if ($tail && preg_match('#\*\*PATH\s+(.+?\.blade\.php)\s+ENDPATH\*\*#', $tail, $m)) {
            return trim($m[1]);
        }

        // Fallback: sometimes at the top as /* ...blade.php */
        $head = @file_get_contents($compiledPath, false, null, 0, 4096);
        if ($head && preg_match('#/\*\s*(.+?\.blade\.php)\s*\*/#', $head, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * @param string $path
     * @param int $bytes
     * @return string|null
     */
    protected function readFileTail(string $path, int $bytes): ?string
    {
        $size = @filesize($path);
        if (!$size || $size <= 0) return null;

        $fh = @fopen($path, 'rb');
        if (!$fh) return null;

        $seek = max(0, $size - $bytes);
        @fseek($fh, $seek);
        $data = @stream_get_contents($fh);
        @fclose($fh);

        return $data === false ? null : $data;
    }

    /**
     * Convert absolute paths to relative to base_path() for clean display.
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
     * Extracts a few lines around the given line number from a file,
     * but clamps the line number into file bounds.
     * @param string $filePath
     * @param int $errorLine
     * @param int $padding
     * @return array|null
     */
    protected function getCodeSnippetClamped(string $filePath, int $errorLine, int $padding = 5): ?array
    {
        $range = $this->readLineRange($filePath, $errorLine, $padding);
        if (!$range) return null;

        [$startLine, $lines] = $range;
        $snippet = [];
        foreach ($lines as $idx => $content) {
            $lineNo = $startLine + $idx;
            $snippet[] = [
                'l' => $lineNo,
                'c' => $content,
                'h' => $lineNo === $errorLine,
            ];
        }

        return $snippet;
    }


    /**
     * Uses LT_LINE markers injected at compile-time to map compiled line -> blade line.
     * @param string $compiledPath
     * @param int $compiledLine
     * @return int|null
     */
    protected function mapCompiledLineToBladeLine(string $compiledPath, int $compiledLine): ?int
    {
        if (!is_readable($compiledPath)) return null;

        // Scan a bounded window backwards to avoid loading big compiled views.
        // In practice, the nearest LT_LINE marker is very close to the compiled line.
        $window = 250;
        $start = max(1, $compiledLine - $window);

        $range = $this->readLineRangeAbsolute($compiledPath, $start, $compiledLine);
        if (!$range) return null;

        [$startLine, $lines] = $range;
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (preg_match('/LT_LINE:(\d+)/', $lines[$i], $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }

    /**
     * Reads a line window around the target line (1-indexed).
     * Returns [startLine, lines[]] or null on failure.
     *
     * @return array{0:int,1:array<int,string>}|null
     */
    protected function readLineRange(string $filePath, int $targetLine, int $padding): ?array
    {
        if (!is_readable($filePath)) return null;

        $targetLine = max(1, $targetLine);
        $startLine = max(1, $targetLine - $padding);
        $endLine = $targetLine + $padding;

        return $this->readLineRangeAbsolute($filePath, $startLine, $endLine);
    }

    /**
     * Reads lines from $startLine..$endLine (both inclusive), 1-indexed.
     * Returns [startLine, lines[]] or null.
     *
     * @return array{0:int,1:array<int,string>}|null
     */
    protected function readLineRangeAbsolute(string $filePath, int $startLine, int $endLine): ?array
    {
        if (!is_readable($filePath)) return null;
        $startLine = max(1, $startLine);
        $endLine = max($startLine, $endLine);

        $fh = @fopen($filePath, 'rb');
        if (!$fh) return null;

        $lines = [];
        $lineNo = 0;

        while (!feof($fh)) {
            $line = fgets($fh);
            if ($line === false) break;

            $lineNo++;
            if ($lineNo < $startLine) continue;
            if ($lineNo > $endLine) break;

            $lines[] = rtrim($line, "\r\n");
        }

        @fclose($fh);

        if (empty($lines)) return null;
        return [$startLine, $lines];
    }
}

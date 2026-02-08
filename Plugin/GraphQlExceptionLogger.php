<?php

declare(strict_types=1);

/**
 * Plugin to intercept and log GraphQL exceptions for Tapbuy requests
 *
 * This plugin hooks into Magento's GraphQL error handling to automatically
 * log all exceptions that occur during Tapbuy-initiated requests (identified
 * by X-Tapbuy-Call header). The trace ID is automatically included via the
 * TraceIdProcessor.
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Plugin;

use GraphQL\Error\Error;
use Magento\Framework\GraphQl\Query\ErrorHandlerInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;
use Tapbuy\RedirectTracking\Api\TapbuyRequestDetectorInterface;

class GraphQlExceptionLogger
{
    /**
     * @var TapbuyRequestDetectorInterface
     */
    private TapbuyRequestDetectorInterface $tapbuyRequestDetector;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param TapbuyRequestDetectorInterface $tapbuyRequestDetector
     * @param LoggerInterface $logger
     */
    public function __construct(
        TapbuyRequestDetectorInterface $tapbuyRequestDetector,
        LoggerInterface $logger
    ) {
        $this->tapbuyRequestDetector = $tapbuyRequestDetector;
        $this->logger = $logger;
    }

    /**
     * Log exceptions before they are handled by GraphQL error handler
     *
     * @param ErrorHandlerInterface $subject
     * @param array $errors
     * @param callable $formatter
     * @return array
     */
    public function beforeHandle(
        ErrorHandlerInterface $subject,
        array $errors,
        callable $formatter
    ): array {
        // Check if this is a Tapbuy request
        $isTapbuyCall = $this->tapbuyRequestDetector->isTapbuyCall();

        // Only process errors for Tapbuy requests
        if (!$isTapbuyCall) {
            return [$errors, $formatter];
        }

        // Log each error
        foreach ($errors as $error) {
            try {
                // Handle GraphQL Error objects and Throwables
                $message = '';
                $errorContext = [
                    'request_uri' => $this->tapbuyRequestDetector->getRequestUri(),
                    'is_tapbuy_request' => true,
                ];

                if ($error instanceof Error) {
                    $message = $error->getMessage();
                    $errorContext['graphql_error'] = true;

                    // Extract underlying exception if available
                    $previousError = $error->getPrevious();
                    if ($previousError instanceof \Throwable) {
                        // Store in nested structure for FetchLogs compatibility
                        $errorContext['exception'] = [
                            'class' => get_class($previousError),
                            'message' => $previousError->getMessage(),
                            'code' => $previousError->getCode(),
                            'file' => $this->normalizePath($previousError->getFile()),
                            'line' => $previousError->getLine(),
                            'stacktrace' => $previousError->getTraceAsString(),
                            'stacktrace_with_context' => json_encode($this->enrichStacktraceWithContext($previousError)),
                        ];

                        // Keep previous_exception for backward compatibility
                        $errorContext['previous_exception'] = [
                            'class' => get_class($previousError),
                            'message' => $previousError->getMessage(),
                            'code' => $previousError->getCode(),
                        ];
                    }
                } elseif ($error instanceof \Throwable) {
                    // Handle regular Throwable exceptions
                    $message = $error->getMessage();
                    $errorContext['graphql_error'] = false;

                    // Store in nested structure for FetchLogs compatibility
                    $errorContext['exception'] = [
                        'class' => get_class($error),
                        'message' => $error->getMessage(),
                        'code' => $error->getCode(),
                        'file' => $this->normalizePath($error->getFile()),
                        'line' => $error->getLine(),
                        'stacktrace' => $error->getTraceAsString(),
                        'stacktrace_with_context' => json_encode($this->enrichStacktraceWithContext($error)),
                    ];

                    // Keep flat fields for backward compatibility
                    $errorContext['exception_class'] = get_class($error);
                    $errorContext['exception_code'] = $error->getCode();
                    $errorContext['file'] = $this->normalizePath($error->getFile());
                    $errorContext['line'] = $error->getLine();
                }

                if (!empty($message)) {
                    $this->logger->error($message, $errorContext);
                }
            } catch (\Throwable $e) {
                // Fail silently to avoid breaking GraphQL error handling
                // but try to log this failure
                try {
                    $this->logger->error(
                        'Failed to log GraphQL error: ' . $e->getMessage(),
                        [
                            'error_class' => get_class($e),
                            'error_code' => $e->getCode(),
                        ]
                    );
                } catch (\Throwable $logException) {
                    // Double failure - give up silently
                }
            }
        }

        return [$errors, $formatter];
    }

    /**
     * Enrich exception stacktrace with source code context (pre_context, context_line, post_context)
     *
     * @param \Throwable $exception
     * @param int $contextLines Number of lines before/after to include (default 2, reduced to avoid Sentry size limits)
     * @return array Array of frames with context
     */
    private function enrichStacktraceWithContext(\Throwable $exception, int $contextLines = 2): array
    {
        $frames = [];

        // Add the exception origin frame first
        $frames[] = [
            'file' => $this->normalizePath($exception->getFile()),
            'line' => $exception->getLine(),
            'class' => get_class($exception),
            'function' => 'throw',
            'pre_context' => $this->getSourceCodeLines($exception->getFile(), $exception->getLine(), $contextLines, 'before'),
            'context_line' => $this->getSourceCodeLine($exception->getFile(), $exception->getLine()),
            'post_context' => $this->getSourceCodeLines($exception->getFile(), $exception->getLine(), $contextLines, 'after'),
        ];

        // Add frames from exception trace
        foreach ($exception->getTrace() as $trace) {
            if (!isset($trace['file'])) {
                continue;
            }

            $frames[] = [
                'file' => $this->normalizePath($trace['file']),
                'line' => $trace['line'] ?? 0,
                'class' => $trace['class'] ?? '',
                'function' => $trace['function'] ?? '',
                'pre_context' => $this->getSourceCodeLines($trace['file'], $trace['line'] ?? 0, $contextLines, 'before'),
                'context_line' => $this->getSourceCodeLine($trace['file'], $trace['line'] ?? 0),
                'post_context' => $this->getSourceCodeLines($trace['file'], $trace['line'] ?? 0, $contextLines, 'after'),
            ];
        }

        return $frames;
    }

    /**
     * Normalize file paths to be environment-independent and readable
     * Converts Docker mount paths to portable paths
     *
     * @param string $filePath
     * @return string
     */
    private function normalizePath(string $filePath): string
    {
        // Convert /var/www/vendor/ to /vendor/ (Magento Docker environment)
        if (strpos($filePath, '/var/www/vendor/') === 0) {
            return str_replace('/var/www/vendor/', '/vendor/', $filePath);
        }

        // Keep /app/ paths as-is
        if (strpos($filePath, '/app/') === 0) {
            return $filePath;
        }

        // Keep /vendor/ paths as-is
        if (strpos($filePath, '/vendor/') === 0) {
            return $filePath;
        }

        // Return as-is for other paths
        return $filePath;
    }

    /**
     * Get source code lines before or after a specific line
     *
     * @param string $filePath
     * @param int $lineNumber
     * @param int $numLines Number of lines to retrieve
     * @param string $position 'before' or 'after'
     * @return array
     */
    private function getSourceCodeLines(string $filePath, int $lineNumber, int $numLines = 3, string $position = 'before'): array
    {
        if (!file_exists($filePath) || $lineNumber <= 0) {
            return [];
        }

        try {
            // Don't use FILE_SKIP_EMPTY_LINES as it breaks line numbering
            $lines = file($filePath, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                return [];
            }

            $result = [];

            if ($position === 'before') {
                $startLine = max(0, $lineNumber - $numLines - 1);
                $endLine = max(0, $lineNumber - 2);
            } else {
                $startLine = $lineNumber;
                $endLine = min(count($lines) - 1, $lineNumber + $numLines - 1);
            }

            for ($i = $startLine; $i <= $endLine; $i++) {
                if (isset($lines[$i])) {
                    $result[] = $lines[$i];
                }
            }

            return $result;
        } catch (\Throwable $e) {
            // Silently fail if we can't read the file
            return [];
        }
    }

    /**
     * Get a single source code line
     *
     * @param string $filePath
     * @param int $lineNumber
     * @return string|null
     */
    private function getSourceCodeLine(string $filePath, int $lineNumber): ?string
    {
        if (!file_exists($filePath) || $lineNumber <= 0) {
            return null;
        }

        try {
            // Don't use FILE_SKIP_EMPTY_LINES as it breaks line numbering
            $lines = file($filePath, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines) || !isset($lines[$lineNumber - 1])) {
                return null;
            }

            return $lines[$lineNumber - 1];
        } catch (\Throwable $e) {
            // Silently fail if we can't read the file
            return null;
        }
    }
}

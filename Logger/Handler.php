<?php

declare(strict_types=1);

/**
 * Tapbuy Centralized Logger Handler
 *
 * Writes JSON-formatted log entries to a rotating log file.
 * Logs can be retrieved via GraphQL and then cleared.
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Logger;

use Magento\Framework\Filesystem\DriverInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Tapbuy\RedirectTracking\Api\LogHandlerInterface;
use Tapbuy\RedirectTracking\Api\TapbuyConstants;

class Handler extends StreamHandler implements LogHandlerInterface
{
    /**
     * @var string
     */
    protected const LOG_FILE = TapbuyConstants::LOG_FILE_NAME;

    /**
     * @var DriverInterface
     */
    protected $filesystem;

    /**
     * @var string
     */
    protected $filePath;

    /**
     * @param DriverInterface $filesystem
     * @param string|null $filePath
     */
    public function __construct(
        DriverInterface $filesystem,
        ?string $filePath = null
    ) {
        $this->filesystem = $filesystem;

        // Determine log file path
        if ($filePath === null) {
            $filePath = BP . '/var/log/' . self::LOG_FILE;
        }

        $this->filePath = $filePath;

        // Initialize parent with file stream
        parent::__construct(
            $filePath,
            Logger::DEBUG
        );

        // Use JSON formatter for structured logging
        $this->setFormatter(new JsonFormatter());
    }

    /**
     * Write record to file with enhanced context.
     *
     * Compatible with both Monolog 2.x (array) and Monolog 3.x (LogRecord)
     *
     * @param array|LogRecord $record
     * @return void
     */
    protected function write(array|LogRecord $record): void
    {
        // Normalize record for both Monolog 2.x (array) and 3.x (LogRecord)
        $isLogRecord = $record instanceof LogRecord;
        $context = $isLogRecord ? $record->context : ($record['context'] ?? []);

        // Handle enriched stacktrace with source code context (preferred)
        if (isset($context['exception']['stacktrace_with_context']) && !isset($context['stacktrace_with_context'])) {
            if ($isLogRecord) {
                $context = array_merge($context, ['stacktrace_with_context' => $context['exception']['stacktrace_with_context']]);
                $record = $record->with(context: $context);
            } else {
                $record['context']['stacktrace_with_context'] = $context['exception']['stacktrace_with_context'];
            }
        }

        // Add stacktrace if not already present and we're logging an error
        if (!isset($context['stacktrace']) && !isset($context['exception']['stacktrace'])) {
            $level = $isLogRecord ? $record->level->value : $record['level'];

            // For errors and above, capture current stacktrace only as fallback
            if ($level >= Logger::ERROR) {
                $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
                // Remove logger internal calls
                $backtrace = array_slice($backtrace, 4);
                $stacktrace = $this->formatStacktrace($backtrace);

                if ($isLogRecord) {
                    $record = $record->with(context: array_merge($context, ['stacktrace' => $stacktrace]));
                } else {
                    $record['context']['stacktrace'] = $stacktrace;
                }
            }
        } elseif (isset($context['exception']['stacktrace']) && !isset($context['stacktrace'])) {
            // Use exception stacktrace if available (preferred over debug_backtrace)
            if ($isLogRecord) {
                $record = $record->with(context: array_merge($context, ['stacktrace' => $context['exception']['stacktrace']]));
            } else {
                $record['context']['stacktrace'] = $context['exception']['stacktrace'];
            }
        }

        // Format the record using the formatter (JsonFormatter)
        $formatted = '';
        if ($this->getFormatter()) {
            $formatted = $this->getFormatter()->format($record);
        } else {
            // Fallback: manually format as JSON if no formatter is set
            $recordArray = $record instanceof LogRecord ? [
                'timestamp' => $record->datetime->format('Y-m-d H:i:s'),
                'level' => $record->level->name,
                'message' => $record->message,
                'context' => $record->context,
            ] : $record;
            $formatted = json_encode($recordArray) . "\n";
        }

        // Write directly to file
        if ($formatted && !empty($this->filePath)) {
            file_put_contents($this->filePath, $formatted, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Format backtrace array into readable string
     *
     * @param array $backtrace
     * @return string
     */
    private function formatStacktrace(array $backtrace): string
    {
        $lines = [];
        foreach ($backtrace as $i => $trace) {
            $file = $trace['file'] ?? 'unknown';
            $line = $trace['line'] ?? 0;
            $class = $trace['class'] ?? '';
            $type = $trace['type'] ?? '';
            $function = $trace['function'] ?? '';

            $lines[] = sprintf('#%d %s(%d): %s%s%s()', $i, $file, $line, $class, $type, $function);
        }
        return implode("\n", $lines);
    }

    /**
     * Get the base log file path (without date suffix for rotated files)
     *
     * @return string
     */
    public function getLogFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Get all log file paths (single file only)
     *
     * @return array
     */
    public function getAllLogFiles(): array
    {
        return file_exists($this->filePath) ? [$this->filePath] : [];
    }
}

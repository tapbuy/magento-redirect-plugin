<?php

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
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Tapbuy\RedirectTracking\Api\LogHandlerInterface;
use Tapbuy\RedirectTracking\Api\TapbuyConstants;

class Handler extends RotatingFileHandler implements LogHandlerInterface
{
    /**
     * @var string
     */
    protected const LOG_FILE = TapbuyConstants::LOG_FILE_NAME;

    /**
     * @var int Maximum file size before rotation (5MB)
     */
    protected const MAX_FILES = 3;

    /**
     * @var DriverInterface
     */
    protected $filesystem;

    /**
     * @param DriverInterface $filesystem
     * @param string|null $filePath
     * @param int $maxFiles
     */
    public function __construct(
        DriverInterface $filesystem,
        ?string $filePath = null,
        int $maxFiles = self::MAX_FILES
    ) {
        $this->filesystem = $filesystem;

        // Determine log file path
        if ($filePath === null) {
            $filePath = BP . '/var/log/' . self::LOG_FILE;
        }

        parent::__construct(
            $filePath,
            $maxFiles,
            Logger::DEBUG,
            true,
            0644
        );

        // Use JSON formatter for structured logging
        $this->setFormatter(new JsonFormatter());
    }

    /**
     * Write record to file with enhanced context
     * Compatible with both Monolog 2.x (array) and Monolog 3.x (LogRecord)
     *
     * @param array|LogRecord $record
     */
    protected function write(array|LogRecord $record): void
    {
        // Normalize record for both Monolog 2.x (array) and 3.x (LogRecord)
        if ($record instanceof LogRecord) {
            $context = $record->context;
        } else {
            $context = $record['context'] ?? [];
        }

        // Add stacktrace if not already present and we're logging an error
        if (!isset($context['stacktrace']) && !isset($context['exception']['stacktrace'])) {
            if ($record instanceof LogRecord) {
                $level = $record->level->value;
            } else {
                $level = $record['level'];
            }

            // For errors and above, capture current stacktrace
            if ($level >= Logger::ERROR) {
                $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
                // Remove logger internal calls
                $backtrace = array_slice($backtrace, 4);

                if ($record instanceof LogRecord) {
                    $record = $record->with(context: array_merge($context, [
                        'stacktrace' => $this->formatStacktrace($backtrace)
                    ]));
                } else {
                    $record['context']['stacktrace'] = $this->formatStacktrace($backtrace);
                }
            }
        }

        parent::write($record);
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
        return BP . '/var/log/' . self::LOG_FILE;
    }

    /**
     * Get all log file paths (including rotated files)
     *
     * @return array
     */
    public function getAllLogFiles(): array
    {
        $basePath = BP . '/var/log/';
        // Use LOG_FILE constant to derive the pattern (replace .log with *.log for glob)
        $pattern = $basePath . str_replace('.log', '*.log', self::LOG_FILE);

        $files = glob($pattern);
        return $files ?: [];
    }
}

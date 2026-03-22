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

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\DriverInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Tapbuy\DataScrubber\Anonymizer;
use Tapbuy\DataScrubber\Keys;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\LogHandlerInterface;
use Tapbuy\RedirectTracking\Api\TapbuyConstants;

class Handler extends StreamHandler implements LogHandlerInterface
{
    protected const LOG_FILE = TapbuyConstants::LOG_FILE_NAME;

    protected const SCRUBBING_KEYS_CACHE_FILE = 'tapbuy-scrubbing-keys.json';

    /**
     * @var DriverInterface
     */
    protected $filesystem;

    /**
     * @var string
     */
    protected $filePath;

    /**
     * @var ConfigInterface
     */
    private ConfigInterface $config;

    /**
     * @var DirectoryList
     */
    private DirectoryList $directoryList;

    /**
     * @var Anonymizer|null Lazily initialized; null when anonymization is disabled or unavailable.
     */
    private ?Anonymizer $anonymizer = null;

    /**
     * @var bool Whether we already attempted (and failed) to build the anonymizer.
     */
    private bool $anonymizerInitAttempted = false;

    /**
     * @param DriverInterface $filesystem
     * @param ConfigInterface $config
     * @param DirectoryList $directoryList
     * @param string|null $filePath
     */
    public function __construct(
        DriverInterface $filesystem,
        ConfigInterface $config,
        DirectoryList $directoryList,
        ?string $filePath = null
    ) {
        $this->filesystem = $filesystem;
        $this->config = $config;
        $this->directoryList = $directoryList;

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
        $context = $this->getContext($record);

        // Promote exception stacktrace_with_context to the top-level context (preferred format)
        if (isset($context['exception']['stacktrace_with_context']) && !isset($context['stacktrace_with_context'])) {
            $record = $this->withContext($record, [
                'stacktrace_with_context' => $context['exception']['stacktrace_with_context'],
            ]);
        }

        // No stacktrace at all — capture a backtrace for errors and above as a fallback
        if (!isset($context['stacktrace']) && !isset($context['exception']['stacktrace'])) {
            $record = $this->addStacktraceFromBacktrace($record);
        }

        // Promote exception stacktrace to top-level context (both conditions are mutually exclusive)
        if (isset($context['exception']['stacktrace']) && !isset($context['stacktrace'])) {
            $record = $this->withContext($record, ['stacktrace' => $context['exception']['stacktrace']]);
        }

        $record = $this->anonymizeRecord($record);

        $formatted = $this->formatRecord($record);
        if ($formatted && !empty($this->filePath)) {
            $this->filesystem->filePutContents($this->filePath, $formatted, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Extract the context array from a Monolog 2.x (array) or 3.x (LogRecord) record.
     *
     * @param array|LogRecord $record
     * @return array
     */
    private function getContext(array|LogRecord $record): array
    {
        return $record instanceof LogRecord ? $record->context : ($record['context'] ?? []);
    }

    /**
     * Add or merge additional context into a Monolog 2.x (array) or 3.x (LogRecord) record.
     *
     * @param array|LogRecord $record
     * @param array $additionalContext
     * @return array|LogRecord
     */
    private function withContext(array|LogRecord $record, array $additionalContext): array|LogRecord
    {
        if ($record instanceof LogRecord) {
            return $record->with(context: array_merge($record->context, $additionalContext));
        }
        foreach ($additionalContext as $key => $value) {
            $record['context'][$key] = $value;
        }
        return $record;
    }

    /**
     * Capture a debug_backtrace stacktrace and inject it into the record context.
     *
     * Only runs for ERROR-level and above; returns the record unchanged otherwise.
     *
     * @param array|LogRecord $record
     * @return array|LogRecord
     */
    private function addStacktraceFromBacktrace(array|LogRecord $record): array|LogRecord
    {
        $level = $record instanceof LogRecord ? $record->level->value : ($record['level'] ?? 0);
        if ($level < Logger::ERROR) {
            return $record;
        }
        // Remove logger-internal frames
        $backtrace = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15), 4);
        return $this->withContext($record, ['stacktrace' => $this->formatStacktrace($backtrace)]);
    }

    /**
     * Serialize a log record using the configured formatter, with a JSON fallback.
     *
     * @param array|LogRecord $record
     * @return string
     */
    private function formatRecord(array|LogRecord $record): string
    {
        $formatter = $this->getFormatter();
        if ($formatter !== null) {
            return (string) $formatter->format($record);
        }
        $recordArray = $record instanceof LogRecord ? [
            'timestamp' => $record->datetime->format('Y-m-d H:i:s'),
            'level'     => $record->level->name,
            'message'   => $record->message,
            'context'   => $record->context,
        ] : $record;
        return json_encode($recordArray) . "\n";
    }

    /**
     * Anonymize the full log record (message, context, extra) using the configured scrubbing keys.
     *
     * Returns the record unchanged when anonymization is disabled or unavailable (fail open).
     * Compatible with both Monolog 2.x (array) and Monolog 3.x (LogRecord).
     *
     * @param array|LogRecord $record
     * @return array|LogRecord
     */
    private function anonymizeRecord(array|LogRecord $record): array|LogRecord
    {
        $anonymizer = $this->getAnonymizer();
        if ($anonymizer === null) {
            return $record;
        }

        $isLogRecord = $record instanceof LogRecord;

        $toAnonymize = [
            'message' => $isLogRecord ? $record->message : ($record['message'] ?? ''),
            'context' => $isLogRecord ? $record->context : ($record['context'] ?? []),
            'extra'   => $isLogRecord ? $record->extra   : ($record['extra']   ?? []),
        ];

        /** @var array $anonymized */
        $anonymized = $anonymizer->anonymizeObject($toAnonymize);

        if ($isLogRecord) {
            return $record->with(
                message: $anonymized['message'],
                context: $anonymized['context'],
                extra: $anonymized['extra'],
            );
        }

        $record['message'] = $anonymized['message'];
        $record['context'] = $anonymized['context'];
        $record['extra']   = $anonymized['extra'];

        return $record;
    }

    /**
     * Return the lazily-initialized Anonymizer, or null if anonymization is disabled or initialization fails.
     *
     * Fail-open: any exception during setup is swallowed so that logging is never blocked.
     */
    private function getAnonymizer(): ?Anonymizer
    {
        if ($this->anonymizer !== null) {
            return $this->anonymizer;
        }

        if ($this->anonymizerInitAttempted) {
            return null;
        }

        $this->anonymizerInitAttempted = true;

        $url = $this->config->getScrubbingKeysUrl();
        if ($url === '') {
            return null;
        }

        try {
            $cachePath = $this->directoryList->getPath(DirectoryList::VAR_DIR) . '/' . self::SCRUBBING_KEYS_CACHE_FILE;
            $this->anonymizer = new Anonymizer(new Keys($url, $cachePath));
        } catch (\Throwable $e) {
            error_log('[Tapbuy] Failed to initialize log anonymizer: ' . $e->getMessage());
        }

        return $this->anonymizer;
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
        return $this->filesystem->isExists($this->filePath) ? [$this->filePath] : [];
    }
}

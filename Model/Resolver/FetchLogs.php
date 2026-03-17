<?php

declare(strict_types=1);

/**
 * GraphQL Resolver for fetching Tapbuy logs
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Model\Resolver;

use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\LogHandlerInterface;
use Tapbuy\RedirectTracking\Api\TapbuyConstants;

class FetchLogs implements ResolverInterface
{
    /**
     * Required ACL resource for managing logs
     */
    private const ACL_RESOURCE = TokenAuthorizationInterface::TAPBUY_LOGS;

    /**
     * @var TokenAuthorizationInterface
     */
    private $tokenAuthorization;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var LogHandlerInterface
     */
    private $logHandler;

    /**
     * @var FileDriver
     */
    private $fileDriver;

    /**
     * @param TokenAuthorizationInterface $tokenAuthorization
     * @param ConfigInterface $config
     * @param LogHandlerInterface $logHandler
     * @param FileDriver $fileDriver
     */
    public function __construct(
        TokenAuthorizationInterface $tokenAuthorization,
        ConfigInterface $config,
        LogHandlerInterface $logHandler,
        FileDriver $fileDriver
    ) {
        $this->tokenAuthorization = $tokenAuthorization;
        $this->config = $config;
        $this->logHandler = $logHandler;
        $this->fileDriver = $fileDriver;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        // Require proper ACL authorization for log management
        $this->tokenAuthorization->authorize(self::ACL_RESOURCE);

        if (!$this->config->isEnabled()) {
            return ['logs' => [
                [
                    'message'    => 'Tapbuy is disabled.',
                    'level'      => 200,
                    'level_name' => 'INFO',
                    'datetime'   => (new \DateTime())->format(\DateTime::ATOM),
                    'context'    => null,
                    'stacktrace' => null,
                    'stacktrace_with_context' => null,
                    'error_details' => null,
                    'channel'    => null,
                    'trace_id'   => null,
                ],
            ]];
        }

        $limit = $args['limit'] ?? null;
        $traceId = $args['traceId'] ?? null;
        $logs = $this->fetchLogs($limit, $traceId);

        return ['logs' => $logs];
    }

    /**
     * Read all log files, parse entries, and return entries
     *
     * @param int|null $limit
     * @param string|null $traceId
     * @return array
     */
    private function fetchLogs(?int $limit, ?string $traceId): array
    {
        $entries = [];
        $logFiles = $this->logHandler->getAllLogFiles();

        foreach ($logFiles as $logFile) {
            if (!$this->fileDriver->isExists($logFile)) {
                continue;
            }

            // Open in read-only mode
            $handle = $this->fileDriver->fileOpen($logFile, 'r');
            if (!$handle) {
                continue;
            }

            try {
                // Read all content
                $content = '';
                while (!$this->fileDriver->endOfFile($handle)) {
                    $content .= $this->fileDriver->fileRead($handle, 8192);
                }

                // Parse JSON lines
                $lines = array_filter(explode("\n", trim($content)));
                foreach ($this->parseLogLines($lines, $traceId) as $logEntry) {
                    $entries[] = $logEntry;
                }

            } finally {
                $this->fileDriver->fileClose($handle);
            }
        }

        // Sort by datetime descending (newest first)
        usort($entries, function ($first, $second) {
            return strtotime($second['datetime']) - strtotime($first['datetime']);
        });

        // Apply limit if specified
        if ($limit !== null && $limit > 0) {
            $entries = array_slice($entries, 0, $limit);
        }

        return $entries;
    }

    /**
     * Parse raw log lines, optionally filtering by trace ID
     *
     * @param array $lines
     * @param string|null $traceId
     * @return array
     */
    private function parseLogLines(array $lines, ?string $traceId): array
    {
        $entries = [];
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!$entry || !is_array($entry)) {
                continue;
            }
            if ($traceId !== null && $this->extractTraceId($entry) !== $traceId) {
                continue;
            }
            $entries[] = $this->formatLogEntry($entry);
        }
        return $entries;
    }

    /**
     * Format a log entry for GraphQL response
     *
     * @param array $entry
     * @return array
     */
    private function formatLogEntry(array $entry): array
    {
        // Handle Monolog 3.x format
        $levelName = $entry['level_name'] ?? 'UNKNOWN';
        $level = $entry['level'] ?? 0;

        // Extract stacktrace from context or exception
        $stacktrace = null;
        $stacktraceWithContext = null;
        $context = $entry['context'] ?? [];

        // Prefer stacktrace_with_context (enriched with source code)
        if (isset($context['exception']['stacktrace_with_context'])) {
            $stacktraceWithContext = $context['exception']['stacktrace_with_context'];
        } elseif (isset($context['stacktrace_with_context'])) {
            $stacktraceWithContext = $context['stacktrace_with_context'];
        }

        // Fall back to plain stacktrace
        if (isset($context['exception']['stacktrace'])) {
            $stacktrace = $context['exception']['stacktrace'];
        } elseif (isset($context['stacktrace'])) {
            $stacktrace = $context['stacktrace'];
        }

        // Build error details from exception if present
        $errorDetails = null;
        if (isset($context['exception'])) {
            $exc = $context['exception'];
            $errorDetails = [
                'class' => $exc['class'] ?? null,
                'message' => $exc['message'] ?? null,
                'code' => $exc['code'] ?? null,
                'file' => $exc['file'] ?? null,
                'line' => $exc['line'] ?? null,
            ];
        }

        return [
            'message' => $entry['message'] ?? '',
            'level' => (int) $level,
            'level_name' => $levelName,
            'context' => json_encode($context),
            'stacktrace' => $stacktrace,
            'stacktrace_with_context' => $stacktraceWithContext,
            'error_details' => $errorDetails ? json_encode($errorDetails) : null,
            'datetime' => $entry['datetime'] ?? date('c'),
            'channel' => $entry['channel'] ?? TapbuyConstants::LOGGER_CHANNEL_NAME,
            'trace_id' => $this->extractTraceId($entry),
        ];
    }

    /**
     * Extract trace ID from log entry context
     *
     * @param array $entry
     * @return string|null
     */
    private function extractTraceId(array $entry): ?string
    {
        $context = $entry['context'] ?? [];
        return $context[TapbuyConstants::LOG_CONTEXT_TRACE_ID] ?? null;
    }
}

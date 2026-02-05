<?php

declare(strict_types=1);

/**
 * GraphQL Resolver for fetching and clearing Tapbuy logs
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\LogHandlerInterface;
use Tapbuy\RedirectTracking\Api\TapbuyConstants;

class FetchAndClearLogs implements ResolverInterface
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
     * @var LogHandlerInterface
     */
    private $logHandler;

    /**
     * @param TokenAuthorizationInterface $tokenAuthorization
     * @param LogHandlerInterface $logHandler
     */
    public function __construct(
        TokenAuthorizationInterface $tokenAuthorization,
        LogHandlerInterface $logHandler
    ) {
        $this->tokenAuthorization = $tokenAuthorization;
        $this->logHandler = $logHandler;
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

        $limit = $args['limit'] ?? null;
        $logs = $this->fetchAndClearLogs($limit);

        return ['logs' => $logs];
    }

    /**
     * Read all log files, parse entries, clear files, and return entries
     *
     * @param int|null $limit
     * @return array
     */
    private function fetchAndClearLogs(?int $limit): array
    {
        $entries = [];
        $logFiles = $this->logHandler->getAllLogFiles();

        foreach ($logFiles as $logFile) {
            if (!file_exists($logFile)) {
                continue;
            }

            // Use file locking to prevent race conditions
            $handle = fopen($logFile, 'r+');
            if (!$handle) {
                continue;
            }

            if (!flock($handle, LOCK_EX)) {
                fclose($handle);
                continue;
            }

            try {
                // Read all content
                $content = '';
                while (!feof($handle)) {
                    $content .= fread($handle, 8192);
                }

                // Parse JSON lines
                $lines = array_filter(explode("\n", trim($content)));
                foreach ($lines as $line) {
                    $entry = json_decode($line, true);
                    if ($entry && is_array($entry)) {
                        $entries[] = $this->formatLogEntry($entry);
                    }
                }

                // Truncate the file
                ftruncate($handle, 0);
                rewind($handle);

            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
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
        $context = $entry['context'] ?? [];

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
            'error_details' => $errorDetails ? json_encode($errorDetails) : null,
            'datetime' => $entry['datetime'] ?? date('c'),
            'channel' => $entry['channel'] ?? TapbuyConstants::LOGGER_CHANNEL_NAME,
        ];
    }
}

<?php

declare(strict_types=1);

/**
 * Monolog Processor for injecting Tapbuy Trace ID into log context
 *
 * Reads the X-Tapbuy-Trace-ID header from incoming requests and automatically
 * adds it to all log entries. Only activates for requests from Tapbuy API
 * (identified by X-Tapbuy-Call header).
 *
 * This enables correlation of Magento exceptions with specific Tapbuy API requests.
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Logger;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Tapbuy\RedirectTracking\Api\TapbuyConstants;
use Tapbuy\RedirectTracking\Api\TapbuyRequestDetectorInterface;

class TraceIdProcessor implements ProcessorInterface
{
    /**
     * @var TapbuyRequestDetectorInterface
     */
    private TapbuyRequestDetectorInterface $tapbuyRequestDetector;

    /**
     * Cached trace ID to avoid repeated header reads
     * @var string|null|false False means already checked but not found
     */
    private $traceId = null;

    /**
     * @param TapbuyRequestDetectorInterface $tapbuyRequestDetector
     */
    public function __construct(TapbuyRequestDetectorInterface $tapbuyRequestDetector)
    {
        $this->tapbuyRequestDetector = $tapbuyRequestDetector;
    }

    /**
     * Inject trace ID into log record context
     *
     * Compatible with both Monolog 2.x (array) and Monolog 3.x (LogRecord)
     *
     * @param array|LogRecord $record
     * @return array|LogRecord
     */
    public function __invoke(array|LogRecord $record): array|LogRecord
    {
        // Only process once per request
        if ($this->traceId === null) {
            $this->traceId = $this->extractTraceId();
        }

        // If trace ID found, inject it into context
        if ($this->traceId !== false && $this->traceId !== null) {
            if ($record instanceof LogRecord) {
                // Monolog 3.x
                $context = $record->context;
                $context[TapbuyConstants::LOG_CONTEXT_TRACE_ID] = $this->traceId;
                $record = $record->with(context: $context);
            } else {
                // Monolog 2.x
                $record['context'][TapbuyConstants::LOG_CONTEXT_TRACE_ID] = $this->traceId;
            }
        }

        return $record;
    }

    /**
     * Extract trace ID from request headers
     *
     * Only extracts if X-Tapbuy-Call header is present (Tapbuy-initiated request)
     *
     * @return string|false String if trace ID found, false otherwise
     */
    private function extractTraceId()
    {
        try {
            $traceId = $this->tapbuyRequestDetector->getTraceId();
            return $traceId !== null ? $traceId : false;
        } catch (\Exception $e) {
            // Fail silently to avoid breaking logging
            return false;
        }
    }
}

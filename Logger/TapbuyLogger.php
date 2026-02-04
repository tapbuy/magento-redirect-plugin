<?php

/**
 * Tapbuy Centralized Logger
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Logger;

use Monolog\Logger;
use Tapbuy\RedirectTracking\Api\TapbuyConstants;

class TapbuyLogger extends Logger
{
    /**
     * TapbuyLogger constructor.
     *
     * @param string $name
     * @param array $handlers
     * @param array $processors
     */
    public function __construct(
        $name = TapbuyConstants::LOGGER_CHANNEL_NAME,
        array $handlers = [],
        array $processors = []
    ) {
        parent::__construct($name, $handlers, $processors);
    }

    /**
     * Log an error with exception details including stacktrace
     *
     * @param string $message
     * @param \Throwable $exception
     * @param array $context
     * @return void
     */
    public function logException(string $message, \Throwable $exception, array $context = []): void
    {
        $context['exception'] = [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'stacktrace' => $exception->getTraceAsString(),
        ];

        if ($exception->getPrevious()) {
            $context['exception']['previous'] = [
                'class' => get_class($exception->getPrevious()),
                'message' => $exception->getPrevious()->getMessage(),
            ];
        }

        $this->error($message, $context);
    }
}

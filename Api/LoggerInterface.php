<?php

declare(strict_types=1);

/**
 * Tapbuy Logger Interface
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Api;

use Psr\Log\LoggerInterface as PsrLoggerInterface;

/**
 * Interface LoggerInterface
 *
 * Extends PSR-3 LoggerInterface with Tapbuy-specific logging methods.
 */
interface LoggerInterface extends PsrLoggerInterface
{
    /**
     * Log an error with exception details including stacktrace
     *
     * @param string $message
     * @param \Throwable $exception
     * @param array $context
     * @return void
     */
    public function logException(string $message, \Throwable $exception, array $context = []): void;
}

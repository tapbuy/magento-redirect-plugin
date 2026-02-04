<?php

/**
 * Tapbuy Log Handler Interface
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Api;

/**
 * Interface LogHandlerInterface
 *
 * Provides log file handling functionality for Tapbuy.
 */
interface LogHandlerInterface
{
    /**
     * Get the base log file path (without date suffix for rotated files)
     *
     * @return string
     */
    public function getLogFilePath(): string;

    /**
     * Get all log file paths (including rotated files)
     *
     * @return array
     */
    public function getAllLogFiles(): array;
}

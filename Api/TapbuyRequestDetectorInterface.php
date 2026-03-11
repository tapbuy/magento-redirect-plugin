<?php

declare(strict_types=1);

/**
 * Tapbuy Request Detector Interface
 *
 * Provides methods to detect Tapbuy-originated requests.
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Api;

interface TapbuyRequestDetectorInterface
{
    /**
     * Check if the current request originates from the Tapbuy checkout.
     *
     * This checks for the presence of the X-Tapbuy-Call header.
     *
     * @return bool
     */
    public function isTapbuyCall(): bool;

    /**
     * Get the current request URI
     *
     * @return string
     */
    public function getRequestUri(): string;

    /**
     * Get the Tapbuy trace ID from request headers
     *
     * Only returns trace ID if this is a Tapbuy-initiated request.
     *
     * @return string|null
     */
    public function getTraceId(): ?string;
}

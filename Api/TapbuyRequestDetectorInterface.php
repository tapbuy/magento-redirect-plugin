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
     * Check if the current request is from the Tapbuy API using the API key.
     *
     * This checks for the x-tapbuy-key header matching the configured API key.
     *
     * @return bool
     */
    public function isTapbuyApiRequest(): bool;
}

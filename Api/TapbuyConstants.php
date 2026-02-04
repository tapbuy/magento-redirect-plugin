<?php

/**
 * Tapbuy Centralized Constants
 *
 * This class contains all shared constants used across Tapbuy modules.
 * Use these constants instead of hardcoding strings to ensure consistency.
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Api;

class TapbuyConstants
{
    /**
     * HTTP Header used to identify requests from Tapbuy API.
     */
    public const HTTP_HEADER_TAPBUY_CALL = 'X-Tapbuy-Call';

    /**
     * HTTP Header used for API key authentication.
     */
    public const HTTP_HEADER_TAPBUY_KEY = 'x-tapbuy-key';

    /**
     * Key used to store Tapbuy additional information in payment.
     */
    public const PAYMENT_ADDITIONAL_INFO_KEY = 'tapbuy';

    /**
     * Logger channel name.
     */
    public const LOGGER_CHANNEL_NAME = 'tapbuy';

    /**
     * Log file name prefix.
     */
    public const LOG_FILE_NAME = 'tapbuy-checkout.log';

    /**
     * A/B test tracking flag for orders.
     */
    public const ABTEST_TRACKING_FLAG = 'tapbuy_abtest_tracked';

    /**
     * Frontend route name.
     */
    public const FRONTEND_ROUTE = 'tapbuy';

    /**
     * Configuration path prefix.
     */
    public const CONFIG_PATH_PREFIX = 'tapbuy';
}

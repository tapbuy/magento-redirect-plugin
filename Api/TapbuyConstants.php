<?php

declare(strict_types=1);

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
     * @var string HTTP header used to identify requests originating from the Tapbuy checkout
     * (as opposed to generic API-key-authenticated API requests).
     */
    public const HTTP_HEADER_TAPBUY_CALL = 'X-Tapbuy-Call';

    /**
     * @var string HTTP Header used for API key authentication.
     */
    public const HTTP_HEADER_TAPBUY_KEY = 'x-tapbuy-key';

    /**
     * @var string HTTP Header used for trace ID correlation.
     */
    public const HTTP_HEADER_TAPBUY_TRACE_ID = 'X-Tapbuy-Trace-ID';

    /**
     * @var string Context key for trace ID in logs.
     */
    public const LOG_CONTEXT_TRACE_ID = 'x_tapbuy_trace_id';

    /**
     * @var string Key used to store Tapbuy additional information in payment.
     */
    public const PAYMENT_ADDITIONAL_INFO_KEY = 'tapbuy';

    /**
     * @var string Logger channel name.
     */
    public const LOGGER_CHANNEL_NAME = 'tapbuy';

    /**
     * @var string Base log file name (including the `.log` extension).
     */
    public const LOG_FILE_NAME = 'tapbuy-checkout.log';

    /**
     * @var string A/B test tracking flag for orders.
     */
    public const ABTEST_TRACKING_FLAG = 'tapbuy_abtest_tracked';

    /**
     * @var string Frontend route name.
     */
    public const FRONTEND_ROUTE = 'tapbuy';

    /**
     * @var string Configuration path prefix.
     */
    public const CONFIG_PATH_PREFIX = 'tapbuy';
}

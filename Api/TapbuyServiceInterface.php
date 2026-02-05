<?php

declare(strict_types=1);
/**
 * Tapbuy Redirect and Tracking Service Interface
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Api;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order;

interface TapbuyServiceInterface
{
    /**
     * Send API request to Tapbuy
     *
     * @param string $endpoint
     * @param array $payload
     * @return array|bool
     */
    public function sendRequest($endpoint, $payload);

    /**
     * Send transaction data to Tapbuy
     *
     * @param Order $order
     * @param int|null $abTestId
     * @return array|bool
     */
    public function sendTransactionForOrder($order, $abTestId = null);

    /**
     * Trigger A/B test
     *
     * @param CartInterface $quote
     * @param bool|null $forceRedirect
     * @param string|null $referer
     * @return array|bool
     */
    public function triggerABTest($quote, $forceRedirect = null, $referer = null);
}

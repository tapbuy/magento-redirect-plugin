<?php
/**
 * Tapbuy Redirect and Tracking Service Interface
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Api;

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
     * @return array|bool
     */
    public function sendTransactionForOrder($order);

    /**
     * Trigger A/B test
     *
     * @return array|bool
     */
    public function triggerABTest();
}
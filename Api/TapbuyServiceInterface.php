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
    public function sendRequest(string $endpoint, array $payload): array|bool;

    /**
     * Send transaction data to Tapbuy
     *
     * @param Order $order
     * @param string|null $abTestId
     * @return array|bool
     */
    public function sendTransactionForOrder(Order $order, ?string $abTestId = null): array|bool;

    /**
     * Trigger A/B test
     *
     * @param CartInterface $quote
     * @param string|null $forceRedirect
     * @param string|null $referer
     * @return array
     */
    public function triggerABTest(
        CartInterface $quote,
        ?string $forceRedirect = null,
        ?string $referer = null
    ): array;
}

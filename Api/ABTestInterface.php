<?php

declare(strict_types=1);

/**
 * Tapbuy A/B Test Interface
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Api;

use Magento\Sales\Model\Order;

/**
 * Interface ABTestInterface
 *
 * Provides A/B test functionality for Tapbuy.
 */
interface ABTestInterface
{
    /**
     * Process order transaction after order placement
     *
     * @param Order $order
     * @param int|null $abTestId Used with tapbuyConfirmOrder GraphQL mutation for headless implementations
     * @return void
     */
    public function processOrderTransaction($order, $abTestId = null);
}

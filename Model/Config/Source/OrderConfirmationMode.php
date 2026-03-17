<?php

declare(strict_types=1);

/**
 * Source model for the "Order Confirmation Mode" admin config option.
 *
 * Provides the list of available strategies used to capture the order
 * confirmation event and forward it to the Tapbuy pixel:
 *  - GraphQL Mutation: triggered via the checkout GraphQL mutation
 *  - OrderSaveAfter Observer: triggered by the sales_order_save_after event
 *  - Both: triggers both strategies simultaneously
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */
namespace Tapbuy\RedirectTracking\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;

class OrderConfirmationMode implements OptionSourceInterface
{
    /**
     * Return option array for use in admin system config select fields.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => ConfigInterface::ORDER_CONFIRMATION_MODE_GRAPHQL, 'label' => __('GraphQL Mutation')],
            ['value' => ConfigInterface::ORDER_CONFIRMATION_MODE_OBSERVER, 'label' => __('OrderSaveAfter Observer')],
            ['value' => ConfigInterface::ORDER_CONFIRMATION_MODE_BOTH, 'label' => __('Both')],
        ];
    }
}

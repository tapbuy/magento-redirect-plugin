<?php

declare(strict_types=1);
namespace Tapbuy\RedirectTracking\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;

class OrderConfirmationMode implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => ConfigInterface::ORDER_CONFIRMATION_MODE_GRAPHQL, 'label' => __('GraphQL Mutation')],
            ['value' => ConfigInterface::ORDER_CONFIRMATION_MODE_OBSERVER, 'label' => __('OrderSaveAfter Observer')],
            ['value' => ConfigInterface::ORDER_CONFIRMATION_MODE_BOTH, 'label' => __('Both')],
        ];
    }
}

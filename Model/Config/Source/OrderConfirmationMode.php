<?php

declare(strict_types=1);
namespace Tapbuy\RedirectTracking\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class OrderConfirmationMode implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'graphql', 'label' => __('GraphQL Mutation')],
            ['value' => 'observer', 'label' => __('OrderSaveAfter Observer')],
            ['value' => 'both', 'label' => __('Both')],
        ];
    }
}

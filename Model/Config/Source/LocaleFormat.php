<?php
namespace Tapbuy\RedirectTracking\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LocaleFormat implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'long', 'label' => __('Long')],
            ['value' => 'short', 'label' => __('Short')],
        ];
    }
}

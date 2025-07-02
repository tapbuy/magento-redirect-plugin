<?php
/**
 * Tapbuy Redirect and Tracking - Tracking Block
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Block\Checkout;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Tapbuy\RedirectTracking\Helper\Data;
use Tapbuy\RedirectTracking\Model\Config;

class Tracking extends Template
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Data
     */
    private $helper;

    /**
     * Tracking constructor.
     *
     * @param Context $context
     * @param Config $config
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Config $config,
        Data $helper,
        array $data = []
    ) {
        $this->config = $config;
        $this->helper = $helper;
        parent::__construct($context, $data);
    }

    /**
     * Check if Tapbuy is enabled
     *
     * @return bool
     */
    public function isTapbuyEnabled()
    {
        return $this->config->isEnabled();
    }

    /**
     * Get A/B test ID
     *
     * @return string|null
     */
    public function getABTestId()
    {
        return $this->helper->getABTestId();
    }
}
<?php

/**
 * Tapbuy Redirect and Tracking Checkout Observer
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Tapbuy\RedirectTracking\Model\ABTest;
use Tapbuy\RedirectTracking\Model\Config;

class CheckoutInit implements ObserverInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var ABTest
     */
    private $abTest;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * CheckoutInit constructor.
     *
     * @param Config $config
     * @param ABTest $abTest
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        ABTest $abTest,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->abTest = $abTest;
        $this->logger = $logger;
    }

    /**
     * Execute observer for checkout initialization
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        try {
            // Process A/B testing for checkout
            $this->abTest->processCheckoutABTest($observer);
        } catch (\Exception $e) {
            $this->logger->error('Error in Tapbuy checkout initialization: ' . $e->getMessage());
        }
    }
}

<?php
/**
 * Tapbuy Redirect and Tracking Order Observer
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tapbuy\RedirectTracking\Logger\TapbuyLogger;
use Tapbuy\RedirectTracking\Model\ABTest;
use Tapbuy\RedirectTracking\Model\Config;

class OrderSaveAfter implements ObserverInterface
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
     * @var TapbuyLogger
     */
    private $logger;

    /**
     * OrderSaveAfter constructor.
     *
     * @param Config $config
     * @param ABTest $abTest
     * @param TapbuyLogger $logger
     */
    public function __construct(
        Config $config,
        ABTest $abTest,
        TapbuyLogger $logger
    ) {
        $this->config = $config;
        $this->abTest = $abTest;
        $this->logger = $logger;
    }

    /**
     * Execute observer for order save after
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
            $order = $observer->getEvent()->getOrder();
            if ($order && $order->getId()) {
                // Process transaction for order
                $this->abTest->processOrderTransaction($order);
            }
        } catch (\Exception $e) {
            $this->logger->logException($e, 'Error in Tapbuy order save processing');
        }
    }
}
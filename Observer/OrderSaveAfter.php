<?php

declare(strict_types=1);
/**
 * Tapbuy Redirect and Tracking Order Observer
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tapbuy\RedirectTracking\Api\ABTestInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;

class OrderSaveAfter implements ObserverInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var ABTestInterface
     */
    private $abTest;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * OrderSaveAfter constructor.
     *
     * @param ConfigInterface $config
     * @param ABTestInterface $abTest
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigInterface $config,
        ABTestInterface $abTest,
        LoggerInterface $logger
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
            $this->logger->logException('Error in Tapbuy order save processing', $e);
        }
    }
}

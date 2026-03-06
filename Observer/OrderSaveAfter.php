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
use Magento\Sales\Model\Order;
use Tapbuy\RedirectTracking\Api\ABTestInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;

class OrderSaveAfter implements ObserverInterface
{
    /**
     * Target states that trigger a transaction transmission.
     */
    private const TRACKED_STATES = [
        Order::STATE_NEW,
        Order::STATE_PROCESSING,
        Order::STATE_COMPLETE,
    ];

    /**
     * Keeps track of order IDs already processed within the current request
     * to prevent duplicate transmissions when an order is saved multiple times.
     *
     * @var array<int|string, bool>
     */
    private static array $processedOrderIds = [];

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
            if (!$order || !$order->getId()) {
                return;
            }

            // Only act on transitions to 'processing' or 'complete'
            $currentState = $order->getState();
            if (!in_array($currentState, self::TRACKED_STATES, true)) {
                return;
            }

            // Only act when the state actually changed
            $previousState = $order->getOrigData('state');
            if ($previousState === $currentState) {
                return;
            }

            // Prevent duplicate transmissions within a single request
            $orderId = $order->getId();
            if (isset(self::$processedOrderIds[$orderId])) {
                return;
            }
            self::$processedOrderIds[$orderId] = true;

            $this->abTest->processOrderTransaction($order);
        } catch (\Exception $e) {
            $this->logger->logException('Error in Tapbuy order save processing', $e);
        }
    }
}

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

        $mode = $this->config->getOrderConfirmationMode();
        if ($mode === ConfigInterface::ORDER_CONFIRMATION_MODE_GRAPHQL) {
            return;
        }

        try {
            $order = $observer->getEvent()->getOrder();
            if (!$order || !$order->getId()) {
                $this->logger->warning('OrderSaveAfter: Observer triggered with no valid order');
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
                $this->logger->debug('OrderSaveAfter: Order already processed in this request, skipping', [
                    'order_id' => $orderId,
                    'order_number' => $order->getIncrementId(),
                ]);
                return;
            }
            self::$processedOrderIds[$orderId] = true;

            $tracked = $this->abTest->processOrderTransaction($order);

            if ($tracked) {
                $this->logger->info('OrderSaveAfter: Order transaction processed successfully', [
                    'order_id' => $order->getId(),
                    'order_number' => $order->getIncrementId(),
                    'state' => $currentState,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->logException('Error in Tapbuy order save processing', $e, [
                'order_id' => $order instanceof Order ? $order->getId() : null,
                'order_number' => $order instanceof Order ? $order->getIncrementId() : null,
            ]);
        }
    }
}

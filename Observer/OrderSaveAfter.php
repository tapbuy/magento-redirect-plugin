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
     * @param ConfigInterface $config
     * @param ABTestInterface $abTest
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ABTestInterface $abTest,
        private readonly LoggerInterface $logger
    ) {
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

        $order = null;
        try {
            $order = $this->resolveOrder($observer);
            if ($order === null) {
                return;
            }

            $tracked = $this->abTest->processOrderTransaction($order);
            if ($tracked) {
                $this->logger->info('OrderSaveAfter: Order transaction processed successfully', [
                    'order_id'     => $order->getId(),
                    'order_number' => $order->getIncrementId(),
                    'state'        => $order->getState(),
                ]);
            }
        } catch (\Throwable $e) {
            // Tapbuy tracking only — must never disrupt the order flow
            $this->logger->logException('Error in Tapbuy order save processing', $e, [
                'order_id'     => $order instanceof Order ? $order->getId() : null,
                'order_number' => $order instanceof Order ? $order->getIncrementId() : null,
            ]);
        }
    }

    /**
     * Validate the observer event and return the order only when all conditions are met.
     *
     * Returns null (skipping processing) when:
     * - The event carries no valid order
     * - The order state is not one of the tracked states
     * - The order state has not actually changed
     * - The order was already processed in this request (deduplication guard)
     *
     * @param Observer $observer
     * @return Order|null
     */
    private function resolveOrder(Observer $observer): ?Order
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order || !$order->getId()) {
            $this->logger->warning('OrderSaveAfter: Observer triggered with no valid order');
            return null;
        }

        $currentState = $order->getState();
        if (!in_array($currentState, self::TRACKED_STATES, true)) {
            return null;
        }

        if ($order->getOrigData('state') === $currentState) {
            return null;
        }

        $orderId = $order->getId();
        if (isset(self::$processedOrderIds[$orderId])) {
            $this->logger->debug('OrderSaveAfter: Order already processed in this request, skipping', [
                'order_id'     => $orderId,
                'order_number' => $order->getIncrementId(),
            ]);
            return null;
        }

        self::$processedOrderIds[$orderId] = true;
        return $order;
    }
}

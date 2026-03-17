<?php

declare(strict_types=1);

/**
 * Tapbuy Redirect and Tracking AB Test Model
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Model;

use Tapbuy\RedirectTracking\Api\ABTestInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\DataHelperInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;
use Tapbuy\RedirectTracking\Api\TapbuyConstants;
use Tapbuy\RedirectTracking\Api\TapbuyRequestDetectorInterface;
use Tapbuy\RedirectTracking\Api\TapbuyServiceInterface;
use Magento\Sales\Model\Order;

class ABTest implements ABTestInterface
{
    /**
     * @param ConfigInterface $config
     * @param TapbuyServiceInterface $service
     * @param DataHelperInterface $helper
     * @param LoggerInterface $logger
     * @param TapbuyRequestDetectorInterface $requestDetector
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly TapbuyServiceInterface $service,
        private readonly DataHelperInterface $helper,
        private readonly LoggerInterface $logger,
        private readonly TapbuyRequestDetectorInterface $requestDetector
    ) {
    }

    /**
     * Process order transaction after order placement
     *
     * @param Order $order
     * @param string|null $abTestId Used with tapbuyConfirmOrder GraphQL mutation for headless implementations
     * @return bool True if a transaction was actually sent, false if skipped or failed
     */
    public function processOrderTransaction(Order $order, ?string $abTestId = null): bool
    {
        // Skip if Tapbuy is disabled or this is a Tapbuy-originated request
        if (!$this->config->isEnabled() || $this->requestDetector->isTapbuyCall()) {
            return false;
        }

        // Skip if this order has already been tracked during this request (e.g. direct GraphQL call)
        if ($order->getData(TapbuyConstants::ABTEST_TRACKING_FLAG)) {
            $this->logger->debug('ABTest: Order already tracked during this request, skipping', [
                'order_id' => $order->getId(),
                'order_number' => $order->getIncrementId(),
            ]);
            return false;
        }

        try {
            $result = $this->service->sendTransactionForOrder($order, $abTestId);

            // Mark the order as tracked to prevent duplicate transmissions only on success
            if ($result) {
                $order->setData(TapbuyConstants::ABTEST_TRACKING_FLAG, true);
                $this->logger->debug('ABTest: Order transaction sent successfully', [
                    'order_id' => $order->getId(),
                    'order_number' => $order->getIncrementId(),
                    'ab_test_id' => $abTestId,
                    'result_id' => $result['id'] ?? null,
                ]);
            } else {
                $this->logger->warning('ABTest: Order transaction returned no result', [
                    'order_id' => $order->getId(),
                    'order_number' => $order->getIncrementId(),
                    'ab_test_id' => $abTestId,
                ]);
            }

            $this->helper->updateABTestCookie($result ? ($result['id'] ?? null) : null);

            return (bool)$result;
        } catch (\Throwable $e) {
            // Tapbuy tracking only — must never disrupt the order flow
            $this->logger->logException('Error processing order transaction', $e, [
                'order_id' => $order->getId(),
                'order_number' => $order->getIncrementId(),
            ]);
            $this->helper->updateABTestCookie(null);

            return false;
        }
    }
}

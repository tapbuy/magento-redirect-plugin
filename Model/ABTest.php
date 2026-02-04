<?php

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
use Tapbuy\RedirectTracking\Api\TapbuyRequestDetectorInterface;
use Tapbuy\RedirectTracking\Api\TapbuyServiceInterface;
use Magento\Sales\Model\Order;

class ABTest implements ABTestInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var TapbuyServiceInterface
     */
    private $service;

    /**
     * @var DataHelperInterface
     */
    private $helper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var TapbuyRequestDetectorInterface
     */
    private $requestDetector;

    /**
     * ABTest constructor.
     *
     * @param ConfigInterface $config
     * @param TapbuyServiceInterface $service
     * @param DataHelperInterface $helper
     * @param LoggerInterface $logger
     * @param TapbuyRequestDetectorInterface $requestDetector
     */
    public function __construct(
        ConfigInterface $config,
        TapbuyServiceInterface $service,
        DataHelperInterface $helper,
        LoggerInterface $logger,
        TapbuyRequestDetectorInterface $requestDetector
    ) {
        $this->config = $config;
        $this->service = $service;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->requestDetector = $requestDetector;
    }

    /**
     * Process order transaction after order placement
     *
     * @param Order $order
     * @param int|null $abTestId Used with tapbuyConfirmOrder GraphQL mutation for headless implementations
     * @return void
     */
    public function processOrderTransaction($order, $abTestId = null)
    {
        // Skip if Tapbuy is disabled or it's a Tapbuy API request
        if (!$this->config->isEnabled() || $this->requestDetector->isTapbuyApiRequest()) {
            return;
        }

        try {
            $result = $this->service->sendTransactionForOrder($order, $abTestId);

            if ($result && isset($result['id'])) {
                $this->helper->setABTestIdCookie($result['id']);
            } else {
                $this->helper->removeABTestIdCookie();
            }
        } catch (\Exception $e) {
            $this->logger->logException('Error processing order transaction', $e, [
                'order_id' => $order->getIncrementId(),
            ]);
            $this->helper->removeABTestIdCookie();
        }
    }
}

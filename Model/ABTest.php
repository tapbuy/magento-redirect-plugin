<?php

/**
 * Tapbuy Redirect and Tracking AB Test Model
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Model;

use Psr\Log\LoggerInterface;
use Tapbuy\RedirectTracking\Model\Config;
use Tapbuy\RedirectTracking\Model\Service;
use Tapbuy\RedirectTracking\Helper\Data;
use Magento\Sales\Model\Order;

class ABTest
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Service
     */
    private $service;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * ABTest constructor.
     *
     * @param Config $config
     * @param Service $service
     * @param Data $helper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        Service $service,
        Data $helper,
        LoggerInterface $logger,
    ) {
        $this->config = $config;
        $this->service = $service;
        $this->helper = $helper;
        $this->logger = $logger;
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
        if (!$this->config->isEnabled() || $this->helper->isTapbuyApiRequest()) {
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
            $this->logger->error('Error processing order transaction: ' . $e->getMessage());
            $this->helper->removeABTestIdCookie();
        }
    }
}

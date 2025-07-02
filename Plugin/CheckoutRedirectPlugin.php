<?php
/**
 * Tapbuy Redirect and Tracking Plugin
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Plugin;

use Magento\Checkout\Controller\Index\Index;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;
use Tapbuy\RedirectTracking\Model\ABTest;
use Tapbuy\RedirectTracking\Model\Config;
use Tapbuy\RedirectTracking\Model\Service;

class CheckoutRedirectPlugin
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
     * @var Service
     */
    private $service;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * CheckoutRedirectPlugin constructor.
     *
     * @param Config $config
     * @param ABTest $abTest
     * @param Service $service
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        ABTest $abTest,
        Service $service,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->abTest = $abTest;
        $this->service = $service;
        $this->logger = $logger;
    }

    /**
     * Around execute method
     *
     * @param Index $subject
     * @param \Closure $proceed
     * @return ResultInterface|ResponseInterface
     */
    public function aroundExecute(Index $subject, \Closure $proceed)
    {
        if (!$this->config->isEnabled()) {
            return $proceed();
        }

        try {
            // Verify if redirection should be allowed for current device
            if (!$this->abTest->shouldAllowRedirection()) {
                return $proceed();
            }

            // Trigger A/B test
            $result = $this->service->triggerABTest();
            
            // Handle redirection if needed
            if ($result && isset($result['redirect']) && $result['redirect'] === true && isset($result['redirectURL'])) {
                return $subject->getResponse()->setRedirect($result['redirectURL']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in Tapbuy checkout redirect plugin: ' . $e->getMessage());
        }

        return $proceed();
    }
}
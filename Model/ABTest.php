<?php

/**
 * Tapbuy Redirect and Tracking AB Test Model
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Model;

use Magento\Framework\App\Response\RedirectInterface;
use Psr\Log\LoggerInterface;
use Tapbuy\RedirectTracking\Helper\Data;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\UrlInterface;

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
     * @var RedirectInterface
     */
    private $redirect;

    /**
     * @var ActionFlag
     */
    private $actionFlag;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var ResponseInterface
     */
    private $response;

    /**
     * ABTest constructor.
     *
     * @param Config $config
     * @param Service $service
     * @param Data $helper
     * @param LoggerInterface $logger
     * @param RedirectInterface $redirect
     * @param ActionFlag $actionFlag
     * @param UrlInterface $urlBuilder
     * @param ResponseInterface $response
     */
    public function __construct(
        Config $config,
        Service $service,
        Data $helper,
        LoggerInterface $logger,
        RedirectInterface $redirect,
        ActionFlag $actionFlag,
        UrlInterface $urlBuilder,
        ResponseInterface $response
    ) {
        $this->config = $config;
        $this->service = $service;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->redirect = $redirect;
        $this->actionFlag = $actionFlag;
        $this->urlBuilder = $urlBuilder;
        $this->response = $response;
    }

    /**
     * Check and process AB test on checkout initialization
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function processCheckoutABTest($observer)
    {
        // Skip if Tapbuy is disabled or it's a Tapbuy API request
        if (!$this->config->isEnabled() || $this->helper->isTapbuyApiRequest()) {
            return;
        }

        try {
            // Trigger AB test
            $result = $this->service->triggerABTest();

            // Process redirection if needed
            if ($result && isset($result['redirect']) && $result['redirect'] && isset($result['redirectURL'])) {
                $controller = $observer->getEvent()->getControllerAction();
                if ($controller) {
                    $this->actionFlag->set('', \Magento\Framework\App\Action\Action::FLAG_NO_DISPATCH, true);
                    $this->response->setRedirect($result['redirectURL']);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error processing checkout AB test: ' . $e->getMessage());
        }
    }

    /**
     * Process order transaction after order placement
     *
     * @param \Magento\Sales\Model\Order $order
     * @return void
     */
    public function processOrderTransaction($order)
    {
        // Skip if Tapbuy is disabled or it's a Tapbuy API request
        if (!$this->config->isEnabled() || $this->helper->isTapbuyApiRequest()) {
            return;
        }

        try {
            $result = $this->service->sendTransactionForOrder($order);

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

    /**
     * Determine if redirection should be allowed based on device type
     *
     * @return bool
     */
    public function shouldAllowRedirection()
    {
        $userAgent = $this->helper->getUserAgent();
        $isMobile = $this->isMobileDevice($userAgent);

        if ($isMobile) {
            return $this->config->isMobileRedirectionEnabled();
        } else {
            return $this->config->isDesktopRedirectionEnabled();
        }
    }

    /**
     * Check if the current device is mobile
     *
     * @param string $userAgent
     * @return bool
     */
    private function isMobileDevice($userAgent)
    {
        return (bool) preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent);
    }
}

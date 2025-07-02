<?php
/**
 * Tapbuy Redirect and Tracking - Redirect Controller
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Controller\Redirect;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Psr\Log\LoggerInterface;
use Tapbuy\RedirectTracking\Helper\Data;
use Tapbuy\RedirectTracking\Model\Config;
use Tapbuy\RedirectTracking\Model\Service;

class Index extends Action
{
    /**
     * @var RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Service
     */
    protected $service;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Index constructor.
     *
     * @param Context $context
     * @param RedirectFactory $resultRedirectFactory
     * @param CheckoutSession $checkoutSession
     * @param Config $config
     * @param Service $service
     * @param Data $helper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        RedirectFactory $resultRedirectFactory,
        CheckoutSession $checkoutSession,
        Config $config,
        Service $service,
        Data $helper,
        LoggerInterface $logger
    ) {
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->checkoutSession = $checkoutSession;
        $this->config = $config;
        $this->service = $service;
        $this->helper = $helper;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        // If Tapbuy is disabled, redirect to normal checkout
        if (!$this->config->isEnabled()) {
            return $resultRedirect->setPath('checkout');
        }

        try {
            // Get parameters
            $variationId = $this->getRequest()->getParam('variation');
            
            // Store the variation ID in cookie if provided
            if ($variationId) {
                $this->helper->setABTestIdCookie($variationId);
            }

            // Check if there's a back parameter to return to a specific URL
            $backUrl = $this->getRequest()->getParam('back');
            if ($backUrl) {
                // Validate and decode the URL
                $decodedUrl = base64_decode($backUrl);
                if (filter_var($decodedUrl, FILTER_VALIDATE_URL)) {
                    // Redirect to the decoded URL
                    return $resultRedirect->setUrl($decodedUrl);
                }
            }

            // If no valid back URL, trigger A/B test
            $result = $this->service->triggerABTest();
            
            // Check if we should redirect to Tapbuy
            if ($result && isset($result['redirect']) && $result['redirect'] === true && isset($result['redirectURL'])) {
                return $resultRedirect->setUrl($result['redirectURL']);
            }
            
            // Default: redirect to standard checkout
            return $resultRedirect->setPath('checkout');
        } catch (\Exception $e) {
            $this->logger->error('Error in Tapbuy redirect controller: ' . $e->getMessage());
            return $resultRedirect->setPath('checkout');
        }
    }
}
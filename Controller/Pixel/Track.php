<?php

/**
 * Tapbuy Pixel Tracking Controller
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Controller\Pixel;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Tapbuy\RedirectTracking\Logger\TapbuyLogger;
use Tapbuy\RedirectTracking\Helper\Data;

class Track implements HttpGetActionInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var RawFactory
     */
    private $rawFactory;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var TapbuyLogger
     */
    private $logger;

    /**
     * Track constructor.
     *
     * @param RequestInterface $request
     * @param RawFactory $rawFactory
     * @param Data $helper
     * @param TapbuyLogger $logger
     */
    public function __construct(
        RequestInterface $request,
        RawFactory $rawFactory,
        Data $helper,
        TapbuyLogger $logger
    ) {
        $this->request = $request;
        $this->rawFactory = $rawFactory;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * Execute pixel tracking request
     *
     * @return Raw
     */
    public function execute()
    {
        try {
            // Get pixel data from request
            $encodedData = $this->request->getParam('data');
            $pixelData = [];
            if ($encodedData) {
                $pixelData = json_decode(base64_decode($encodedData), true) ?: [];
            }

            // Collect cookies sent as query parameters (cookie_* format)
            $cookies = [];
            foreach ($this->request->getParams() as $key => $value) {
                if (strpos($key, 'cookie_') === 0) {
                    $cookieName = substr($key, 7); // Remove 'cookie_' prefix
                    $cookies[$cookieName] = $value;
                }
            }

            // Example: Use a specific cookie for AB test logic
            if (isset($cookies['tb-abtest-id'])) {
                $this->helper->setABTestIdCookie($cookies['tb-abtest-id']);
            }

            // Optionally: log or process all cookies as needed
            // $this->logger->info('Pixel cookies', $cookies);

            // Continue with existing pixel data logic
            if ($pixelData && is_array($pixelData)) {
                $this->processPixelData($pixelData);
            }
        } catch (\Exception $e) {
            $this->logger->logException('Pixel tracking error', $e, [
                'data' => $this->request->getParam('data'),
            ]);
        }

        // Always return a 1x1 transparent GIF
        return $this->createPixelResponse();
    }

    /**
     * Process pixel tracking data
     *
     * @param array $pixelData
     * @return void
     */
    private function processPixelData(array $pixelData)
    {
        $this->logger->info('Pixel tracking data received', $pixelData);

        // Handle A/B test ID cookie
        if (isset($pixelData['test_id']) && $pixelData['test_id']) {
            $this->helper->setABTestIdCookie($pixelData['test_id']);
        }

        // Handle cookie removal
        if (isset($pixelData['remove_test_cookie']) && $pixelData['remove_test_cookie']) {
            $this->helper->removeABTestIdCookie();
        }

        // Log action for analytics
        $action = $pixelData['action'] ?? 'unknown';
        $cartId = $pixelData['cart_id'] ?? 'unknown';

        $this->logger->info('Tapbuy pixel fired', [
            'action' => $action,
            'cart_id' => $cartId,
            'test_id' => $pixelData['test_id'] ?? null,
            'timestamp' => $pixelData['timestamp'] ?? time()
        ]);
    }

    /**
     * Create 1x1 transparent GIF response
     *
     * @return Raw
     */
    private function createPixelResponse()
    {
        // 1x1 transparent GIF in base64
        $gifData = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        $response = $this->rawFactory->create();
        $response->setHeader('Content-Type', 'image/gif');
        $response->setHeader('Content-Length', strlen($gifData));
        $response->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->setHeader('Pragma', 'no-cache');
        $response->setHeader('Expires', '0');
        $response->setContents($gifData);

        return $response;
    }
}

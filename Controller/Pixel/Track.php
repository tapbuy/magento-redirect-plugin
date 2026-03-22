<?php

declare(strict_types=1);

/**
 * Tapbuy Pixel Tracking Controller
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Controller\Pixel;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\DataHelperInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;
use Tapbuy\RedirectTracking\Model\Validator\PixelInputValidator;

class Track implements HttpGetActionInterface
{
    /**
     * @param RequestInterface $request
     * @param RawFactory $rawFactory
     * @param DataHelperInterface $helper
     * @param LoggerInterface $logger
     * @param ConfigInterface $config
     * @param PixelInputValidator $pixelInputValidator
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawFactory,
        private readonly DataHelperInterface $helper,
        private readonly LoggerInterface $logger,
        private readonly ConfigInterface $config,
        private readonly PixelInputValidator $pixelInputValidator
    ) {
    }

    /**
     * Execute pixel tracking request
     *
     * @return Raw
     */
    public function execute()
    {
        if (!$this->config->isEnabled()) {
            return $this->createPixelResponse();
        }

        try {
            $encodedData = $this->getValidEncodedData();

            if ($encodedData !== null && !$this->pixelInputValidator->isInputSizeValid($encodedData)) {
                $this->logger->warning('Pixel tracking: input exceeds maximum allowed size, request ignored');
                return $this->createPixelResponse();
            }

            $pixelData = $encodedData !== null ? $this->pixelInputValidator->decodeAndSanitize($encodedData) : [];
            $cookies = $this->extractCookiesFromRequest();

            if (isset($cookies['tb-abtest-id'])) {
                $this->helper->setABTestIdCookie($cookies['tb-abtest-id']);
            }

            if (!empty($pixelData)) {
                $this->processPixelData($pixelData);
            }
        } catch (\RuntimeException $e) {
            $this->logger->logException('Pixel tracking error', $e, [
                'data' => $this->request->getParam('data'),
            ]);
        }

        // Always return a 1x1 transparent GIF
        return $this->createPixelResponse();
    }

    /**
     * Return the raw data query parameter if it is a non-empty string, null otherwise.
     *
     * Treats non-string values (e.g. ?data[]=...) as invalid to prevent TypeErrors
     * from propagating through strict_types code.
     *
     * @return string|null
     */
    private function getValidEncodedData(): ?string
    {
        $rawParam = $this->request->getParam('data');
        if (!is_string($rawParam) || $rawParam === '') {
            return null;
        }
        return $rawParam;
    }

    /**
     * Collect and sanitize cookies sent as cookie_* query parameters.
     *
     * Non-scalar values (e.g. cookie_foo[]=x) are silently dropped to avoid
     * "Array to string conversion" notices and garbage log entries.
     *
     * @return array<string, string>
     */
    private function extractCookiesFromRequest(): array
    {
        $cookies = [];
        foreach ($this->request->getParams() as $key => $value) {
            if (strpos($key, 'cookie_') !== 0) {
                continue;
            }
            if (!is_scalar($value)) {
                continue;
            }
            $cookies[substr($key, 7)] = $this->pixelInputValidator->sanitizeCookieValue((string) $value);
        }
        return $cookies;
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
        // 1x1 transparent GIF
        $gifData = hex2bin('47494638396101000100800000000000ffffff21f90401000000002c000000000100010000020144003b');

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

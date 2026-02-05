<?php

declare(strict_types=1);

/**
 * Tapbuy Pixel Tracking Service
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Service;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class PixelService
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * PixelService constructor.
     *
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
    }

    /**
     * Generate pixel tracking URL for headless frontends
     *
     * @param array $data
     * @return string
     */
    public function generatePixelUrl(array $data = []): string
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);
        $encodedData = base64_encode(json_encode($data));

        return $baseUrl . 'tapbuy/pixel/track?data=' . $encodedData;
    }

    /**
     * Generate pixel data for A/B test tracking
     *
     * @param string $cartId
     * @param array $testResult
     * @param string $action
     * @return array
     */
    public function generatePixelData(
        string $cartId,
        array $testResult = [],
        string $action = 'redirect_check'
    ): array {
        return [
            'cart_id' => $cartId,
            'test_id' => $testResult['id'] ?? null,
            'action' => $action,
            'timestamp' => time(),
            'variation_id' => $testResult['variation_id'] ?? null,
            'remove_test_cookie' => empty($testResult['id']) ? true : false
        ];
    }
}

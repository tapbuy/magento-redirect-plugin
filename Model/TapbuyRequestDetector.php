<?php

/**
 * Tapbuy Request Detector
 *
 * Centralized service to detect Tapbuy-originated requests.
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Model;

use Magento\Framework\App\RequestInterface;
use Tapbuy\RedirectTracking\Api\TapbuyConstants;
use Tapbuy\RedirectTracking\Api\TapbuyRequestDetectorInterface;

class TapbuyRequestDetector implements TapbuyRequestDetectorInterface
{
    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param RequestInterface $request
     * @param Config $config
     */
    public function __construct(
        RequestInterface $request,
        Config $config
    ) {
        $this->request = $request;
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function isTapbuyCall(): bool
    {
        $header = $this->request->getHeader(TapbuyConstants::HTTP_HEADER_TAPBUY_CALL);
        return !empty($header);
    }

    /**
     * @inheritDoc
     */
    public function isTapbuyApiRequest(): bool
    {
        $apiKey = $this->config->getApiKey();
        if (empty($apiKey)) {
            return false;
        }

        $headerKey = $this->request->getHeader(TapbuyConstants::HTTP_HEADER_TAPBUY_KEY);
        return $headerKey === $apiKey;
    }
}

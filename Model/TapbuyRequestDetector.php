<?php

declare(strict_types=1);

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
     * @param RequestInterface $request
     */
    public function __construct(
        RequestInterface $request
    ) {
        $this->request = $request;
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
    public function getRequestUri(): string
    {
        return $this->request->getRequestUri();
    }

    /**
     * @inheritDoc
     */
    public function getTraceId(): ?string
    {
        // Only return trace ID for Tapbuy-initiated requests
        if (!$this->isTapbuyCall()) {
            return null;
        }

        $traceId = $this->request->getHeader(TapbuyConstants::HTTP_HEADER_TAPBUY_TRACE_ID);
        return (is_string($traceId) && !empty($traceId)) ? $traceId : null;
    }
}

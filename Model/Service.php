<?php

/**
 * Tapbuy Redirect and Tracking Service Model
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Model;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Tapbuy\RedirectTracking\Helper\Data;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;

class Service
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * Service constructor.
     *
     * @param Config $config
     * @param Curl $curl
     * @param Json $json
     * @param LoggerInterface $logger
     * @param Data $helper
     * @param RequestInterface $request
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        Config $config,
        Curl $curl,
        Json $json,
        LoggerInterface $logger,
        Data $helper,
        RequestInterface $request,
        UrlInterface $urlBuilder
    ) {
        $this->config = $config;
        $this->curl = $curl;
        $this->json = $json;
        $this->logger = $logger;
        $this->helper = $helper;
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Send API request to Tapbuy
     *
     * @param string $endpoint
     * @param array $payload
     * @return array|bool
     */
    public function sendRequest($endpoint, $payload)
    {
        if (!$this->config->isEnabled()) {
            return false;
        }

        $apiUrl = $this->config->getApiUrl();
        if (empty($apiUrl)) {
            $this->logger->error('Tapbuy API URL is not configured');
            return false;
        }

        $url = rtrim($apiUrl, '/') . '/' . ltrim($endpoint, '/');

        try {
            // Set headers
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->addHeader('Origin', $this->urlBuilder->getBaseUrl());
            $this->curl->addHeader('User-Agent', $this->request->getHeader('User-Agent'));
            $this->curl->addHeader('X-Locale', $this->helper->getLocale());
            $this->curl->setOption(CURLOPT_SSL_VERIFYHOST, false);
            $this->curl->setOption(CURLOPT_SSL_VERIFYPEER, false);
            // Send request
            $this->curl->post($url, $this->json->serialize($payload));

            // Get response
            $response = $this->curl->getBody();
            $statusCode = $this->curl->getStatus();

            if ($statusCode >= 200 && $statusCode < 300 && !empty($response)) {
                return $this->json->unserialize($response);
            }

            $this->logger->error(
                'Tapbuy API request failed',
                [
                    'endpoint' => $endpoint,
                    'status' => $statusCode,
                    'response' => $response
                ]
            );

            return false;
        } catch (\Exception $e) {
            $this->logger->error(
                'Error sending Tapbuy API request: ' . $e->getMessage(),
                [
                    'endpoint' => $endpoint,
                    'exception' => $e->getTraceAsString()
                ]
            );

            return false;
        }
    }

    /**
     * Send transaction data to Tapbuy
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array|bool
     */
    public function sendTransactionForOrder($order)
    {
        if (!$this->config->isEnabled() || $this->helper->isTapbuyApiRequest()) {
            return false;
        }

        try {
            // Build payload
            $payload = [
                'orderId' => $order->getIncrementId(),
                'orderTotal' => (float)$order->getGrandTotal(),
                'orderCoupon' => $order->getCouponCode(),
                'orderPaymentMethod' => $order->getPayment() ? $order->getPayment()->getMethod() : null,
                'orderShippingMethod' => $order->getShippingMethod(),
                'path' => $this->helper->getCurrentPath(),
                'variationId' => $this->helper->getABTestId()
            ];

            return $this->sendRequest('/ab-test/transaction', $payload);
        } catch (\Exception $e) {
            $this->logger->error('Error sending transaction to Tapbuy: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Trigger A/B test
     *
     * @return array|bool
     */
    public function triggerABTest($quote, $forceRedirect = null)
    {
        if (!$this->config->isEnabled() || $this->helper->isTapbuyApiRequest()) {
            return ['redirect' => false];
        }

        // Check if the cart has at least one product
        if (!$this->helper->hasProductsInCart($quote)) {
            $this->helper->removeABTestIdCookie();
            return ['redirect' => false];
        }

        try {
            // Build payload
            $payload = [
                'tracking' => $this->helper->getTrackingCookies(),
                'variationId' => $this->helper->getABTestId(),
                'cookies' => $this->helper->getStoreCookies(),
                'key' => $this->helper->getTapbuyKey($quote),
                'locale' => $this->helper->getLocale(),
                'forceRedirect' => $forceRedirect,
            ];

            $result = $this->sendRequest('/ab-test/variation', $payload);

            if ($result) {
                if (isset($result['id'])) {
                    $this->helper->setABTestIdCookie($result['id']);
                };
                return $result;
            }

            $this->helper->removeABTestIdCookie();
            return ['redirect' => false];
        } catch (\Exception $e) {
            $this->logger->error('Error triggering A/B test: ' . $e->getMessage());
            $this->helper->removeABTestIdCookie();
            return ['redirect' => false];
        }
    }
}

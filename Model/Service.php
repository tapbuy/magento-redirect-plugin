<?php

declare(strict_types=1);

/**
 * Tapbuy Redirect and Tracking Service Model
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Model;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\DataHelperInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;
use Tapbuy\RedirectTracking\Api\TapbuyRequestDetectorInterface;
use Tapbuy\RedirectTracking\Api\TapbuyServiceInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;

class Service implements TapbuyServiceInterface
{
    /**
     * @var ConfigInterface
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
     * @var DataHelperInterface
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
     * @var TapbuyRequestDetectorInterface
     */
    private $requestDetector;

    /**
     * Service constructor.
     *
     * @param ConfigInterface $config
     * @param Curl $curl
     * @param Json $json
     * @param LoggerInterface $logger
     * @param DataHelperInterface $helper
     * @param RequestInterface $request
     * @param UrlInterface $urlBuilder
     * @param TapbuyRequestDetectorInterface $requestDetector
     */
    public function __construct(
        ConfigInterface $config,
        Curl $curl,
        Json $json,
        LoggerInterface $logger,
        DataHelperInterface $helper,
        RequestInterface $request,
        UrlInterface $urlBuilder,
        TapbuyRequestDetectorInterface $requestDetector
    ) {
        $this->config = $config;
        $this->curl = $curl;
        $this->json = $json;
        $this->logger = $logger;
        $this->helper = $helper;
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->requestDetector = $requestDetector;
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

        $url = trim($apiUrl, '/') . '/' . trim($endpoint, '/');

        try {
            $this->configureCurl();
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
            $this->logger->logException('Error sending Tapbuy API request', $e, [
                'endpoint' => $endpoint,
            ]);

            return false;
        }
    }

    /**
     * Send transaction data to Tapbuy
     *
     * @param Order $order
     * @param int|null $abTestId Used with tapbuyConfirmOrder GraphQL mutation for headless implementations
     * @return array|bool
     */
    public function sendTransactionForOrder($order, $abTestId = null)
    {
        if (!$this->config->isEnabled() || $this->requestDetector->isTapbuyApiRequest()) {
            return false;
        }

        try {
            $abTestId = $abTestId ?? $this->helper->getABTestId();
            $payload = [
                'orderId' => $order->getIncrementId(),
                'orderTotal' => (float)$order->getGrandTotal(),
                'orderCoupon' => $order->getCouponCode(),
                'orderPaymentMethod' => $order->getPayment() ? $order->getPayment()->getMethod() : null,
                'orderShippingMethod' => $order->getShippingMethod(),
                'path' => $this->helper->getCurrentPath(),
                'variationId' => $abTestId
            ];

            return $this->sendRequest('/ab-test/transaction', $payload);
        } catch (\Exception $e) {
            $this->logger->logException('Error sending transaction to Tapbuy', $e, [
                'order_id' => $order->getIncrementId(),
            ]);
            return false;
        }
    }

    /**
     * Trigger A/B test
     *
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @param bool|null $forceRedirect
     * @param string|null $referer
     * @return array|bool
     */
    public function triggerABTest($quote, $forceRedirect = null, $referer = null)
    {
        if (!$this->config->isEnabled() || $this->requestDetector->isTapbuyApiRequest()) {
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
                'referer' => $referer,
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
            $this->logger->logException('Error triggering A/B test', $e);
            $this->helper->removeABTestIdCookie();
            return ['redirect' => false];
        }
    }

    /**
     * Configures the cURL settings for HTTP requests.
     *
     * This method sets up the necessary cURL options to ensure proper communication
     * with external services. It does not return any value.
     *
     * @return void
     */
    private function configureCurl(): void
    {
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->addHeader('Origin', $this->urlBuilder->getBaseUrl());
        $this->curl->addHeader('User-Agent', $this->request->getHeader('User-Agent'));
        $this->curl->addHeader('X-Locale', $this->helper->getLocale());

        // Only disable SSL verification in development
        if ($this->helper->isDevelopmentMode()) {
            $this->curl->setOption(CURLOPT_SSL_VERIFYHOST, false);
            $this->curl->setOption(CURLOPT_SSL_VERIFYPEER, false);
        }

        // Set timeout
        $this->curl->setOption(CURLOPT_TIMEOUT, 30);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 10);
    }
}

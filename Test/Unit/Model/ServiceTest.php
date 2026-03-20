<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Model;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\DataHelperInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;
use Tapbuy\RedirectTracking\Api\TapbuyRequestDetectorInterface;
use Tapbuy\RedirectTracking\Model\Service;

class ServiceTest extends TestCase
{
    private Service $service;
    private ConfigInterface&MockObject $config;
    private Curl&MockObject $curl;
    private Json&MockObject $json;
    private LoggerInterface&MockObject $logger;
    private DataHelperInterface&MockObject $helper;
    private RequestInterface&MockObject $request;
    private UrlInterface&MockObject $urlBuilder;
    private TapbuyRequestDetectorInterface&MockObject $requestDetector;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->curl = $this->createMock(Curl::class);
        $this->json = $this->createMock(Json::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->helper = $this->createMock(DataHelperInterface::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->requestDetector = $this->createMock(TapbuyRequestDetectorInterface::class);

        $this->service = new Service(
            $this->config,
            $this->curl,
            $this->json,
            $this->logger,
            $this->helper,
            $this->request,
            $this->urlBuilder,
            $this->requestDetector
        );
    }

    public function testSendRequestReturnsFalseWhenDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->assertFalse($this->service->sendRequest('/test', []));
    }

    public function testSendRequestReturnsFalseWhenApiUrlEmpty(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getApiUrl')->willReturn('');

        $this->logger->expects($this->once())->method('error');

        $this->assertFalse($this->service->sendRequest('/test', []));
    }

    public function testSendRequestReturnsDeserializedResponseOnSuccess(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getApiUrl')->willReturn('https://api.tapbuy.com');
        $this->config->method('getEncryptionKey')->willReturn(null);

        $this->helper->method('isDevelopmentMode')->willReturn(false);
        $this->helper->method('getLocale')->willReturn('en_US');
        $this->urlBuilder->method('getBaseUrl')->willReturn('https://shop.com/');
        $this->request->method('getHeader')->willReturn('Mozilla/5.0');

        $this->json->method('serialize')->willReturn('{}');
        $this->curl->method('getBody')->willReturn('{"success":true}');
        $this->curl->method('getStatus')->willReturn(200);
        $this->json->method('unserialize')->with('{"success":true}')->willReturn(['success' => true]);

        $result = $this->service->sendRequest('/endpoint', ['key' => 'value']);
        $this->assertSame(['success' => true], $result);
    }

    public function testSendRequestReturnsFalseOnNon2xxStatus(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getApiUrl')->willReturn('https://api.tapbuy.com');

        $this->helper->method('isDevelopmentMode')->willReturn(false);
        $this->helper->method('getLocale')->willReturn('en_US');
        $this->urlBuilder->method('getBaseUrl')->willReturn('https://shop.com/');
        $this->request->method('getHeader')->willReturn('Mozilla/5.0');

        $this->json->method('serialize')->willReturn('{}');
        $this->curl->method('getBody')->willReturn('Error');
        $this->curl->method('getStatus')->willReturn(500);

        $this->logger->expects($this->once())->method('error');

        $this->assertFalse($this->service->sendRequest('/endpoint', []));
    }

    public function testSendRequestCatchesRuntimeException(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getApiUrl')->willReturn('https://api.tapbuy.com');

        $this->helper->method('isDevelopmentMode')->willReturn(false);
        $this->helper->method('getLocale')->willReturn('en_US');
        $this->urlBuilder->method('getBaseUrl')->willReturn('https://shop.com/');
        $this->request->method('getHeader')->willReturn('Mozilla/5.0');

        $this->json->method('serialize')->willReturn('{}');
        $this->curl->method('post')->willThrowException(new \RuntimeException('Curl error'));

        $this->logger->expects($this->once())->method('logException');

        $this->assertFalse($this->service->sendRequest('/endpoint', []));
    }

    public function testSendTransactionReturnsFalseWhenDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $order = $this->createMock(Order::class);
        $this->assertFalse($this->service->sendTransactionForOrder($order));
    }

    public function testSendTransactionReturnsFalseWhenTapbuyCall(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->requestDetector->method('isTapbuyCall')->willReturn(true);
        $order = $this->createMock(Order::class);
        $this->assertFalse($this->service->sendTransactionForOrder($order));
    }

    public function testTriggerABTestReturnsNoRedirectWhenDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $quote = $this->createMock(CartInterface::class);
        $result = $this->service->triggerABTest($quote);
        $this->assertSame(['redirect' => false], $result);
    }

    public function testTriggerABTestReturnsNoRedirectWhenEmptyCart(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->requestDetector->method('isTapbuyCall')->willReturn(false);
        $this->helper->method('hasProductsInCart')->willReturn(false);

        $this->helper->expects($this->once())->method('updateABTestCookie')->with(null);

        $quote = $this->createMock(CartInterface::class);
        $result = $this->service->triggerABTest($quote);
        $this->assertSame(['redirect' => false], $result);
    }
}
